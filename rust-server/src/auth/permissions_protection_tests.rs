use super::permissions::{
    ResultadoRevocacion, conceder_permiso, revocar_permiso_protegido, rol_tiene_permiso,
};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn protege_ultimo_administrador_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento = Uuid::new_v4();
    let otro_establecimiento = Uuid::new_v4();
    let rol_a = Uuid::new_v4();
    let rol_b = Uuid::new_v4();
    let usuario_a = Uuid::new_v4();
    let usuario_b = Uuid::new_v4();

    // Usamos una transacción para crear y limpiar los datos de prueba.
    // La función protegida abre su propia transacción, por lo que
    // los datos de preparación deben estar confirmados.
    let prueba = async {
        for (id, nombre) in [
            (establecimiento, "TEST_PROTECCION_ADMIN_A"),
            (otro_establecimiento, "TEST_PROTECCION_ADMIN_B"),
        ] {
            sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
                .bind(id)
                .bind(nombre)
                .execute(&pool)
                .await?;
        }

        for (id, establecimiento_id) in [(rol_a, establecimiento), (rol_b, establecimiento)] {
            sqlx::query(
                "INSERT INTO roles (id, establecimiento_id, nombre)
                 VALUES ($1, $2, $3)",
            )
            .bind(id)
            .bind(establecimiento_id)
            .bind(format!("TEST_ADMIN_{id}"))
            .execute(&pool)
            .await?;
        }

        for (id, rol_id, nombre) in [
            (usuario_a, rol_a, "TEST_USUARIO_ADMIN_A"),
            (usuario_b, rol_b, "TEST_USUARIO_ADMIN_B"),
        ] {
            sqlx::query(
                "INSERT INTO usuarios
                 (id, establecimiento_id, rol_id, nombre, pin_hash)
                 VALUES ($1, $2, $3, $4, $5)",
            )
            .bind(id)
            .bind(establecimiento)
            .bind(rol_id)
            .bind(nombre)
            .bind("TEST_HASH_NO_LOGIN")
            .execute(&pool)
            .await?;
        }

        assert!(conceder_permiso(&pool, establecimiento, rol_a, "roles.gestionar",).await?);

        // Caso 1: único administrador.
        assert_eq!(
            revocar_permiso_protegido(&pool, establecimiento, rol_a, "roles.gestionar",).await?,
            ResultadoRevocacion::UltimoAdministrador
        );

        assert!(rol_tiene_permiso(&pool, establecimiento, rol_a, "roles.gestionar",).await?);

        // Caso 2: otro administrador activo.
        assert!(conceder_permiso(&pool, establecimiento, rol_b, "roles.gestionar",).await?);

        assert_eq!(
            revocar_permiso_protegido(&pool, establecimiento, rol_a, "roles.gestionar",).await?,
            ResultadoRevocacion::Revocado
        );

        // Caso 3: el administrador alternativo tiene usuario inactivo.
        assert!(conceder_permiso(&pool, establecimiento, rol_a, "roles.gestionar",).await?);

        sqlx::query("UPDATE usuarios SET activo = FALSE WHERE id = $1")
            .bind(usuario_b)
            .execute(&pool)
            .await?;

        assert_eq!(
            revocar_permiso_protegido(&pool, establecimiento, rol_a, "roles.gestionar",).await?,
            ResultadoRevocacion::UltimoAdministrador
        );

        // Caso 4: no revocar asignaciones de otro establecimiento.
        assert_eq!(
            revocar_permiso_protegido(&pool, otro_establecimiento, rol_a, "roles.gestionar",)
                .await?,
            ResultadoRevocacion::SinCambios
        );

        assert!(rol_tiene_permiso(&pool, establecimiento, rol_a, "roles.gestionar",).await?);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpieza incluso si alguna comprobación devuelve error.
    for id in [establecimiento, otro_establecimiento] {
        sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
            .bind(id)
            .execute(&pool)
            .await?;

        sqlx::query("DELETE FROM usuarios WHERE establecimiento_id = $1")
            .bind(id)
            .execute(&pool)
            .await?;

        sqlx::query("DELETE FROM roles WHERE establecimiento_id = $1")
            .bind(id)
            .execute(&pool)
            .await?;

        sqlx::query("DELETE FROM establecimientos WHERE id = $1")
            .bind(id)
            .execute(&pool)
            .await?;
    }

    prueba
}
