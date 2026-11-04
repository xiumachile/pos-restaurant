use super::permissions::conceder_permiso;
use super::roles::{ResultadoDesactivacionRol, desactivar_rol_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn protege_desactivacion_ultimo_rol_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_a = Uuid::new_v4();
    let rol_b = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_DESACTIVACION_ROL_ADMIN")
        .execute(&pool)
        .await?;

    let terminal_id = Uuid::new_v4();
    let sesion_a = Uuid::new_v4();
    let sesion_b = Uuid::new_v4();

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        for (id, nombre) in [(rol_a, "TEST_ROL_ADMIN_A"), (rol_b, "TEST_ROL_ADMIN_B")] {
            sqlx::query(
                "INSERT INTO roles (id, establecimiento_id, nombre)
                 VALUES ($1, $2, $3)",
            )
            .bind(id)
            .bind(establecimiento_id)
            .bind(nombre)
            .execute(&pool_prueba)
            .await?;
        }

        for (id, rol_id, nombre) in [
            (usuario_a, rol_a, "TEST_USUARIO_ROL_A"),
            (usuario_b, rol_b, "TEST_USUARIO_ROL_B"),
        ] {
            sqlx::query(
                "INSERT INTO usuarios
                 (id, establecimiento_id, rol_id, nombre, pin_hash)
                 VALUES ($1, $2, $3, $4, $5)",
            )
            .bind(id)
            .bind(establecimiento_id)
            .bind(rol_id)
            .bind(nombre)
            .bind("hash_de_prueba_no_utilizado")
            .execute(&pool_prueba)
            .await?;
        }

        assert!(
            conceder_permiso(&pool_prueba, establecimiento_id, rol_a, "roles.gestionar",).await?
        );

        // Crear una terminal y sesiones activas para ambos usuarios.
        sqlx::query(
            "INSERT INTO terminales
             (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_TERMINAL_ROLES")
        .execute(&pool_prueba)
        .await?;

        for (sesion_id, usuario_id) in [(sesion_a, usuario_a), (sesion_b, usuario_b)] {
            sqlx::query(
                "INSERT INTO sesiones_usuario
                 (id, establecimiento_id, usuario_id, terminal_id,
                  token_hash, expira_en)
                 VALUES ($1, $2, $3, $4, $5,
                         NOW() + INTERVAL '1 hour')",
            )
            .bind(sesion_id)
            .bind(establecimiento_id)
            .bind(usuario_id)
            .bind(terminal_id)
            .bind(format!("TEST_ROL_SESION_{}", sesion_id))
            .execute(&pool_prueba)
            .await?;
        }

        // Solo el rol A es administrador.
        assert_eq!(
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_a,).await?,
            ResultadoDesactivacionRol::UltimoAdministrador
        );

        let rol_a_activo: bool = sqlx::query_scalar("SELECT activo FROM roles WHERE id = $1")
            .bind(rol_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert!(rol_a_activo);

        // Una desactivación rechazada no debe cerrar sesiones.
        let cierre_rechazado: Option<chrono::DateTime<chrono::Utc>> = sqlx::query_scalar(
            "SELECT cerrada_en
                 FROM sesiones_usuario WHERE id = $1",
        )
        .bind(sesion_a)
        .fetch_one(&pool_prueba)
        .await?;

        assert!(
            cierre_rechazado.is_none(),
            "La protección del último administrador no debe cerrar sesiones"
        );

        // Ahora el rol B también puede administrar.
        assert!(
            conceder_permiso(&pool_prueba, establecimiento_id, rol_b, "roles.gestionar",).await?
        );

        assert_eq!(
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_a,).await?,
            ResultadoDesactivacionRol::Desactivado
        );

        let rol_a_activo: bool = sqlx::query_scalar("SELECT activo FROM roles WHERE id = $1")
            .bind(rol_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert!(!rol_a_activo);

        // La sesión del rol desactivado debe quedar cerrada.
        let (cierre_a, motivo_a): (Option<chrono::DateTime<chrono::Utc>>, Option<String>) =
            sqlx::query_as(
                "SELECT cerrada_en, motivo_cierre
             FROM sesiones_usuario WHERE id = $1",
            )
            .bind(sesion_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert!(cierre_a.is_some());
        assert_eq!(motivo_a.as_deref(), Some("rol_desactivado"));

        // La sesión del otro rol debe continuar abierta.
        let cierre_b: Option<chrono::DateTime<chrono::Utc>> = sqlx::query_scalar(
            "SELECT cerrada_en
                 FROM sesiones_usuario WHERE id = $1",
        )
        .bind(sesion_b)
        .fetch_one(&pool_prueba)
        .await?;

        assert!(
            cierre_b.is_none(),
            "No deben cerrarse sesiones de otros roles"
        );

        // Repetir la desactivación no debe modificar el cierre.
        assert_eq!(
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_a,).await?,
            ResultadoDesactivacionRol::Desactivado
        );

        let cierre_repetido: Option<chrono::DateTime<chrono::Utc>> = sqlx::query_scalar(
            "SELECT cerrada_en
                 FROM sesiones_usuario WHERE id = $1",
        )
        .bind(sesion_a)
        .fetch_one(&pool_prueba)
        .await?;

        assert_eq!(cierre_repetido, cierre_a);

        // El rol B es ahora el último administrador.
        assert_eq!(
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_b,).await?,
            ResultadoDesactivacionRol::UltimoAdministrador
        );

        // Un establecimiento distinto no puede modificar el rol.
        assert_eq!(
            desactivar_rol_protegido(&pool_prueba, Uuid::new_v4(), rol_b,).await?,
            ResultadoDesactivacionRol::NoEncontrado
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpieza, incluso si la tarea de prueba falla.
    sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

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
