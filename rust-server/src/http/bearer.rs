use axum::http::{HeaderMap, StatusCode};

/// Extrae un token Bearer del encabezado Authorization.
///
/// No valida todavía la sesión contra PostgreSQL.
pub(crate) fn extraer_token_bearer(headers: &HeaderMap) -> Result<&str, StatusCode> {
    let authorization = headers
        .get(axum::http::header::AUTHORIZATION)
        .ok_or(StatusCode::UNAUTHORIZED)?
        .to_str()
        .map_err(|_| StatusCode::UNAUTHORIZED)?;

    let token = authorization
        .strip_prefix("Bearer ")
        .ok_or(StatusCode::UNAUTHORIZED)?;

    if token.len() != 64 || !token.bytes().all(|b| b.is_ascii_hexdigit()) {
        return Err(StatusCode::UNAUTHORIZED);
    }

    Ok(token)
}

#[cfg(test)]
mod tests {
    use super::*;
    use axum::http::header::AUTHORIZATION;

    #[test]
    fn acepta_token_bearer_con_formato_valido() {
        let mut headers = HeaderMap::new();
        let token = "a".repeat(64);

        headers.insert(AUTHORIZATION, format!("Bearer {token}").parse().unwrap());

        assert_eq!(extraer_token_bearer(&headers), Ok(token.as_str()));
    }

    #[test]
    fn rechaza_solicitud_sin_authorization() {
        let headers = HeaderMap::new();

        assert_eq!(
            extraer_token_bearer(&headers),
            Err(StatusCode::UNAUTHORIZED)
        );
    }

    #[test]
    fn rechaza_token_con_formato_invalido() {
        let mut headers = HeaderMap::new();

        headers.insert(AUTHORIZATION, "Bearer token-invalido".parse().unwrap());

        assert_eq!(
            extraer_token_bearer(&headers),
            Err(StatusCode::UNAUTHORIZED)
        );
    }

    #[test]
    fn rechaza_esquema_distinto_de_bearer() {
        let mut headers = HeaderMap::new();

        headers.insert(
            AUTHORIZATION,
            format!("Basic {}", "a".repeat(64)).parse().unwrap(),
        );

        assert_eq!(
            extraer_token_bearer(&headers),
            Err(StatusCode::UNAUTHORIZED)
        );
    }
}

/// Sesión autenticada obtenida desde Authorization: Bearer.
#[derive(Debug, Clone)]
pub(crate) struct SesionAutenticada(pub(crate) crate::auth::session::SesionValidada);

impl axum::extract::FromRequestParts<crate::AppState> for SesionAutenticada {
    type Rejection = axum::response::Response;

    async fn from_request_parts(
        parts: &mut axum::http::request::Parts,
        state: &crate::AppState,
    ) -> Result<Self, Self::Rejection> {
        use axum::response::IntoResponse;

        let token = extraer_token_bearer(&parts.headers).map_err(|_| {
            crate::http::auth::respuesta_error(StatusCode::UNAUTHORIZED, "token_invalido")
        })?;

        let sesion = crate::auth::session::validar_token_bearer(&state.db, token)
            .await
            .map_err(|_| {
                crate::http::auth::respuesta_error(
                    StatusCode::SERVICE_UNAVAILABLE,
                    "servicio_no_disponible",
                )
            })?
            .ok_or_else(|| {
                crate::http::auth::respuesta_error(StatusCode::UNAUTHORIZED, "token_invalido")
            })?;

        Ok(Self(sesion))
    }
}
