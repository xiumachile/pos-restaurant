use axum::{Json, extract::State, response::Response};
use serde_json::{Value, json};

use crate::{
    AppState,
    http::{authorization::exigir_permiso, bearer::SesionAutenticada},
};

/// Endpoint disponible solamente durante las pruebas.
pub(super) async fn endpoint_protegido_prueba(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
) -> Result<Json<Value>, Response> {
    exigir_permiso(&state, &sesion, "pos.rbac.prueba").await?;

    Ok(Json(json!({
        "status": "autorizado"
    })))
}
