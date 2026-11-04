use super::permissions::conceder_permiso;
use super::roles::{ResultadoDesactivacionRol, desactivar_rol_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn desactivacion_concurrente_roles_conserva_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_a = Uuid::new_v4();
    let rol_b = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_CONCURRENCIA_ROLES")
        .execute(&pool)
        .await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        for (rol_id, nombre) in [
            (rol_a, "TEST_ROL_CONCURRENCIA_A"),
            (rol_b, "TEST_ROL_CONCURRENCIA_B"),
        ] {
            sqlx::query(
                "INSERT INTO roles (id, establecimiento_id, nombre)
                 VALUES ($1, $2, $3)",
            )
            .bind(rol_id)
            .bind(establecimiento_id)
            .bind(nombre)
            .execute(&pool_prueba)
            .await?;
        }

        for (usuario_id, rol_id, nombre) in [
            (usuario_a, rol_a, "TEST_ADMIN_ROL_A"),
            (usuario_b, rol_b, "TEST_ADMIN_ROL_B"),
        ] {
            sqlx::query(
                "INSERT INTO usuarios
                 (id, establecimiento_id, rol_id, nombre, pin_hash)
                 VALUES ($1, $2, $3, $4, $5)",
            )
            .bind(usuario_id)
            .bind(establecimiento_id)
            .bind(rol_id)
            .bind(nombre)
            .bind("hash_de_prueba_no_utilizado")
            .execute(&pool_prueba)
            .await?;
        }

        for rol_id in [rol_a, rol_b] {
            assert!(
                conceder_permiso(&pool_prueba, establecimiento_id, rol_id, "roles.gestionar",)
                    .await?
            );
        }

        let (resultado_a, resultado_b) = tokio::join!(
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_a,),
            desactivar_rol_protegido(&pool_prueba, establecimiento_id, rol_b,),
        );

        let resultados = [resultado_a?, resultado_b?];

        assert_eq!(
            resultados
                .iter()
                .filter(|&&r| r == ResultadoDesactivacionRol::Desactivado)
                .count(),
            1,
            "Exactamente un rol debe desactivarse"
        );

        assert_eq!(
            resultados
                .iter()
                .filter(|&&r| r == ResultadoDesactivacionRol::UltimoAdministrador)
                .count(),
            1,
            "Debe protegerse el último rol administrador"
        );

        let administradores_activos: i64 = sqlx::query_scalar(
            r#"
            SELECT COUNT(*)
            FROM usuarios u
            INNER JOIN roles r
                ON r.establecimiento_id = u.establecimiento_id
               AND r.id = u.rol_id
            INNER JOIN rol_permisos rp
                ON rp.establecimiento_id = r.establecimiento_id
               AND rp.rol_id = r.id
            INNER JOIN permisos p
                ON p.id = rp.permiso_id
            WHERE u.establecimiento_id = $1
              AND u.activo = TRUE
              AND r.activo = TRUE
              AND p.codigo = 'roles.gestionar'
            "#,
        )
        .bind(establecimiento_id)
        .fetch_one(&pool_prueba)
        .await?;

        assert_eq!(
            administradores_activos, 1,
            "Debe permanecer un administrador activo"
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar los datos aunque falle una aserción.
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
