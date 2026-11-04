use super::pin::hash_pin;
use super::service::{ResultadoInicioSesion, iniciar_sesion_pin};
use super::session::desactivar_terminal_y_revocar_sesiones;
use sqlx::postgres::PgPoolOptions;
use std::time::Duration;
use tokio::time::timeout;
use uuid::Uuid;

#[tokio::test]
async fn desactivacion_terminal_antes_de_login_rechaza_acceso()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let url = std::env::var("DATABASE_URL")?;

    let pool = PgPoolOptions::new()
        .max_connections(5)
        .connect(&url)
        .await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, 'TEST_CONCURRENCIA_TERMINAL')",
    )
    .bind(establecimiento_id)
    .execute(&pool)
    .await?;

    // Ejecutar el escenario en una tarea separada para poder
    // limpiar los datos incluso si una aserción falla.
    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        let pool = pool_prueba;

        sqlx::query(
            "INSERT INTO terminales (id, establecimiento_id, nombre)
             VALUES ($1, $2, 'Terminal concurrencia')",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, 'Rol concurrencia')",
        )
        .bind(rol_id)
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, 'Usuario concurrencia', $4)",
        )
        .bind(usuario_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind(hash_pin("1234").unwrap())
        .execute(&pool)
        .await?;

        // Transacción A: bloquear la terminal.
        let mut tx = pool.begin().await?;

        sqlx::query(
            "SELECT id FROM terminales
             WHERE id = $1
             FOR UPDATE",
        )
        .bind(terminal_id)
        .fetch_one(&mut *tx)
        .await?;

        // Transacción B: iniciar login concurrente.
        let pool_login = pool.clone();

        let login = tokio::spawn(async move {
            iniciar_sesion_pin(
                &pool_login,
                establecimiento_id,
                terminal_id,
                usuario_id,
                "1234",
            )
            .await
        });

        // El login no debe terminar mientras la terminal
        // permanezca bloqueada.
        let mut login = login;

        assert!(
            timeout(Duration::from_millis(300), &mut login)
                .await
                .is_err(),
            "El login no debería superar el bloqueo de terminal"
        );

        // Desactivar terminal dentro de la transacción A.
        sqlx::query(
            "UPDATE terminales
             SET activo = FALSE
             WHERE id = $1",
        )
        .bind(terminal_id)
        .execute(&mut *tx)
        .await?;

        tx.commit().await?;

        // La función de desactivación debe ser idempotente
        // y revocar cualquier sesión abierta.
        let desactivada =
            desactivar_terminal_y_revocar_sesiones(&pool, establecimiento_id, terminal_id).await?;

        assert!(desactivada);

        let resultado = timeout(Duration::from_secs(5), login).await???;

        assert!(
            matches!(resultado, ResultadoInicioSesion::Incorrecto),
            "El login debe rechazarse tras desactivar la terminal"
        );

        let sesiones_abiertas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE establecimiento_id = $1
               AND terminal_id = $2
               AND cerrada_en IS NULL",
        )
        .bind(establecimiento_id)
        .bind(terminal_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(sesiones_abiertas, 0);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpieza.
    sqlx::query("DELETE FROM sesiones_usuario WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM usuarios WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    prueba??;

    Ok(())
}

#[tokio::test]
async fn login_primero_desactivacion_espera_y_revoca_sesion()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use super::repository::terminal_activa;
    use super::session::validar_token_sesion;

    let url = std::env::var("DATABASE_URL")?;
    let pool = PgPoolOptions::new()
        .max_connections(5)
        .connect(&url)
        .await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    // Los datos se crean dentro de una transacción de preparación.
    // Si la prueba falla antes del commit, PostgreSQL hace rollback.
    let mut preparacion = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, 'TEST_LOGIN_PRIMERO_TERMINAL')",
    )
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, 'Terminal login primero')",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, 'Rol login primero')",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, 'Usuario login primero', $4)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind(hash_pin("1234").unwrap())
    .execute(&mut *preparacion)
    .await?;

    preparacion.commit().await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        let pool = pool_prueba;

        // Login real: crear una sesión válida.
        let token =
            match iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234")
                .await?
            {
                ResultadoInicioSesion::Autenticado { token, .. } => token,
                otro => panic!("Se esperaba autenticación: {otro:?}"),
            };

        assert!(
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id,)
                .await?
                .is_some()
        );

        // Mantener un bloqueo de lectura equivalente al que
        // adquiere el login antes de crear la sesión.
        let mut tx_login = pool.begin().await?;

        assert!(terminal_activa(&mut tx_login, establecimiento_id, terminal_id,).await?);

        // Iniciar la desactivación en otra conexión.
        let pool_desactivacion = pool.clone();

        let mut desactivacion = tokio::spawn(async move {
            desactivar_terminal_y_revocar_sesiones(
                &pool_desactivacion,
                establecimiento_id,
                terminal_id,
            )
            .await
        });

        // La desactivación no puede obtener FOR UPDATE
        // mientras mantenemos FOR SHARE.
        assert!(
            timeout(Duration::from_millis(300), &mut desactivacion,)
                .await
                .is_err(),
            "La desactivación debe esperar el bloqueo de terminal"
        );

        // Simular el final de la transacción de login.
        tx_login.commit().await?;

        let desactivada = timeout(Duration::from_secs(5), desactivacion).await???;

        assert!(desactivada);

        // La sesión debe quedar revocada permanentemente.
        let sesion: (bool, Option<String>) = sqlx::query_as(
            "SELECT cerrada_en IS NOT NULL, motivo_cierre
             FROM sesiones_usuario
             WHERE token_hash = $1",
        )
        .bind(super::token::hash_token(&token))
        .fetch_one(&pool)
        .await?;

        assert!(sesion.0);
        assert_eq!(sesion.1.as_deref(), Some("terminal_desactivada"));

        assert!(
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id,)
                .await?
                .is_none()
        );

        // Reactivar no debe recuperar el token revocado.
        sqlx::query("UPDATE terminales SET activo = TRUE WHERE id = $1")
            .bind(terminal_id)
            .execute(&pool)
            .await?;

        assert!(
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id,)
                .await?
                .is_none()
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpieza independiente del resultado de la tarea.
    for tabla in ["sesiones_usuario", "usuarios", "roles", "terminales"] {
        let consulta = format!("DELETE FROM {tabla} WHERE establecimiento_id = $1");

        sqlx::query(&consulta)
            .bind(establecimiento_id)
            .execute(&pool)
            .await?;
    }

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    prueba??;

    Ok(())
}

