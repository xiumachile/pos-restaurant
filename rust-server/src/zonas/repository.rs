use sqlx::PgPool;
use uuid::Uuid;

/// Zona de comedor perteneciente a un establecimiento.
#[derive(Debug, Clone, sqlx::FromRow)]
pub struct Zona {
    pub id: Uuid,
    pub establecimiento_id: Uuid,
    pub codigo: String,
    pub nombre_traducciones: serde_json::Value,
    pub color: String,
    pub nivel_piso: i32,
    pub orden: i32,
    pub activo: bool,
    pub version: i32,
}

/// Obtiene las zonas no eliminadas de un establecimiento.
///
/// El establecimiento_id debe provenir de la sesión autenticada.
pub async fn listar_zonas(
    pool: &PgPool,
    establecimiento_id: Uuid,
) -> Result<Vec<Zona>, sqlx::Error> {
    sqlx::query_as::<_, Zona>(
        r#"
        SELECT
            id,
            establecimiento_id,
            codigo,
            nombre_traducciones,
            color,
            nivel_piso,
            orden,
            activo,
            version
        FROM zonas
        WHERE establecimiento_id = $1
          AND deleted_at IS NULL
        ORDER BY orden ASC, codigo ASC
        "#,
    )
    .bind(establecimiento_id)
    .fetch_all(pool)
    .await
}

/// Datos validados para insertar una nueva zona.
pub struct NuevaZona {
    pub codigo: String,
    pub nombre_traducciones: serde_json::Value,
    pub color: String,
    pub nivel_piso: i32,
    pub orden: i32,
    pub activo: bool,
}

/// Crea una zona asociada al establecimiento autenticado.
///
/// La unicidad del código por establecimiento se garantiza
/// mediante la restricción UNIQUE de PostgreSQL.
pub async fn crear_zona(
    pool: &PgPool,
    establecimiento_id: Uuid,
    nueva: NuevaZona,
) -> Result<Zona, sqlx::Error> {
    let id = Uuid::new_v4();

    sqlx::query_as::<_, Zona>(
        r#"
        INSERT INTO zonas (
            id,
            establecimiento_id,
            codigo,
            nombre_traducciones,
            color,
            nivel_piso,
            orden,
            activo
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
        RETURNING
            id,
            establecimiento_id,
            codigo,
            nombre_traducciones,
            color,
            nivel_piso,
            orden,
            activo,
            version
        "#,
    )
    .bind(id)
    .bind(establecimiento_id)
    .bind(nueva.codigo)
    .bind(nueva.nombre_traducciones)
    .bind(nueva.color)
    .bind(nueva.nivel_piso)
    .bind(nueva.orden)
    .bind(nueva.activo)
    .fetch_one(pool)
    .await
}
