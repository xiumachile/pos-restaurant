use axum::{
    Json,
    http::StatusCode,
    response::{IntoResponse, Response},
};
use serde::Serialize;

#[derive(Serialize)]
struct ErrorResponse {
    error: &'static str,
}

pub(crate) fn respuesta_error(status: StatusCode, codigo: &'static str) -> Response {
    (status, Json(ErrorResponse { error: codigo })).into_response()
}

pub(crate) fn pin_tiene_formato_valido(pin: &str) -> bool {
    pin.len() == 4 && pin.bytes().all(|b| b.is_ascii_digit())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn acepta_pin_de_cuatro_digitos() {
        assert!(pin_tiene_formato_valido("0012"));
        assert!(pin_tiene_formato_valido("9876"));
    }

    #[test]
    fn rechaza_pin_con_formato_invalido() {
        assert!(!pin_tiene_formato_valido("123"));
        assert!(!pin_tiene_formato_valido("12345"));
        assert!(!pin_tiene_formato_valido("12a4"));
        assert!(!pin_tiene_formato_valido("１２３４"));
        assert!(!pin_tiene_formato_valido(""));
    }
}

pub(crate) async fn login(
    axum::extract::State(state): axum::extract::State<crate::AppState>,
    solicitud: Result<
        Json<crate::auth::models::LoginRequest>,
        axum::extract::rejection::JsonRejection,
    >,
) -> Response {
    use crate::auth::models::LoginResponse;

    let Json(request) = match solicitud {
        Ok(request) => request,
        Err(error) => {
            return match error {
                axum::extract::rejection::JsonRejection::JsonSyntaxError(_) => {
                    respuesta_error(StatusCode::BAD_REQUEST, "json_invalido")
                }
                axum::extract::rejection::JsonRejection::JsonDataError(_) => {
                    respuesta_error(StatusCode::UNPROCESSABLE_ENTITY, "datos_invalidos")
                }
                axum::extract::rejection::JsonRejection::MissingJsonContentType(_) => {
                    respuesta_error(StatusCode::UNSUPPORTED_MEDIA_TYPE, "content_type_invalido")
                }
                _ => respuesta_error(StatusCode::BAD_REQUEST, "solicitud_invalida"),
            };
        }
    };
    use crate::auth::service::{ResultadoInicioSesion, iniciar_sesion_pin};

    if !pin_tiene_formato_valido(&request.pin) {
        return respuesta_error(StatusCode::BAD_REQUEST, "pin_formato_invalido");
    }

    let resultado = iniciar_sesion_pin(
        &state.db,
        request.establecimiento_id,
        request.terminal_id,
        request.usuario_id,
        &request.pin,
    )
    .await;

    match resultado {
        Ok(ResultadoInicioSesion::Autenticado { token, expira_en }) => (
            StatusCode::OK,
            Json(LoginResponse {
                token,
                usuario_id: request.usuario_id,
                terminal_id: request.terminal_id,
                expira_en: expira_en.to_rfc3339(),
            }),
        )
            .into_response(),

        Ok(ResultadoInicioSesion::Incorrecto) => {
            respuesta_error(StatusCode::UNAUTHORIZED, "credenciales_invalidas")
        }

        Ok(ResultadoInicioSesion::Bloqueado) => {
            respuesta_error(StatusCode::LOCKED, "usuario_bloqueado")
        }

        Ok(ResultadoInicioSesion::SesionYaAbierta) => {
            respuesta_error(StatusCode::CONFLICT, "sesion_ya_abierta")
        }

        Err(_) => respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"),
    }
}

#[cfg(test)]
mod http_tests {
    use super::*;
    use axum::{
        Router,
        body::{Body, to_bytes},
        http::{Method, Request},
        routing::post,
    };
    use sqlx::postgres::PgPoolOptions;
    use tower::ServiceExt;

