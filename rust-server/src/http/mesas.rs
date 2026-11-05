use axum::{Json, extract::State, http::StatusCode, response::Response};
use serde::Serialize;
use uuid::Uuid;

use crate::{
    AppState,
    http::{auth::respuesta_error, authorization::exigir_permiso, bearer::SesionAutenticada},
    mesas::repository,
};

/// Configuración pública de una mesa física.
///
/// La ocupación y la geometría se administran por separado.
#[derive(Serialize)]
pub(crate) struct MesaResponse {
    id: Uuid,
    zona_id: Option<Uuid>,
    numero: String,
    capacidad: i32,
    activo: bool,
    version_configuracion: i32,
}

/// Lista las mesas del establecimiento autenticado.
///
/// Requiere el permiso mesas.ver.
pub(crate) async fn listar(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
) -> Result<Json<Vec<MesaResponse>>, Response> {
    exigir_permiso(&state, &sesion, "mesas.ver").await?;

    let mesas = repository::listar_mesas(&state.db, sesion.0.establecimiento_id)
        .await
        .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    let respuesta = mesas
        .into_iter()
        .map(|mesa| MesaResponse {
            id: mesa.id,
            zona_id: mesa.zona_id,
            numero: mesa.numero,
            capacidad: mesa.capacidad,
            activo: mesa.activo,
            version_configuracion: mesa.version_configuracion,
        })
        .collect();

    Ok(Json(respuesta))
}
