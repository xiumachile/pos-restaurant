use super::pin::hash_pin;
use super::service::{ResultadoInicioSesion, iniciar_sesion_pin};
use super::session::ResultadoDesactivacionUsuario;
use super::session::{
    desactivar_terminal_y_revocar_sesiones, desactivar_usuario_protegido, validar_token_sesion,
};
use super::token::hash_token;
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn iniciar_sesion_pin_postgresql() -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    // Crear datos de prueba.
    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_LOGIN_SESION")
        .execute(&pool)
        .await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        let pool = pool_prueba;
        sqlx::query(
            "INSERT INTO terminales
             (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_TERMINAL_LOGIN")
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO roles
             (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(rol_id)
        .bind(establecimiento_id)
        .bind("TEST_ROL_LOGIN")
        .execute(&pool)
        .await?;

        let pin_hash = hash_pin("1234").unwrap();

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_LOGIN")
        .bind(pin_hash)
        .execute(&pool)
        .await?;

        // Caso 3: bloqueo por cinco intentos.
        let usuario_bloqueo_id = Uuid::new_v4();

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_bloqueo_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_BLOQUEO")
        .bind(hash_pin("5678").unwrap())
        .execute(&pool)
        .await?;

        for intento in 1..=5 {
            let resultado = iniciar_sesion_pin(
                &pool,
                establecimiento_id,
                terminal_id,
                usuario_bloqueo_id,
                "9999",
            )
            .await?;

            assert!(
                matches!(resultado, ResultadoInicioSesion::Incorrecto),
                "El intento {intento} debe rechazarse"
            );
        }

        let (intentos, bloqueado_hasta): (i32, Option<chrono::DateTime<chrono::Utc>>) =
            sqlx::query_as(
                "SELECT intentos_fallidos, bloqueado_hasta
             FROM usuarios WHERE id = $1",
            )
            .bind(usuario_bloqueo_id)
            .fetch_one(&pool)
            .await?;

        assert_eq!(intentos, 5);
        assert!(
            bloqueado_hasta.is_some_and(|fecha| fecha > chrono::Utc::now()),
            "El usuario debe quedar bloqueado"
        );

        // Incluso el PIN correcto debe rechazarse durante el bloqueo.
        let resultado_bloqueado = iniciar_sesion_pin(
            &pool,
            establecimiento_id,
            terminal_id,
            usuario_bloqueo_id,
            "5678",
        )
        .await?;

        assert!(
            matches!(resultado_bloqueado, ResultadoInicioSesion::Bloqueado),
            "El PIN correcto no debe eludir el bloqueo"
        );

        let intentos_finales: i32 =
            sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                .bind(usuario_bloqueo_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(
            intentos_finales, 5,
            "El bloqueo no debe incrementar el contador"
        );

        let sesiones_bloqueadas: i64 =
            sqlx::query_scalar("SELECT COUNT(*) FROM sesiones_usuario WHERE usuario_id = $1")
                .bind(usuario_bloqueo_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(sesiones_bloqueadas, 0);

        // Caso 4: concurrencia entre terminales.
        let usuario_concurrente_id = Uuid::new_v4();
        let segunda_terminal_id = Uuid::new_v4();

        sqlx::query(
            "INSERT INTO terminales
             (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(segunda_terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_SEGUNDA_TERMINAL")
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_concurrente_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_CONCURRENTE")
        .bind(hash_pin("2468").unwrap())
        .execute(&pool)
        .await?;

        let (resultado_a, resultado_b) = tokio::join!(
            iniciar_sesion_pin(
                &pool,
                establecimiento_id,
                terminal_id,
                usuario_concurrente_id,
                "2468",
            ),
            iniciar_sesion_pin(
                &pool,
                establecimiento_id,
                segunda_terminal_id,
                usuario_concurrente_id,
                "2468",
            ),
        );

        let resultados = [resultado_a?, resultado_b?];

        let autenticados = resultados
            .iter()
            .filter(|resultado| matches!(resultado, ResultadoInicioSesion::Autenticado { .. }))
            .count();

        let sesiones_existentes = resultados
            .iter()
            .filter(|resultado| matches!(resultado, ResultadoInicioSesion::SesionYaAbierta))
            .count();

        assert_eq!(autenticados, 1, "Solo una terminal debe autenticarse");
        assert_eq!(
            sesiones_existentes, 1,
            "La segunda terminal debe recibir SesionYaAbierta"
        );

        let sesiones_abiertas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE usuario_id = $1
               AND cerrada_en IS NULL",
        )
        .bind(usuario_concurrente_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(
            sesiones_abiertas, 1,
            "PostgreSQL debe conservar una única sesión abierta"
        );

        // Caso 5: renovación después de expiración.
        let usuario_expiracion_id = Uuid::new_v4();

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_expiracion_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_EXPIRACION")
        .bind(hash_pin("1357").unwrap())
        .execute(&pool)
        .await?;

        let primer_resultado = iniciar_sesion_pin(
            &pool,
            establecimiento_id,
            terminal_id,
            usuario_expiracion_id,
            "1357",
        )
        .await?;

        let primer_token = match primer_resultado {
            ResultadoInicioSesion::Autenticado { token, .. } => token,
            otro => panic!("Se esperaba primera sesión: {otro:?}"),
        };

        let primera_sesion_id: Uuid = sqlx::query_scalar(
            "SELECT id FROM sesiones_usuario
             WHERE usuario_id = $1 AND cerrada_en IS NULL",
        )
        .bind(usuario_expiracion_id)
        .fetch_one(&pool)
        .await?;

        // Simular una sesión vencida conservando la restricción
        // expira_en > iniciada_en de PostgreSQL.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET iniciada_en = NOW() - INTERVAL '10 hours',
                 expira_en = NOW() - INTERVAL '2 hours'
             WHERE id = $1",
        )
        .bind(primera_sesion_id)
        .execute(&pool)
        .await?;

        let segundo_resultado = iniciar_sesion_pin(
            &pool,
            establecimiento_id,
            terminal_id,
            usuario_expiracion_id,
            "1357",
        )
        .await?;

        let segundo_token = match segundo_resultado {
            ResultadoInicioSesion::Autenticado { token, .. } => token,
            otro => panic!("Se esperaba nueva sesión: {otro:?}"),
        };

        assert_ne!(
            primer_token, segundo_token,
            "La nueva sesión debe utilizar un token diferente"
        );

        let (cerrada_en, motivo_cierre): (Option<chrono::DateTime<chrono::Utc>>, Option<String>) =
            sqlx::query_as(
                "SELECT cerrada_en, motivo_cierre
             FROM sesiones_usuario WHERE id = $1",
            )
            .bind(primera_sesion_id)
            .fetch_one(&pool)
            .await?;

        assert!(cerrada_en.is_some());
        assert_eq!(motivo_cierre.as_deref(), Some("expirada"));

        let sesiones_abiertas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM sesiones_usuario
             WHERE usuario_id = $1 AND cerrada_en IS NULL",
        )
        .bind(usuario_expiracion_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(sesiones_abiertas, 1);

        // Caso 0: PIN incorrecto.
        let resultado_incorrecto =
            iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "9999").await?;

        assert!(
            matches!(resultado_incorrecto, ResultadoInicioSesion::Incorrecto),
            "Un PIN incorrecto debe rechazarse"
        );

        let intentos: i32 =
            sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(intentos, 1);

        let sesiones: i64 =
            sqlx::query_scalar("SELECT COUNT(*) FROM sesiones_usuario WHERE usuario_id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(sesiones, 0, "Un PIN incorrecto no debe generar una sesión");

        // Caso 1: PIN correcto.
        let resultado =
            iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234").await?;

        let token = match resultado {
            ResultadoInicioSesion::Autenticado { token, expira_en } => {
                assert!(expira_en > chrono::Utc::now());
                token
            }
            otro => panic!("Se esperaba autenticación correcta: {otro:?}"),
        };

        // Verificar que la base de datos almacena el hash.
        let hash_guardado: String = sqlx::query_scalar(
            "SELECT token_hash
             FROM sesiones_usuario
             WHERE usuario_id = $1
               AND cerrada_en IS NULL",
        )
        .bind(usuario_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(hash_guardado, hash_token(&token));
        assert_ne!(hash_guardado, token);

        // Caso 6: validar token autenticado.
        let sesion_validada = validar_token_sesion(&pool, &token, establecimiento_id, terminal_id)
            .await?
            .expect("El token correcto debe ser válido");

        assert_eq!(sesion_validada.establecimiento_id, establecimiento_id);
        assert_eq!(sesion_validada.usuario_id, usuario_id);
        assert_eq!(sesion_validada.terminal_id, terminal_id);
        assert_eq!(sesion_validada.rol_id, rol_id);

        // Un token incorrecto no debe autenticar.
        let token_incorrecto = super::token::generar_token();

        let resultado_incorrecto =
            validar_token_sesion(&pool, &token_incorrecto, establecimiento_id, terminal_id).await?;

        assert!(resultado_incorrecto.is_none());

        // Un token con formato inválido también debe rechazarse.
        let resultado_formato =
            validar_token_sesion(&pool, "token-invalido", establecimiento_id, terminal_id).await?;

        assert!(resultado_formato.is_none());

        // No permitir utilizar el token en otro establecimiento.
        let resultado_otro_establecimiento =
            validar_token_sesion(&pool, &token, Uuid::new_v4(), terminal_id).await?;

        assert!(resultado_otro_establecimiento.is_none());

        // No permitir utilizar el token en otra terminal.
        let resultado_otra_terminal =
            validar_token_sesion(&pool, &token, establecimiento_id, Uuid::new_v4()).await?;

        assert!(resultado_otra_terminal.is_none());

        // Caso 2: impedir otra sesión abierta.
        let segundo_intento =
            iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234").await?;

        assert!(
            matches!(segundo_intento, ResultadoInicioSesion::SesionYaAbierta),
            "Debe impedir una segunda sesión abierta"
        );

        // Caso 8: invalidación por usuario inactivo.
        sqlx::query("UPDATE usuarios SET activo = FALSE WHERE id = $1")
            .bind(usuario_id)
            .execute(&pool)
            .await?;

        let token_usuario_inactivo =
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

        assert!(
            token_usuario_inactivo.is_none(),
            "Un usuario inactivo no debe conservar acceso"
        );

        // Reactivar para comprobar que la causa del rechazo
        // fue exclusivamente el estado del usuario.
        sqlx::query("UPDATE usuarios SET activo = TRUE WHERE id = $1")
            .bind(usuario_id)
            .execute(&pool)
            .await?;

        let token_usuario_reactivado =
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

        assert!(
            token_usuario_reactivado.is_some(),
            "El token debe ser válido tras reactivar al usuario"
        );

        // Caso 9: invalidación por entidades inactivas.
        for (tabla, id) in [
            ("roles", rol_id),
            ("terminales", terminal_id),
            ("establecimientos", establecimiento_id),
        ] {
            // Los nombres de tabla son constantes definidas
            // en esta prueba, nunca entradas del usuario.
            let desactivar = format!("UPDATE {tabla} SET activo = FALSE WHERE id = $1");

            sqlx::query(&desactivar).bind(id).execute(&pool).await?;

            let resultado =
                validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

            assert!(
                resultado.is_none(),
                "El token debe rechazarse cuando {tabla} está inactivo"
            );

            let reactivar = format!("UPDATE {tabla} SET activo = TRUE WHERE id = $1");

            sqlx::query(&reactivar).bind(id).execute(&pool).await?;

            let resultado_reactivado =
                validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

            assert!(
                resultado_reactivado.is_some(),
                "El token debe volver a ser válido al reactivar {tabla}"
            );
        }

        // Caso 7: rechazar sesiones vencidas y cerradas.
        let sesion_principal_id: Uuid = sqlx::query_scalar(
            "SELECT id FROM sesiones_usuario
             WHERE usuario_id = $1
               AND cerrada_en IS NULL",
        )
        .bind(usuario_id)
        .fetch_one(&pool)
        .await?;

        // Simular expiración sin incumplir las restricciones SQL.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET iniciada_en = NOW() - INTERVAL '10 hours',
                 expira_en = NOW() - INTERVAL '2 hours'
             WHERE id = $1",
        )
        .bind(sesion_principal_id)
        .execute(&pool)
        .await?;

        let token_vencido =
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

        assert!(
            token_vencido.is_none(),
            "Un token vencido no debe autenticar"
        );

        // Restaurar una expiración futura para aislar el caso
        // de cierre explícito del caso de expiración.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET expira_en = NOW() + INTERVAL '1 hour'
             WHERE id = $1",
        )
        .bind(sesion_principal_id)
        .execute(&pool)
        .await?;

        let token_restaurado =
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

        assert!(
            token_restaurado.is_some(),
            "La sesión vigente debe volver a ser válida"
        );

        // Cerrar explícitamente la sesión.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET cerrada_en = GREATEST(NOW(), iniciada_en),
                 motivo_cierre = 'prueba_cierre'
             WHERE id = $1",
        )
        .bind(sesion_principal_id)
        .execute(&pool)
        .await?;

        let token_cerrado =
            validar_token_sesion(&pool, &token, establecimiento_id, terminal_id).await?;

        assert!(
            token_cerrado.is_none(),
            "Un token de sesión cerrada no debe autenticar"
        );

        // Caso 10: revocación permanente por usuario.
        // El caso 7 cerró la sesión anterior. Crear una nueva.
        let token_revocable =
            match iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234")
                .await?
            {
                ResultadoInicioSesion::Autenticado { token, .. } => token,
                otro => panic!("Se esperaba una nueva sesión: {otro:?}"),
            };

        let sesion_antes =
            validar_token_sesion(&pool, &token_revocable, establecimiento_id, terminal_id).await?;

        assert!(
            sesion_antes.is_some(),
            "La nueva sesión debe estar activa antes de revocarla"
        );

        let revocado = desactivar_usuario_protegido(&pool, establecimiento_id, usuario_id).await?;

        assert_eq!(
            revocado,
            ResultadoDesactivacionUsuario::Desactivado,
            "Debe desactivar al usuario y revocar sus sesiones"
        );

        let sesion_revocada: (bool, String) = sqlx::query_as(
            "SELECT cerrada_en IS NOT NULL, motivo_cierre
             FROM sesiones_usuario
             WHERE establecimiento_id = $1
               AND usuario_id = $2
               AND token_hash = $3",
        )
        .bind(establecimiento_id)
        .bind(usuario_id)
        .bind(hash_token(&token_revocable))
        .fetch_one(&pool)
        .await?;

        assert!(sesion_revocada.0, "La sesión debe quedar cerrada");
        assert_eq!(sesion_revocada.1, "usuario_desactivado");

        let token_revocado =
            validar_token_sesion(&pool, &token_revocable, establecimiento_id, terminal_id).await?;

        assert!(
            token_revocado.is_none(),
            "El token revocado debe ser rechazado"
        );

        // Reactivar el usuario no debe restaurar la sesión revocada.
        sqlx::query("UPDATE usuarios SET activo = TRUE WHERE id = $1")
            .bind(usuario_id)
            .execute(&pool)
            .await?;

        let token_tras_reactivacion =
            validar_token_sesion(&pool, &token_revocable, establecimiento_id, terminal_id).await?;

        assert!(
            token_tras_reactivacion.is_none(),
            "Reactivar el usuario no debe restaurar el token"
        );

        // Caso 11: revocación permanente por terminal.
        // El caso 10 dejó al usuario reactivado y su sesión cerrada.
        let token_terminal =
            match iniciar_sesion_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234")
                .await?
            {
                ResultadoInicioSesion::Autenticado { token, .. } => token,
                otro => panic!("Se esperaba una sesión nueva: {otro:?}"),
            };

        assert!(
            validar_token_sesion(&pool, &token_terminal, establecimiento_id, terminal_id,)
                .await?
                .is_some(),
            "El token debe ser válido antes de desactivar la terminal"
        );

        let terminal_desactivada =
            desactivar_terminal_y_revocar_sesiones(&pool, establecimiento_id, terminal_id).await?;

        assert!(terminal_desactivada);

        let sesion_terminal: (bool, Option<String>) = sqlx::query_as(
            "SELECT cerrada_en IS NOT NULL, motivo_cierre
             FROM sesiones_usuario
             WHERE token_hash = $1",
        )
        .bind(hash_token(&token_terminal))
        .fetch_one(&pool)
        .await?;

        assert!(sesion_terminal.0);
        assert_eq!(sesion_terminal.1.as_deref(), Some("terminal_desactivada"));

        assert!(
            validar_token_sesion(&pool, &token_terminal, establecimiento_id, terminal_id,)
                .await?
                .is_none(),
            "La sesión debe quedar revocada"
        );

        // Reactivar la terminal no debe restaurar el token.
        sqlx::query("UPDATE terminales SET activo = TRUE WHERE id = $1")
            .bind(terminal_id)
            .execute(&pool)
            .await?;

        assert!(
            validar_token_sesion(&pool, &token_terminal, establecimiento_id, terminal_id,)
                .await?
                .is_none(),
            "Reactivar la terminal no debe restaurar el token"
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpieza después de finalizar la tarea, incluso si hubo panic.
    let limpieza = sqlx::query("DELETE FROM sesiones_usuario WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await;

    limpieza?;

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
