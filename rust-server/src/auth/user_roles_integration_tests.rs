use super::permissions::conceder_permiso;
use super::user_roles::{ResultadoCambioRolUsuario, cambiar_rol_usuario_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn protege_cambio_rol_usuario_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let otro_establecimiento_id = Uuid::new_v4();
    let rol_admin = Uuid::new_v4();
    let rol_cajero = Uuid::new_v4();
    let rol_externo = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    for (id, nombre) in [
        (establecimiento_id, "TEST_CAMBIO_ROL_PRINCIPAL"),
        (otro_establecimiento_id, "TEST_CAMBIO_ROL_EXTERNO"),
    ] {
        sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
            .bind(id)
            .bind(nombre)
            .execute(&pool)
            .await?;
    }

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        for (id, establecimiento, nombre) in [
            (rol_admin, establecimiento_id, "TEST_CAMBIO_ADMIN"),
            (rol_cajero, establecimiento_id, "TEST_CAMBIO_CAJERO"),
            (rol_externo, otro_establecimiento_id, "TEST_CAMBIO_EXTERNO"),
        ] {
            sqlx::query(
                "INSERT INTO roles (id, establecimiento_id, nombre)
                 VALUES ($1, $2, $3)",
            )
            .bind(id)
            .bind(establecimiento)
            .bind(nombre)
            .execute(&pool_prueba)
            .await?;
        }

        for (id, nombre) in [
            (usuario_a, "TEST_CAMBIO_USUARIO_A"),
            (usuario_b, "TEST_CAMBIO_USUARIO_B"),
        ] {
            sqlx::query(
                "INSERT INTO usuarios
                 (id, establecimiento_id, rol_id, nombre, pin_hash)
                 VALUES ($1, $2, $3, $4, $5)",
            )
            .bind(id)
            .bind(establecimiento_id)
            .bind(rol_admin)
            .bind(nombre)
            .bind("hash_de_prueba_no_utilizado")
            .execute(&pool_prueba)
            .await?;
        }

        assert!(
            conceder_permiso(
                &pool_prueba,
                establecimiento_id,
                rol_admin,
                "roles.gestionar",
            )
            .await?
        );

        // Inicialmente solo A está activo.
        sqlx::query(
            "UPDATE usuarios SET activo = FALSE
             WHERE establecimiento_id = $1 AND id = $2",
        )
        .bind(establecimiento_id)
        .bind(usuario_b)
        .execute(&pool_prueba)
        .await?;

        // 1. No permitir eliminar el último administrador.
        let resultado =
            cambiar_rol_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a, rol_cajero)
                .await?;

        assert_eq!(resultado, ResultadoCambioRolUsuario::UltimoAdministrador);

        let rol_actual: Uuid = sqlx::query_scalar("SELECT rol_id FROM usuarios WHERE id = $1")
            .bind(usuario_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert_eq!(rol_actual, rol_admin);

        // 2. Rechazar roles de otro establecimiento.
        let resultado =
            cambiar_rol_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a, rol_externo)
                .await?;

        assert_eq!(resultado, ResultadoCambioRolUsuario::RolDestinoNoDisponible);

        // 3. Activar otro administrador.
        sqlx::query(
            "UPDATE usuarios SET activo = TRUE
             WHERE establecimiento_id = $1 AND id = $2",
        )
        .bind(establecimiento_id)
        .bind(usuario_b)
        .execute(&pool_prueba)
        .await?;

        // 4. Crear una terminal y una sesión activa para A.
        let terminal_id = Uuid::new_v4();
        let sesion_id = Uuid::new_v4();
        let token_hash = format!("TEST_CAMBIO_TOKEN_{}", Uuid::new_v4());

        sqlx::query(
            "INSERT INTO terminales
             (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_TERMINAL_CAMBIO_ROL")
        .execute(&pool_prueba)
        .await?;

        sqlx::query(
            r#"
            INSERT INTO sesiones_usuario
                (id, establecimiento_id, usuario_id, terminal_id,
                 token_hash, iniciada_en, expira_en)
            VALUES
                ($1, $2, $3, $4, $5, NOW(),
                 NOW() + INTERVAL '1 day')
            "#,
        )
        .bind(sesion_id)
        .bind(establecimiento_id)
        .bind(usuario_a)
        .bind(terminal_id)
        .bind(token_hash)
        .execute(&pool_prueba)
        .await?;

        // 5. Ahora el cambio debe estar permitido.
        let resultado =
            cambiar_rol_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a, rol_cajero)
                .await?;

        assert_eq!(resultado, ResultadoCambioRolUsuario::Cambiado);

        let nuevo_rol: Uuid = sqlx::query_scalar("SELECT rol_id FROM usuarios WHERE id = $1")
            .bind(usuario_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert_eq!(nuevo_rol, rol_cajero);

        let sesion_cerrada: bool = sqlx::query_scalar(
            r#"
            SELECT cerrada_en IS NOT NULL
                   AND motivo_cierre = 'rol_cambiado'
            FROM sesiones_usuario
            WHERE id = $1
            "#,
        )
        .bind(sesion_id)
        .fetch_one(&pool_prueba)
        .await?;

        assert!(sesion_cerrada, "La sesión anterior debe revocarse");

        // 6. Repetir el cambio no debe modificar nada.
        let resultado =
            cambiar_rol_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a, rol_cajero)
                .await?;

        assert_eq!(resultado, ResultadoCambioRolUsuario::SinCambios);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar registros temporales aunque falle una aserción.
    sqlx::query("DELETE FROM sesiones_usuario WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM usuarios WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    for id in [establecimiento_id, otro_establecimiento_id] {
        sqlx::query("DELETE FROM roles WHERE establecimiento_id = $1")
            .bind(id)
            .execute(&pool)
            .await?;

        sqlx::query("DELETE FROM establecimientos WHERE id = $1")
            .bind(id)
            .execute(&pool)
            .await?;
    }

    prueba??;

    Ok(())
}