    #[tokio::test]
    async fn login_http_rechaza_campo_obligatorio_ausente() {
        let pool = PgPoolOptions::new()
            .acquire_timeout(std::time::Duration::from_millis(200))
            .connect_lazy("postgres://test:test@127.0.0.1:1/db_inexistente")
            .expect("URL de prueba válida");

        let app = Router::new()
            .route("/api/v1/auth/login", post(login))
            .with_state(crate::AppState { db: pool });

        let solicitud = serde_json::json!({
            "establecimiento_id": "10000000-0000-4000-8000-000000000001",
            "terminal_id": "20000000-0000-4000-8000-000000000001",
            "pin": "1234"
        });

        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud.to_string()))
            .unwrap();

        let response = app.oneshot(request).await.unwrap();

        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);

        let bytes = to_bytes(response.into_body(), 1024)
            .await
            .expect("Cuerpo HTTP válido");

        let json: serde_json::Value = serde_json::from_slice(&bytes).expect("JSON válido");

        assert_eq!(json, serde_json::json!({"error": "datos_invalidos"}));
    }

    #[tokio::test]
    async fn login_http_rechaza_content_type_invalido() {
        let pool = PgPoolOptions::new()
            .acquire_timeout(std::time::Duration::from_millis(200))
            .connect_lazy("postgres://test:test@127.0.0.1:1/db_inexistente")
            .expect("URL de prueba válida");

        let app = Router::new()
            .route("/api/v1/auth/login", post(login))
            .with_state(crate::AppState { db: pool });

        let solicitud = serde_json::json!({
            "establecimiento_id": "10000000-0000-4000-8000-000000000001",
            "terminal_id": "20000000-0000-4000-8000-000000000001",
            "usuario_id": "40000000-0000-4000-8000-000000000001",
            "pin": "1234"
        });

        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .body(Body::from(solicitud.to_string()))
            .unwrap();

        // No enviamos Content-Type intencionalmente.
        let response = app.oneshot(request).await.unwrap();

        assert_eq!(response.status(), StatusCode::UNSUPPORTED_MEDIA_TYPE);

        let bytes = to_bytes(response.into_body(), 1024)
            .await
            .expect("Cuerpo HTTP válido");

        let json: serde_json::Value = serde_json::from_slice(&bytes).expect("JSON válido");

        assert_eq!(
            json,
            serde_json::json!({
                "error": "content_type_invalido"
            })
        );
    }

    #[tokio::test]
    async fn login_http_rechaza_json_malformado() {
        let pool = PgPoolOptions::new()
            .acquire_timeout(std::time::Duration::from_millis(200))
            .connect_lazy("postgres://test:test@127.0.0.1:1/db_inexistente")
            .expect("URL de prueba válida");

        let app = Router::new()
            .route("/api/v1/auth/login", post(login))
            .with_state(crate::AppState { db: pool });

        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(r#"{"pin": "1234", "#))
            .unwrap();

        let response = app.oneshot(request).await.unwrap();

        assert_eq!(response.status(), StatusCode::BAD_REQUEST);

        let bytes = to_bytes(response.into_body(), 1024)
            .await
            .expect("Cuerpo HTTP válido");

        let json: serde_json::Value = serde_json::from_slice(&bytes).expect("JSON válido");

        assert_eq!(json, serde_json::json!({"error": "json_invalido"}));
    }

    #[tokio::test]
    async fn login_http_rechaza_pin_invalido_sin_consultar_db() {
        let pool = PgPoolOptions::new()
            .acquire_timeout(std::time::Duration::from_millis(200))
            .connect_lazy("postgres://test:test@127.0.0.1:1/db_inexistente")
            .expect("URL de prueba válida");

        let app = Router::new()
            .route("/api/v1/auth/login", post(login))
            .with_state(crate::AppState { db: pool });

        let solicitud = serde_json::json!({
            "establecimiento_id": "10000000-0000-4000-8000-000000000001",
            "terminal_id": "20000000-0000-4000-8000-000000000001",
            "usuario_id": "40000000-0000-4000-8000-000000000001",
            "pin": "12a4"
        });

        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud.to_string()))
            .unwrap();

        let response = app.oneshot(request).await.unwrap();

        assert_eq!(response.status(), StatusCode::BAD_REQUEST);

        let bytes = to_bytes(response.into_body(), 1024)
            .await
            .expect("Cuerpo HTTP válido");

        let json: serde_json::Value = serde_json::from_slice(&bytes).expect("JSON válido");

        assert_eq!(
            json,
            serde_json::json!({
                "error": "pin_formato_invalido"
            })
        );
    }
}
