use sqlx::PgPool;
use uuid::Uuid;

/// Configuración de una mesa física.
///
/// No contiene estado de ocupación ni geometría del plano.
#[derive(Debug, Clone, sqlx::FromRow)]
pub struct Mesa {
    pub id: Uuid,
    pub establecimiento_id: Uuid,
    pub zona_id: Option<Uuid>,
    pub numero: String,
    pub capacidad: i32,
    pub activo: bool,
    pub version_configuracion: i32,
}

/// Obtiene las mesas no eliminadas de un establecimiento.
///
/// El establecimiento_id debe provenir de la sesión autenticada.
pub async fn listar_mesas(
    pool: &PgPool,
    establecimiento_id: Uuid,
) -> Result<Vec<Mesa>, sqlx::Error> {
    sqlx::query_as::<_, Mesa>(
        r#"
        SELECT
            id,
            establecimiento_id,
            zona_id,
            numero,
            capacidad,
            activo,
            version_configuracion
        FROM mesas
        WHERE establecimiento_id = $1
          AND deleted_at IS NULL
        ORDER BY numero ASC, id ASC
        "#,
    )
    .bind(establecimiento_id)
    .fetch_all(pool)
    .await
}
