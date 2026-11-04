use serde::{Deserialize, Serialize};
use uuid::Uuid;

#[derive(Deserialize)]
pub struct LoginRequest {
    pub establecimiento_id: Uuid,
    pub terminal_id: Uuid,
    pub usuario_id: Uuid,
    pub pin: String,
}

impl std::fmt::Debug for LoginRequest {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        f.debug_struct("LoginRequest")
            .field("establecimiento_id", &self.establecimiento_id)
            .field("terminal_id", &self.terminal_id)
            .field("usuario_id", &self.usuario_id)
            .field("pin", &"[REDACTED]")
            .finish()
    }
}

#[derive(Serialize)]
pub struct LoginResponse {
    pub token: String,
    pub usuario_id: Uuid,
    pub terminal_id: Uuid,
    pub expira_en: String,
}

impl std::fmt::Debug for LoginResponse {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        f.debug_struct("LoginResponse")
            .field("token", &"[REDACTED]")
            .field("usuario_id", &self.usuario_id)
            .field("terminal_id", &self.terminal_id)
            .field("expira_en", &self.expira_en)
            .finish()
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn debug_login_response_oculta_token_pero_json_lo_conserva() {
        let token = "TOKEN_SECRETO_PRUEBA_123456";

        let response = LoginResponse {
            token: token.to_string(),
            usuario_id: Uuid::new_v4(),
            terminal_id: Uuid::new_v4(),
            expira_en: "2026-11-04T12:00:00Z".to_string(),
        };

        let depuracion = format!("{response:?}");

        assert!(!depuracion.contains(token));
        assert!(depuracion.contains("[REDACTED]"));

        let json = serde_json::to_value(&response).unwrap();

        assert_eq!(json["token"].as_str(), Some(token));
        assert_eq!(
            json["usuario_id"].as_str(),
            Some(response.usuario_id.to_string().as_str())
        );
    }

    #[test]
    fn debug_login_request_oculta_pin() {
        let request = LoginRequest {
            establecimiento_id: Uuid::new_v4(),
            terminal_id: Uuid::new_v4(),
            usuario_id: Uuid::new_v4(),
            pin: "9876".to_string(),
        };

        let depuracion = format!("{request:?}");

        assert!(!depuracion.contains("9876"));
        assert!(depuracion.contains("[REDACTED]"));
        assert!(depuracion.contains("LoginRequest"));
    }

    #[test]
    fn deserializar_login_valido() {
        let json = r#"{
            "establecimiento_id": "10000000-0000-4000-8000-000000000001",
            "terminal_id": "20000000-0000-4000-8000-000000000001",
            "usuario_id": "40000000-0000-4000-8000-000000000001",
            "pin": "0012"
        }"#;

        let request: LoginRequest = serde_json::from_str(json).unwrap();

        assert_eq!(request.pin, "0012");
        assert_eq!(
            request.establecimiento_id.to_string(),
            "10000000-0000-4000-8000-000000000001"
        );
    }

    #[test]
    fn rechazar_uuid_invalido() {
        let json = r#"{
            "establecimiento_id": "invalido",
            "terminal_id": "20000000-0000-4000-8000-000000000001",
            "usuario_id": "40000000-0000-4000-8000-000000000001",
            "pin": "1234"
        }"#;

        assert!(serde_json::from_str::<LoginRequest>(json).is_err());
    }
}
