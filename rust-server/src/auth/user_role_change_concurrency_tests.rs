use super::permissions::conceder_permiso;
use super::session::{ResultadoDesactivacionUsuario, desactivar_usuario_protegido};
use super::user_roles::{ResultadoCambioRolUsuario, cambiar_rol_usuario_protegido};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn concurrencia_cambio_rol_y_desactivacion_conserva_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_admin = Uuid::new_v4();
    let rol_cajero = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_CONCURRENCIA_CAMBIO_ROL")
        .execute(&pool)
        .await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        for (id, nombre) in [
            (rol_admin, "TEST_CONCURRENCIA_ADMIN"),
            (rol_cajero, "TEST_CONCURRENCIA_CAJERO"),
        ] {
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

        for (id, nombre) in [
            (usuario_a, "TEST_CAMBIO_CONCURRENTE_A"),
            (usuario_b, "TEST_CAMBIO_CONCURRENTE_B"),
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

        let (resultado_cambio, resultado_desactivacion) = tokio::join!(
            cambiar_rol_usuario_protegido(&pool_prueba, establecimiento_id, usuario_a, rol_cajero,),
            desactivar_usuario_protegido(&pool_prueba, establecimiento_id, usuario_b,),
        );

        let resultado_cambio = resultado_cambio?;
        let resultado_desactivacion = resultado_desactivacion?;

        let exitosas = i32::from(resultado_cambio == ResultadoCambioRolUsuario::Cambiado)
            + i32::from(resultado_desactivacion == ResultadoDesactivacionUsuario::Desactivado);

        let protegidas =
            i32::from(resultado_cambio == ResultadoCambioRolUsuario::UltimoAdministrador)
                + i32::from(
                    resultado_desactivacion == ResultadoDesactivacionUsuario::UltimoAdministrador,
                );

        assert_eq!(exitosas, 1, "Exactamente una operación debe completarse");

        assert_eq!(
            protegidas, 1,
            "La otra operación debe proteger al último administrador"
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
            "Debe permanecer exactamente un administrador activo"
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar incluso si falla una aserción.
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
