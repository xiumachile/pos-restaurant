use axum::{
    Json,
    extract::{Path, State},
    http::StatusCode,
    response::Response,
};
use serde::{Deserialize, Serialize};
use uuid::Uuid;

use crate::{
    AppState,
    auth::permissions::ResultadoRevocacion,
    http::{auth::respuesta_error, authorization::exigir_permiso, bearer::SesionAutenticada},
};

#[derive(Deserialize)]
pub(crate) struct PermisoRequest {
    codigo: String,
}

#[derive(Serialize)]
pub(crate) struct PermisoResponse {
    status: &'static str,
    modificado: bool,
}

/// Concede un permiso a un rol del establecimiento autenticado.
pub(crate) async fn conceder(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
    Path(rol_id): Path<Uuid>,
    Json(payload): Json<PermisoRequest>,
) -> Result<Json<PermisoResponse>, Response> {
    exigir_permiso(&state, &sesion, "roles.gestionar").await?;

    let codigo = payload.codigo.trim();

    if codigo.is_empty() || codigo.len() > 150 {
        return Err(respuesta_error(
            StatusCode::BAD_REQUEST,
            "codigo_permiso_invalido",
        ));
    }

    let modificado = crate::auth::permissions::conceder_permiso(
        &state.db,
        sesion.0.establecimiento_id,
        rol_id,
        codigo,
    )
    .await
    .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    Ok(Json(PermisoResponse {
        status: "procesado",
        modificado,
    }))
}

/// Revoca un permiso de un rol del establecimiento autenticado.
pub(crate) async fn revocar(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
    Path(rol_id): Path<Uuid>,
    Json(payload): Json<PermisoRequest>,
) -> Result<Json<PermisoResponse>, Response> {
    exigir_permiso(&state, &sesion, "roles.gestionar").await?;

    let codigo = payload.codigo.trim();

    if codigo.is_empty() || codigo.len() > 150 {
        return Err(respuesta_error(
            StatusCode::BAD_REQUEST,
            "codigo_permiso_invalido",
        ));
    }

    let resultado = crate::auth::permissions::revocar_permiso_protegido(
        &state.db,
        sesion.0.establecimiento_id,
        rol_id,
        codigo,
    )
    .await
    .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    let modificado = match resultado {
        ResultadoRevocacion::Revocado => true,
        ResultadoRevocacion::SinCambios => false,
        ResultadoRevocacion::UltimoAdministrador => {
            return Err(respuesta_error(
                StatusCode::CONFLICT,
                "ultimo_administrador",
            ));
        }
    };

    Ok(Json(PermisoResponse {
        status: "procesado",
        modificado,
    }))
}