#[tokio::test]
async fn login_real_concurrente_con_desactivacion_revoca_token()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use super::service::{SincronizacionLogin, iniciar_sesion_pin_interno};
    use super::session::validar_token_sesion;
    use tokio::sync::oneshot;

    let url = std::env::var("DATABASE_URL")?;
    let pool = PgPoolOptions::new()
        .max_connections(5)
        .connect(&url)
        .await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    // Preparar los datos en una transacción.
    let mut preparacion = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, 'TEST_LOGIN_REAL_CONCURRENTE')",
    )
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, 'Terminal login real')",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, 'Rol login real')",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .execute(&mut *preparacion)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, 'Usuario login real', $4)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind(hash_pin("1234").unwrap())
    .execute(&mut *preparacion)
    .await?;

    preparacion.commit().await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        let pool = pool_prueba;

        let (bloqueo_tx, bloqueo_rx) = oneshot::channel();
        let (continuar_tx, continuar_rx) = oneshot::channel();

        let pool_login = pool.clone();

        // Login real: se detendrá después de bloquear la terminal.
        let mut login = tokio::spawn(async move {
            iniciar_sesion_pin_interno(
                &pool_login,
                establecimiento_id,
                terminal_id,
                usuario_id,
                "1234",
                Some(SincronizacionLogin {
                    bloqueo_adquirido: bloqueo_tx,
                    continuar: continuar_rx,
                }),
            )
            .await
        });

        // Esperar la confirmación del bloqueo FOR SHARE.
        timeout(Duration::from_secs(5), bloqueo_rx).await??;

        // Desactivación concurrente.
        let pool_desactivacion = pool.clone();

        let mut desactivacion = tokio::spawn(async move {
            desactivar_terminal_y_revocar_sesiones(
                &pool_desactivacion,
                establecimiento_id,
                terminal_id,
            )
            .await
        });

        // No debe completarse mientras el login mantenga
        // bloqueada la terminal.
        assert!(
            timeout(Duration::from_millis(300), &mut desactivacion)
                .await
                .is_err(),
            "La desactivación debe esperar al login real"
        );

        // Permitir que el login cree y confirme la sesión.
        continuar_tx
            .send(())
            .expect("El login debe seguir esperando");

        let resultado = timeout(Duration::from_secs(5), &mut login).await???;

        let token = match resultado {
            ResultadoInicioSesion::Autenticado { token, .. } => token,
            otro => panic!("Se esperaba autenticación: {otro:?}"),
        };

        // La desactivación debe finalizar después del login.
        let desactivada = timeout(Duration::from_secs(5), desactivacion).await???;

        assert!(desactivada);

        let sesion: (bool, Option<String>) = sqlx::query_as(
            "SELECT cerrada_en IS NOT NULL, motivo_cierre
             FROM sesiones_usuario
             WHERE token_hash = $1",
        )
        .bind(super::token::hash_token(&token))
        .fetch_one(&pool)
        .await?;

        assert!(sesion.0);
        assert_eq!(sesion.1.as_deref(), Some("terminal_desactivada"));

        assert!(
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id,)
                .await?
                .is_none()
        );

        // Reactivar la terminal no debe restaurar el token.
        sqlx::query("UPDATE terminales SET activo = TRUE WHERE id = $1")
            .bind(terminal_id)
            .execute(&pool)
            .await?;

        assert!(
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id,)
                .await?
                .is_none()
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar los datos de la prueba.
    for tabla in ["sesiones_usuario", "usuarios", "roles", "terminales"] {
        let consulta = format!("DELETE FROM {tabla} WHERE establecimiento_id = $1");

        sqlx::query(&consulta)
            .bind(establecimiento_id)
            .execute(&pool)
            .await?;
    }

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    prueba??;

    Ok(())
}
