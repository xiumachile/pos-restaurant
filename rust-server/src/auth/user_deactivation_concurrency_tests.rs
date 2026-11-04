use super::permissions::conceder_permiso;
use super::session::{ResultadoDesactivacionUsuario, desactivar_usuario_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn desactivacion_concurrente_conserva_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_CONCURRENCIA_ADMIN")
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
        .bind("TEST_ROL_CONCURRENCIA")
        .execute(&pool_prueba)
        .await?;

        for (id, nombre) in [
            (usuario_a, "TEST_CONCURRENCIA_A"),
            (usuario_b, "TEST_CONCURRENCIA_B"),
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
            conceder_permiso(&pool_prueba, establecimiento_id, rol_id, "roles.gestionar",).await?
        );

        // Lanzar ambas desactivaciones simultáneamente.
        let (resultado_a, resultado_b) = tokio::join!(
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a,),
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, usuario_b,),
        );

        let resultado_a = resultado_a?;
        let resultado_b = resultado_b?;

        let resultados = [resultado_a, resultado_b];

        assert_eq!(
            resultados
                .iter()
                .filter(|&&r| r == ResultadoDesactivacionUsuario::Desactivado)
                .count(),
            1,
            "Exactamente una desactivación debe tener éxito"
        );

        assert_eq!(
            resultados
                .iter()
                .filter(|&&r| r == ResultadoDesactivacionUsuario::UltimoAdministrador)
                .count(),
            1,
            "La segunda desactivación debe proteger al último administrador"
        );

        let administradores_activos: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM usuarios
             WHERE establecimiento_id = $1
               AND rol_id = $2
               AND activo = TRUE",
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .fetch_one(&pool_prueba)
        .await?;

        assert_eq!(
            administradores_activos, 1,
            "Debe permanecer exactamente un administrador activo"
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpieza incluso si falla una aserción de la tarea.
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
