use axum::{Json, extract::State, http::StatusCode, response::Response};
use serde::Serialize;

use crate::{
    AppState,
    http::{auth::respuesta_error, bearer::SesionAutenticada},
};

#[derive(Serialize)]
pub(crate) struct LogoutResponse {
    status: &'static str,
}

/// Cierra exclusivamente la sesión autenticada por Bearer.
pub(crate) async fn logout(
    State(state): State<AppState>,
    SesionAutenticada(sesion): SesionAutenticada,
) -> Result<Json<LogoutResponse>, Response> {
    let cerrada = crate::auth::session::cerrar_sesion_usuario(
        &state.db,
        sesion.establecimiento_id,
        sesion.sesion_id,
    )
    .await
    .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    if !cerrada {
        return Err(respuesta_error(StatusCode::UNAUTHORIZED, "token_invalido"));
    }

    Ok(Json(LogoutResponse {
        status: "sesion_cerrada",
    }))
}
