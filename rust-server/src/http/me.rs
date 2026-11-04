use axum::Json;
use serde::Serialize;
use uuid::Uuid;

use crate::http::bearer::SesionAutenticada;

#[derive(Serialize)]
pub(crate) struct MeResponse {
    establecimiento_id: Uuid,
    usuario_id: Uuid,
    terminal_id: Uuid,
    rol_id: Uuid,
}

/// Devuelve el contexto del usuario autenticado.
///
/// SesionAutenticada valida automáticamente el token Bearer
/// contra PostgreSQL antes de ejecutar este handler.
pub(crate) async fn me(SesionAutenticada(sesion): SesionAutenticada) -> Json<MeResponse> {
    Json(MeResponse {
        establecimiento_id: sesion.establecimiento_id,
        usuario_id: sesion.usuario_id,
        terminal_id: sesion.terminal_id,
        rol_id: sesion.rol_id,
    })
}
