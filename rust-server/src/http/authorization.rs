use axum::{http::StatusCode, response::Response};

use crate::{
    AppState,
    http::{auth::respuesta_error, bearer::SesionAutenticada},
};

/// Verifica que la sesión autenticada tenga el permiso solicitado.
///
/// La sesión ya debe haber sido validada mediante Bearer.
/// Los permisos se consultan en PostgreSQL en cada llamada.
///
/// - Ok(())         => permiso concedido.
/// - 403 Forbidden  => permiso no asignado.
/// - 503            => error de PostgreSQL.
pub(crate) async fn exigir_permiso(
    state: &AppState,
    sesion: &SesionAutenticada,
    codigo_permiso: &str,
) -> Result<(), Response> {
    let autorizado = crate::auth::permissions::rol_tiene_permiso(
        &state.db,
        sesion.0.establecimiento_id,
        sesion.0.rol_id,
        codigo_permiso,
    )
    .await
    .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    if !autorizado {
        return Err(respuesta_error(StatusCode::FORBIDDEN, "permiso_denegado"));
    }

    Ok(())
}
