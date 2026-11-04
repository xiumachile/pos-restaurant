use crate::auth::policy::usuario_esta_bloqueado;
use chrono::{DateTime, Utc};

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ResultadoValidacionPin {
    Correcto,
    Incorrecto,
    Bloqueado,
}

/// Resultado de intentar iniciar una sesión mediante PIN.
pub enum ResultadoInicioSesion {
    Incorrecto,
    Bloqueado,
    SesionYaAbierta,
    Autenticado {
        token: String,
        expira_en: chrono::DateTime<chrono::Utc>,
    },
}

impl std::fmt::Debug for ResultadoInicioSesion {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            Self::Incorrecto => f.write_str("Incorrecto"),
            Self::Bloqueado => f.write_str("Bloqueado"),
            Self::SesionYaAbierta => f.write_str("SesionYaAbierta"),
            Self::Autenticado { expira_en, .. } => f
                .debug_struct("Autenticado")
                .field("token", &"[REDACTED]")
                .field("expira_en", expira_en)
                .finish(),
        }
    }
}

pub fn validar_estado_pin(
    pin_ingresado: &str,
    pin_hash: &str,
    bloqueado_hasta: Option<DateTime<Utc>>,
    ahora: DateTime<Utc>,
) -> ResultadoValidacionPin {
    if usuario_esta_bloqueado(bloqueado_hasta, ahora) {
        return ResultadoValidacionPin::Bloqueado;
    }

    if crate::auth::pin::verify_pin(pin_ingresado, pin_hash) {
        ResultadoValidacionPin::Correcto
    } else {
        ResultadoValidacionPin::Incorrecto
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::auth::pin::hash_pin;
    use chrono::Duration;

    #[test]
    fn debug_resultado_login_oculta_token() {
        let token = "TOKEN_SECRETO_NO_DEBE_APARECER_123456";

        let resultado = ResultadoInicioSesion::Autenticado {
            token: token.to_string(),
            expira_en: Utc::now(),
        };

        let depuracion = format!("{resultado:?}");

        assert!(
            !depuracion.contains(token),
            "Debug nunca debe mostrar el token original"
        );

        assert!(
            depuracion.contains("[REDACTED]"),
            "Debug debe indicar que el token fue ocultado"
        );

        assert!(
            depuracion.contains("Autenticado"),
            "Debug debe conservar el estado de autenticación"
        );
    }

    #[test]
    fn pin_correcto_sin_bloqueo() {
        let hash = hash_pin("1234").unwrap();

        assert_eq!(
            validar_estado_pin("1234", &hash, None, Utc::now()),
            ResultadoValidacionPin::Correcto
        );
    }

    #[test]
    fn pin_incorrecto_sin_bloqueo() {
        let hash = hash_pin("1234").unwrap();

        assert_eq!(
            validar_estado_pin("9999", &hash, None, Utc::now()),
            ResultadoValidacionPin::Incorrecto
        );
    }

    #[test]
    fn pin_correcto_con_bloqueo_vigente() {
        let hash = hash_pin("1234").unwrap();
        let ahora = Utc::now();

        assert_eq!(
            validar_estado_pin("1234", &hash, Some(ahora + Duration::minutes(10)), ahora,),
            ResultadoValidacionPin::Bloqueado
        );
    }

    #[test]
    fn pin_correcto_con_bloqueo_vencido() {
        let hash = hash_pin("1234").unwrap();
        let ahora = Utc::now();

        assert_eq!(
            validar_estado_pin("1234", &hash, Some(ahora - Duration::minutes(1)), ahora,),
            ResultadoValidacionPin::Correcto
        );
    }
}

pub async fn autenticar_pin(
    pool: &sqlx::PgPool,
    establecimiento_id: uuid::Uuid,
    terminal_id: uuid::Uuid,
    usuario_id: uuid::Uuid,
    pin: &str,
) -> Result<ResultadoValidacionPin, sqlx::Error> {
    use crate::auth::repository::{
        bloquear_usuario_login, registrar_pin_incorrecto, reiniciar_intentos_pin, rol_activo,
        terminal_activa,
    };

    let mut tx = pool.begin().await?;

    // Bloquear la fila para serializar los intentos de este usuario.
    let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id).await?;

    let Some(usuario) = usuario else {
        tx.rollback().await?;
        return Ok(ResultadoValidacionPin::Incorrecto);
    };

    // Verificar terminal y rol antes de evaluar el PIN.
    let terminal_valida = terminal_activa(&mut tx, establecimiento_id, terminal_id).await?;

    let rol_valido = rol_activo(&mut tx, establecimiento_id, usuario.rol_id).await?;

    if !terminal_valida || !rol_valido {
        tx.rollback().await?;
        return Ok(ResultadoValidacionPin::Incorrecto);
    }

    // Obtener la hora desde PostgreSQL para mantener
    // una referencia temporal consistente con los UPDATE.
    let ahora: chrono::DateTime<chrono::Utc> = sqlx::query_scalar("SELECT NOW()")
        .fetch_one(&mut *tx)
        .await?;

    let resultado = validar_estado_pin(pin, &usuario.pin_hash, usuario.bloqueado_hasta, ahora);

    match resultado {
        ResultadoValidacionPin::Correcto => {
            reiniciar_intentos_pin(&mut tx, usuario_id).await?;
        }

        ResultadoValidacionPin::Incorrecto => {
            registrar_pin_incorrecto(&mut tx, usuario_id).await?;
        }

        ResultadoValidacionPin::Bloqueado => {
            // No incrementar intentos ni prolongar el bloqueo.
        }
    }

    // Confirmar también los intentos fallidos.
    tx.commit().await?;

    Ok(resultado)
}

