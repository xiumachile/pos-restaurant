use super::permissions::{
    ResultadoRevocacion, conceder_permiso, revocar_permiso_protegido, rol_tiene_permiso,
};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn revocacion_permiso_aplica_inmediatamente_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let pool = PgPool::connect(&std::env::var("DATABASE_URL")?).await?;

    let establecimiento_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_REVOCACION_INMEDIATA")
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
        .bind("TEST_ROL_REVOCACION_INMEDIATA")
        .execute(&pool_prueba)
        .await?;

        // Conceder dos permisos independientes.
        assert!(conceder_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.cobrar",).await?);

        assert!(conceder_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.abrir",).await?);

        // Ambos permisos están disponibles inicialmente.
        assert!(rol_tiene_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.cobrar",).await?);

        assert!(rol_tiene_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.abrir",).await?);

        // Revocar únicamente caja.cobrar.
        let resultado =
            revocar_permiso_protegido(&pool_prueba, establecimiento_id, rol_id, "caja.cobrar")
                .await?;

        assert_eq!(resultado, ResultadoRevocacion::Revocado);

        // Una consulta nueva debe detectar la revocación.
        assert!(
            !rol_tiene_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.cobrar",).await?,
            "El permiso revocado no debe seguir autorizando"
        );

        // El permiso no revocado permanece disponible.
        assert!(
            rol_tiene_permiso(&pool_prueba, establecimiento_id, rol_id, "caja.abrir",).await?,
            "Los demás permisos deben permanecer intactos"
        );

        // Revocar nuevamente no debe modificar nada.
        assert_eq!(
            revocar_permiso_protegido(&pool_prueba, establecimiento_id, rol_id, "caja.cobrar",)
                .await?,
            ResultadoRevocacion::SinCambios
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar aunque falle una aserción.
    sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
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
