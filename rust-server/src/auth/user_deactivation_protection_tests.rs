use super::permissions::conceder_permiso;
use super::session::{ResultadoDesactivacionUsuario, desactivar_usuario_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn protege_desactivacion_ultimo_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_DESACTIVACION_ADMIN")
        .execute(&pool)
        .await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(rol_id)
        .bind(establecimiento_id)
        .bind("TEST_ROL_ADMIN")
        .execute(&pool_prueba)
        .await?;

        for (id, nombre) in [(usuario_a, "TEST_ADMIN_A"), (usuario_b, "TEST_ADMIN_B")] {
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
            conceder_permiso(&pool_prueba, establecimiento_id, rol_id, "roles.gestionar",).await?
        );

        // Ambos usuarios son administradores: A puede desactivarse.
        assert_eq!(
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a,).await?,
            ResultadoDesactivacionUsuario::Desactivado
        );

        let activo_a: bool = sqlx::query_scalar("SELECT activo FROM usuarios WHERE id = $1")
            .bind(usuario_a)
            .fetch_one(&pool_prueba)
            .await?;

        assert!(!activo_a);

        // B es ahora el único administrador activo.
        assert_eq!(
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, usuario_b,).await?,
            ResultadoDesactivacionUsuario::UltimoAdministrador
        );

        let activo_b: bool = sqlx::query_scalar("SELECT activo FROM usuarios WHERE id = $1")
            .bind(usuario_b)
            .fetch_one(&pool_prueba)
            .await?;

        assert!(activo_b);

        // Un usuario inexistente no debe modificarse.
        assert_eq!(
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, Uuid::new_v4(),).await?,
            ResultadoDesactivacionUsuario::NoEncontrado
        );

        // Un establecimiento ajeno no debe modificar este usuario.
        assert_eq!(
            desactivar_usuario_protegido(&pool_prueba, Uuid::new_v4(), usuario_b,).await?,
            ResultadoDesactivacionUsuario::NoEncontrado
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar incluso si las aserciones de la tarea fallan.
    sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
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

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    prueba??;

    Ok(())
}