/// Autentica un usuario mediante PIN y crea su sesión.
///
/// La validación y creación se realizan en una única transacción.
/// El bloqueo de la fila del usuario serializa los inicios de sesión
/// concurrentes para ese usuario.
pub async fn iniciar_sesion_pin(
    pool: &sqlx::PgPool,
    establecimiento_id: uuid::Uuid,
    terminal_id: uuid::Uuid,
    usuario_id: uuid::Uuid,
    pin: &str,
) -> Result<ResultadoInicioSesion, sqlx::Error> {
    iniciar_sesion_pin_interno(pool, establecimiento_id, terminal_id, usuario_id, pin, None).await
}

#[cfg(test)]
pub(crate) struct SincronizacionLogin {
    pub bloqueo_adquirido: tokio::sync::oneshot::Sender<()>,
    pub continuar: tokio::sync::oneshot::Receiver<()>,
}

pub(in crate::auth) async fn iniciar_sesion_pin_interno(
    pool: &sqlx::PgPool,
    establecimiento_id: uuid::Uuid,
    terminal_id: uuid::Uuid,
    usuario_id: uuid::Uuid,
    pin: &str,
    #[cfg(test)] sincronizacion: Option<SincronizacionLogin>,
    #[cfg(not(test))] _sincronizacion: Option<()>,
) -> Result<ResultadoInicioSesion, sqlx::Error> {
    use crate::auth::repository::{
        bloquear_usuario_login, registrar_pin_incorrecto, reiniciar_intentos_pin, rol_activo,
        terminal_activa,
    };
    use crate::auth::session::{
        cerrar_sesion_vencida, crear_sesion_usuario, usuario_tiene_sesion_abierta,
    };
    use crate::auth::token::{generar_token, hash_token};

    const DURACION_SESION_MINUTOS: i64 = 480;

    let mut tx = pool.begin().await?;

    // Orden de bloqueos: terminal -> usuario -> sesión.
    // Mantener el bloqueo de terminal hasta confirmar el login.
    let terminal_valida = terminal_activa(&mut tx, establecimiento_id, terminal_id).await?;

    if !terminal_valida {
        tx.rollback().await?;
        return Ok(ResultadoInicioSesion::Incorrecto);
    }

    // Sincronización disponible únicamente durante las pruebas.
    #[cfg(test)]
    if let Some(sincronizacion) = sincronizacion {
        let _ = sincronizacion.bloqueo_adquirido.send(());
        let _ = sincronizacion.continuar.await;
    }

    // Serializar los intentos del mismo usuario.
    let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id).await?;

    let Some(usuario) = usuario else {
        tx.rollback().await?;
        return Ok(ResultadoInicioSesion::Incorrecto);
    };

    let rol_valido = rol_activo(&mut tx, establecimiento_id, usuario.rol_id).await?;

    if !rol_valido {
        tx.rollback().await?;
        return Ok(ResultadoInicioSesion::Incorrecto);
    }

    let ahora: chrono::DateTime<chrono::Utc> = sqlx::query_scalar("SELECT NOW()")
        .fetch_one(&mut *tx)
        .await?;

    let resultado = validar_estado_pin(pin, &usuario.pin_hash, usuario.bloqueado_hasta, ahora);

    match resultado {
        ResultadoValidacionPin::Bloqueado => {
            tx.commit().await?;
            return Ok(ResultadoInicioSesion::Bloqueado);
        }

        ResultadoValidacionPin::Incorrecto => {
            registrar_pin_incorrecto(&mut tx, usuario_id).await?;
            tx.commit().await?;
            return Ok(ResultadoInicioSesion::Incorrecto);
        }

        ResultadoValidacionPin::Correcto => {}
    }

    // Cerrar sesiones que hayan vencido.
    cerrar_sesion_vencida(&mut tx, establecimiento_id, usuario_id).await?;

    // No reemplazar automáticamente una sesión vigente.
    if usuario_tiene_sesion_abierta(&mut tx, establecimiento_id, usuario_id).await? {
        tx.commit().await?;
        return Ok(ResultadoInicioSesion::SesionYaAbierta);
    }

    // Generar token aleatorio y persistir únicamente su hash.
    let token = generar_token();
    let token_hash = hash_token(&token);

    let (_sesion_id, expira_en) = crear_sesion_usuario(
        &mut tx,
        establecimiento_id,
        usuario_id,
        terminal_id,
        &token_hash,
        DURACION_SESION_MINUTOS,
    )
    .await?;

    reiniciar_intentos_pin(&mut tx, usuario_id).await?;

    // El token solo se entrega después de confirmar la sesión.
    tx.commit().await?;

    Ok(ResultadoInicioSesion::Autenticado { token, expira_en })
}
