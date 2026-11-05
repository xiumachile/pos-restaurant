use axum::{Json, extract::State, http::StatusCode, response::Response};
use serde::Serialize;
use uuid::Uuid;

use crate::{
    AppState,
    http::{auth::respuesta_error, authorization::exigir_permiso, bearer::SesionAutenticada},
    zonas::repository,
};

#[derive(Serialize)]
pub(crate) struct ZonaResponse {
    id: Uuid,
    codigo: String,
    nombre_traducciones: serde_json::Value,
    color: String,
    nivel_piso: i32,
    orden: i32,
    activo: bool,
    version: i32,
}

pub(crate) async fn listar(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
) -> Result<Json<Vec<ZonaResponse>>, Response> {
    exigir_permiso(&state, &sesion, "mesas.ver").await?;

    let zonas = repository::listar_zonas(&state.db, sesion.0.establecimiento_id)
        .await
        .map_err(|_| respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"))?;

    let respuesta = zonas
        .into_iter()
        .map(|zona| ZonaResponse {
            id: zona.id,
            codigo: zona.codigo,
            nombre_traducciones: zona.nombre_traducciones,
            color: zona.color,
            nivel_piso: zona.nivel_piso,
            orden: zona.orden,
            activo: zona.activo,
            version: zona.version,
        })
        .collect();

    Ok(Json(respuesta))
}

/// Datos permitidos para crear una zona.
///
/// El establecimiento y el UUID nunca se reciben del cliente.
#[derive(Debug, serde::Deserialize)]
#[serde(deny_unknown_fields)]
pub(crate) struct CrearZonaRequest {
    codigo: String,
    nombre_traducciones: serde_json::Value,

    #[serde(default = "color_predeterminado")]
    color: String,

    #[serde(default)]
    nivel_piso: i32,

    #[serde(default)]
    orden: i32,

    #[serde(default = "activo_predeterminado")]
    activo: bool,
}

fn color_predeterminado() -> String {
    "#64748b".to_owned()
}

fn activo_predeterminado() -> bool {
    true
}

/// Valida los datos de una nueva zona antes de acceder a PostgreSQL.
fn validar_crear_zona(request: &CrearZonaRequest) -> bool {
    let codigo_valido = !request.codigo.is_empty()
        && request.codigo.len() <= 50
        && request
            .codigo
            .bytes()
            .all(|b| b.is_ascii_uppercase() || b.is_ascii_digit() || b == b'_');

    let color_valido = request.color.len() == 7
        && request.color.starts_with('#')
        && request.color.as_bytes()[1..]
            .iter()
            .all(u8::is_ascii_hexdigit);

    let traducciones_validas =
        request
            .nombre_traducciones
            .as_object()
            .is_some_and(|traducciones| {
                ["es", "zh"].iter().all(|idioma| {
                    traducciones
                        .get(*idioma)
                        .and_then(serde_json::Value::as_str)
                        .is_some_and(|nombre| !nombre.trim().is_empty())
                })
            });

    codigo_valido && color_valido && traducciones_validas
}

#[cfg(test)]
mod zonas_validacion_tests {
    use super::*;
    use serde_json::json;

    fn solicitud_valida() -> CrearZonaRequest {
        CrearZonaRequest {
            codigo: "SALON_PRINCIPAL".to_owned(),
            nombre_traducciones: json!({
                "es": "Salón principal",
                "zh": "主厅"
            }),
            color: "#64748b".to_owned(),
            nivel_piso: 0,
            orden: 0,
            activo: true,
        }
    }

    #[test]
    fn acepta_zona_con_espanol_y_chino() {
        assert!(validar_crear_zona(&solicitud_valida()));
    }

    #[test]
    fn rechaza_zona_sin_espanol() {
        let mut request = solicitud_valida();
        request.nombre_traducciones = json!({"zh": "主厅"});

        assert!(!validar_crear_zona(&request));
    }

    #[test]
    fn rechaza_zona_sin_chino() {
        let mut request = solicitud_valida();
        request.nombre_traducciones = json!({"es": "Salón principal"});

        assert!(!validar_crear_zona(&request));
    }

    #[test]
    fn rechaza_traducciones_vacias_o_invalidas() {
        for traducciones in [
            json!({"es": "", "zh": "主厅"}),
            json!({"es": "Salón", "zh": "   "}),
            json!({"es": null, "zh": "主厅"}),
            json!({"es": 123, "zh": "主厅"}),
            json!(["Salón", "主厅"]),
        ] {
            let mut request = solicitud_valida();
            request.nombre_traducciones = traducciones;

            assert!(!validar_crear_zona(&request));
        }
    }

    #[test]
    fn rechaza_codigo_invalido() {
        for codigo in ["", "salon", "SALON PRINCIPAL", "SALON-1", "ÁREA"] {
            let mut request = solicitud_valida();
            request.codigo = codigo.to_owned();

            assert!(!validar_crear_zona(&request));
        }

        let mut request = solicitud_valida();
        request.codigo = "A".repeat(51);

        assert!(!validar_crear_zona(&request));
    }

    #[test]
    fn rechaza_color_invalido() {
        for color in ["", "64748b", "#FFF", "#GGGGGG", "#12345678"] {
            let mut request = solicitud_valida();
            request.color = color.to_owned();

            assert!(!validar_crear_zona(&request));
        }
    }

    #[test]
    fn aplica_valores_predeterminados() {
        let request: CrearZonaRequest = serde_json::from_value(json!({
            "codigo": "SALON_PRINCIPAL",
            "nombre_traducciones": {
                "es": "Salón principal",
                "zh": "主厅"
            }
        }))
        .unwrap();

        assert_eq!(request.color, "#64748b");
        assert_eq!(request.nivel_piso, 0);
        assert_eq!(request.orden, 0);
        assert!(request.activo);
        assert!(validar_crear_zona(&request));
    }

    #[test]
    fn rechaza_campos_no_permitidos() {
        let resultado = serde_json::from_value::<CrearZonaRequest>(json!({
            "codigo": "SALON_PRINCIPAL",
            "nombre_traducciones": {
                "es": "Salón principal",
                "zh": "主厅"
            },
            "establecimiento_id": "no_permitido"
        }));

        assert!(resultado.is_err());
    }
    #[test]
    fn actualizar_acepta_cambios_parciales_validos() {
        let casos = [
            json!({"version": 1, "color": "#22C55E"}),
            json!({"version": 1, "nivel_piso": -1}),
            json!({"version": 1, "orden": 5}),
            json!({"version": 1, "activo": false}),
            json!({
                "version": 1,
                "nombre_traducciones": {
                    "es": "Terraza",
                    "zh": "露台"
                }
            }),
        ];

        for caso in casos {
            let request: ActualizarZonaRequest = serde_json::from_value(caso).unwrap();

            assert!(validar_actualizar_zona(&request));
        }
    }

    #[test]
    fn actualizar_rechaza_version_invalida() {
        for version in [0, -1] {
            let request: ActualizarZonaRequest = serde_json::from_value(json!({
                "version": version,
                "color": "#22C55E"
            }))
            .unwrap();

            assert!(!validar_actualizar_zona(&request));
        }

        assert!(
            serde_json::from_value::<ActualizarZonaRequest>(json!({"color": "#22C55E"})).is_err()
        );
    }

    #[test]
    fn actualizar_rechaza_solicitud_sin_cambios() {
        let request: ActualizarZonaRequest = serde_json::from_value(json!({
            "version": 1
        }))
        .unwrap();

        assert!(!validar_actualizar_zona(&request));
    }

    #[test]
    fn actualizar_rechaza_traducciones_invalidas() {
        for traducciones in [
            json!({"es": "Terraza"}),
            json!({"zh": "露台"}),
            json!({"es": "", "zh": "露台"}),
            json!({"es": "Terraza", "zh": " "}),
            json!(["Terraza", "露台"]),
        ] {
            let request: ActualizarZonaRequest = serde_json::from_value(json!({
                "version": 1,
                "nombre_traducciones": traducciones
            }))
            .unwrap();

            assert!(!validar_actualizar_zona(&request));
        }
    }

    #[test]
    fn actualizar_rechaza_color_invalido() {
        for color in ["", "22C55E", "#FFF", "#GGGGGG"] {
            let request: ActualizarZonaRequest = serde_json::from_value(json!({
                "version": 1,
                "color": color
            }))
            .unwrap();

            assert!(!validar_actualizar_zona(&request));
        }
    }

    #[test]
    fn actualizar_rechaza_campos_inmutables() {
        for campo in ["codigo", "id", "establecimiento_id"] {
            let mut datos = json!({
                "version": 1,
                "color": "#22C55E"
            });

            datos[campo] = json!("NO_PERMITIDO");

            assert!(serde_json::from_value::<ActualizarZonaRequest>(datos).is_err());
        }
    }
    #[test]
    fn actualizar_rechaza_null_explicito() {
        for campo in [
            "nombre_traducciones",
            "color",
            "nivel_piso",
            "orden",
            "activo",
        ] {
            let mut datos = json!({
                "version": 1,
                "color": "#22C55E"
            });

            datos[campo] = serde_json::Value::Null;

            let resultado = serde_json::from_value::<ActualizarZonaRequest>(datos);

            assert!(resultado.is_err(), "El campo {campo} no debe aceptar null");
        }
    }
}

/// Crea una zona en el establecimiento de la sesión autenticada.
pub(crate) async fn crear(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
    solicitud: Result<Json<CrearZonaRequest>, axum::extract::rejection::JsonRejection>,
) -> Response {
    use axum::response::IntoResponse;

    if let Err(respuesta) = exigir_permiso(&state, &sesion, "mesas.configurar").await {
        return respuesta;
    }

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

    if !validar_crear_zona(&request) {
        return respuesta_error(StatusCode::UNPROCESSABLE_ENTITY, "datos_invalidos");
    }

    let nueva = repository::NuevaZona {
        codigo: request.codigo,
        nombre_traducciones: request.nombre_traducciones,
        color: request.color,
        nivel_piso: request.nivel_piso,
        orden: request.orden,
        activo: request.activo,
    };

    let zona = match repository::crear_zona(&state.db, sesion.0.establecimiento_id, nueva).await {
        Ok(zona) => zona,

        Err(sqlx::Error::Database(error))
            if error.code().as_deref() == Some("23505")
                && error.constraint() == Some("zonas_codigo_por_establecimiento") =>
        {
            return respuesta_error(StatusCode::CONFLICT, "codigo_duplicado");
        }

        Err(_) => {
            return respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible");
        }
    };

    (
        StatusCode::CREATED,
        Json(ZonaResponse {
            id: zona.id,
            codigo: zona.codigo,
            nombre_traducciones: zona.nombre_traducciones,
            color: zona.color,
            nivel_piso: zona.nivel_piso,
            orden: zona.orden,
            activo: zona.activo,
            version: zona.version,
        }),
    )
        .into_response()
}

/// Deserializa un campo opcional, rechazando null explícito.
///
/// Campo ausente: None, mediante serde(default).
/// Campo presente: Some(valor), siempre que no sea null.
fn campo_actualizacion<'de, D, T>(deserializer: D) -> Result<Option<T>, D::Error>
where
    D: serde::Deserializer<'de>,
    T: serde::Deserialize<'de>,
{
    use serde::{Deserialize, de::Error};

    let valor = serde_json::Value::deserialize(deserializer)?;

    if valor.is_null() {
        return Err(D::Error::custom(
            "null no está permitido en campos de actualización",
        ));
    }

    T::deserialize(valor).map(Some).map_err(D::Error::custom)
}

/// Datos permitidos para actualizar parcialmente una zona.
///
/// El código, UUID y establecimiento son inmutables.
#[derive(Debug, serde::Deserialize)]
#[serde(deny_unknown_fields)]
pub(crate) struct ActualizarZonaRequest {
    version: i32,

    #[serde(default, deserialize_with = "campo_actualizacion")]
    nombre_traducciones: Option<serde_json::Value>,

    #[serde(default, deserialize_with = "campo_actualizacion")]
    color: Option<String>,

    #[serde(default, deserialize_with = "campo_actualizacion")]
    nivel_piso: Option<i32>,

    #[serde(default, deserialize_with = "campo_actualizacion")]
    orden: Option<i32>,

    #[serde(default, deserialize_with = "campo_actualizacion")]
    activo: Option<bool>,
}

/// Valida los datos de actualización parcial de una zona.
///
/// Requiere una versión positiva y al menos un campo modificable.
/// Los campos proporcionados deben respetar las reglas de creación.
fn validar_actualizar_zona(request: &ActualizarZonaRequest) -> bool {
    if request.version < 1 {
        return false;
    }

    let tiene_cambios = request.nombre_traducciones.is_some()
        || request.color.is_some()
        || request.nivel_piso.is_some()
        || request.orden.is_some()
        || request.activo.is_some();

    if !tiene_cambios {
        return false;
    }

    if let Some(traducciones) = &request.nombre_traducciones {
        let validas = traducciones.as_object().is_some_and(|objeto| {
            ["es", "zh"].iter().all(|idioma| {
                objeto
                    .get(*idioma)
                    .and_then(serde_json::Value::as_str)
                    .is_some_and(|nombre| !nombre.trim().is_empty())
            })
        });

        if !validas {
            return false;
        }
    }

    if let Some(color) = &request.color {
        let valido = color.len() == 7
            && color.starts_with('#')
            && color.as_bytes()[1..].iter().all(u8::is_ascii_hexdigit);

        if !valido {
            return false;
        }
    }

    true
}

/// Actualiza parcialmente una zona del establecimiento autenticado.
pub(crate) async fn actualizar(
    State(state): State<AppState>,
    sesion: SesionAutenticada,
    axum::extract::Path(zona_id): axum::extract::Path<Uuid>,
    solicitud: Result<Json<ActualizarZonaRequest>, axum::extract::rejection::JsonRejection>,
) -> Response {
    use axum::response::IntoResponse;

    if let Err(respuesta) = exigir_permiso(&state, &sesion, "mesas.configurar").await {
        return respuesta;
    }

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

    if !validar_actualizar_zona(&request) {
        return respuesta_error(StatusCode::UNPROCESSABLE_ENTITY, "datos_invalidos");
    }

    let cambios = repository::ActualizarZona {
        version: request.version,
        nombre_traducciones: request.nombre_traducciones,
        color: request.color,
        nivel_piso: request.nivel_piso,
        orden: request.orden,
        activo: request.activo,
    };

    let establecimiento_id = sesion.0.establecimiento_id;

    match repository::actualizar_zona(&state.db, establecimiento_id, zona_id, cambios).await {
        Ok(Some(zona)) => Json(ZonaResponse {
            id: zona.id,
            codigo: zona.codigo,
            nombre_traducciones: zona.nombre_traducciones,
            color: zona.color,
            nivel_piso: zona.nivel_piso,
            orden: zona.orden,
            activo: zona.activo,
            version: zona.version,
        })
        .into_response(),

        Ok(None) => match repository::existe_zona(&state.db, establecimiento_id, zona_id).await {
            Ok(true) => respuesta_error(StatusCode::CONFLICT, "version_conflicto"),
            Ok(false) => respuesta_error(StatusCode::NOT_FOUND, "zona_no_encontrada"),
            Err(_) => respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"),
        },

        Err(_) => respuesta_error(StatusCode::SERVICE_UNAVAILABLE, "servicio_no_disponible"),
    }
}
