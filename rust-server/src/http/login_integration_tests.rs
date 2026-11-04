use crate::AppState;
use crate::auth::pin::hash_pin;
use crate::auth::session::validar_token_sesion;
use crate::auth::token::hash_token;
use axum::{
    Router,
    body::{Body, to_bytes},
    http::{Method, Request, StatusCode},
    routing::post,
};
use sqlx::PgPool;
use tower::ServiceExt;
use uuid::Uuid;

#[tokio::test]
async fn login_http_exitoso_postgresql() -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let permiso_rbac_id = Uuid::new_v4();
    let establecimiento_ajeno_id = Uuid::new_v4();
    let rol_ajeno_id = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_HTTP_LOGIN_REAL")
        .execute(&pool)
        .await?;

    // Segundo establecimiento independiente para probar aislamiento HTTP.
    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2)",
    )
    .bind(establecimiento_ajeno_id)
    .bind("TEST_HTTP_TENANT_AJENO")
    .execute(&pool)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_ajeno_id)
    .bind(establecimiento_ajeno_id)
    .bind("TEST_ROL_TENANT_AJENO")
    .execute(&pool)
    .await?;

    let pool_prueba = pool.clone();

    let prueba = tokio::spawn(async move {
        let pool = pool_prueba;

        sqlx::query(
            "INSERT INTO terminales (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_TERMINAL_HTTP")
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(rol_id)
        .bind(establecimiento_id)
        .bind("TEST_ROL_HTTP")
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_HTTP")
        .bind(hash_pin("1234")?)
        .execute(&pool)
        .await?;

        let app = Router::new()
            .route("/api/v1/auth/login", post(crate::http::auth::login))
            .route("/api/v1/auth/me", axum::routing::get(crate::http::me::me))
            .route("/api/v1/auth/logout", post(crate::http::logout::logout))
            .route(
                "/api/v1/test/rbac",
                axum::routing::get(
                    crate::http::authorization_integration_tests::endpoint_protegido_prueba,
                ),
            )
            .route(
                "/api/v1/roles/{rol_id}/permisos",
                post(crate::http::role_permissions::conceder)
                    .delete(crate::http::role_permissions::revocar),
            )
            .with_state(AppState { db: pool.clone() });

        // Caso HTTP: bloqueo automático por cinco intentos.
        let usuario_bloqueo_id = Uuid::new_v4();

        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(usuario_bloqueo_id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_HTTP_BLOQUEO")
        .bind(hash_pin("5678")?)
        .execute(&pool)
        .await?;

        for intento in 1..=5 {
            let solicitud = serde_json::json!({
                "establecimiento_id": establecimiento_id,
                "terminal_id": terminal_id,
                "usuario_id": usuario_bloqueo_id,
                "pin": "9999"
            });

            let request = Request::builder()
                .method(Method::POST)
                .uri("/api/v1/auth/login")
                .header("content-type", "application/json")
                .body(Body::from(solicitud.to_string()))?;

            let response = app.clone().oneshot(request).await?;

            assert_eq!(
                response.status(),
                StatusCode::UNAUTHORIZED,
                "El intento {intento} debe rechazarse"
            );

            let bytes = to_bytes(response.into_body(), 4096).await?;
            let error: serde_json::Value = serde_json::from_slice(&bytes)?;

            assert_eq!(
                error,
                serde_json::json!({"error": "credenciales_invalidas"})
            );
        }

        let (intentos, bloqueado_hasta): (i32, Option<chrono::DateTime<chrono::Utc>>) =
            sqlx::query_as(
                "SELECT intentos_fallidos, bloqueado_hasta
                 FROM usuarios WHERE id = $1",
            )
            .bind(usuario_bloqueo_id)
            .fetch_one(&pool)
            .await?;

        assert_eq!(intentos, 5);
        assert!(
            bloqueado_hasta.is_some_and(|fecha| fecha > chrono::Utc::now()),
            "El usuario debe quedar bloqueado"
        );

        // El PIN correcto tampoco debe permitir acceso mientras esté bloqueado.
        let solicitud_bloqueada = serde_json::json!({
            "establecimiento_id": establecimiento_id,
            "terminal_id": terminal_id,
            "usuario_id": usuario_bloqueo_id,
            "pin": "5678"
        });

        let request_bloqueado = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud_bloqueada.to_string()))?;

        let response_bloqueado = app.clone().oneshot(request_bloqueado).await?;

        assert_eq!(response_bloqueado.status(), StatusCode::LOCKED);

        let bytes = to_bytes(response_bloqueado.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "usuario_bloqueado"}));

        let sesiones_bloqueadas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE usuario_id = $1",
        )
        .bind(usuario_bloqueo_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(
            sesiones_bloqueadas, 0,
            "Un usuario bloqueado no debe tener sesiones"
        );

        // Caso HTTP: PIN incorrecto.
        let solicitud_incorrecta = serde_json::json!({
            "establecimiento_id": establecimiento_id,
            "terminal_id": terminal_id,
            "usuario_id": usuario_id,
            "pin": "9999"
        });

        let request_incorrecto = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud_incorrecta.to_string()))?;

        let respuesta_incorrecta = app.clone().oneshot(request_incorrecto).await?;

        assert_eq!(respuesta_incorrecta.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(respuesta_incorrecta.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            error,
            serde_json::json!({
                "error": "credenciales_invalidas"
            })
        );

        let intentos: i32 =
            sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(intentos, 1);

        let sesiones: i64 =
            sqlx::query_scalar("SELECT COUNT(*) FROM sesiones_usuario WHERE usuario_id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(sesiones, 0, "Un PIN incorrecto no debe crear sesiones");

        let solicitud = serde_json::json!({
            "establecimiento_id": establecimiento_id,
            "terminal_id": terminal_id,
            "usuario_id": usuario_id,
            "pin": "1234"
        });

        let request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud.to_string()))?;

        let response = app.clone().oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::OK);

        let bytes = to_bytes(response.into_body(), 4096).await?;
        let json: serde_json::Value = serde_json::from_slice(&bytes)?;

        let token = json["token"]
            .as_str()
            .expect("La respuesta debe incluir el token");

        assert_eq!(token.len(), 64);
        assert!(token.bytes().all(|b| b.is_ascii_hexdigit()));

        assert_eq!(
            json["usuario_id"].as_str(),
            Some(usuario_id.to_string().as_str())
        );
        assert_eq!(
            json["terminal_id"].as_str(),
            Some(terminal_id.to_string().as_str())
        );

        let expira_en = json["expira_en"]
            .as_str()
            .expect("Debe incluir la expiración");

        let expiracion = chrono::DateTime::parse_from_rfc3339(expira_en)?;
        assert!(expiracion > chrono::Utc::now());

        // Caso HTTP: impedir una segunda sesión abierta.
        let segundo_request = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud.to_string()))?;

        let segunda_respuesta = app.clone().oneshot(segundo_request).await?;

        assert_eq!(
            segunda_respuesta.status(),
            StatusCode::CONFLICT,
            "Una segunda sesión debe ser rechazada"
        );

        let bytes = to_bytes(segunda_respuesta.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            error,
            serde_json::json!({
                "error": "sesion_ya_abierta"
            })
        );

        let sesiones_abiertas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE establecimiento_id = $1
               AND usuario_id = $2
               AND cerrada_en IS NULL",
        )
        .bind(establecimiento_id)
        .bind(usuario_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(sesiones_abiertas, 1, "Solo debe existir una sesión abierta");

        let hash_guardado: String = sqlx::query_scalar(
            "SELECT token_hash
             FROM sesiones_usuario
             WHERE establecimiento_id = $1
               AND usuario_id = $2
               AND terminal_id = $3
               AND cerrada_en IS NULL",
        )
        .bind(establecimiento_id)
        .bind(usuario_id)
        .bind(terminal_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(hash_guardado, hash_token(token));
        assert_ne!(hash_guardado, token);

        let sesion = validar_token_sesion(&pool, token, establecimiento_id, terminal_id)
            .await?
            .expect("El token HTTP debe validar una sesión real");

        assert_eq!(sesion.establecimiento_id, establecimiento_id);
        assert_eq!(sesion.terminal_id, terminal_id);
        assert_eq!(sesion.usuario_id, usuario_id);
        assert_eq!(sesion.rol_id, rol_id);

        // Caso Bearer: el token identifica su propia sesión.
        let sesion_bearer = crate::auth::session::validar_token_bearer(&pool, token)
            .await?
            .expect("El token Bearer debe ser válido");

        assert_eq!(sesion_bearer, sesion);

        // Caso HTTP: /me sin Authorization.
        let request_sin_token = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .body(Body::empty())?;

        let response_sin_token = app.clone().oneshot(request_sin_token).await?;

        assert_eq!(response_sin_token.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_sin_token.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Caso HTTP: /me con token inexistente.
        let token_falso = crate::auth::token::generar_token();

        let request_token_falso = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .header("authorization", format!("Bearer {token_falso}"))
            .body(Body::empty())?;

        let response_token_falso = app.clone().oneshot(request_token_falso).await?;

        assert_eq!(response_token_falso.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_token_falso.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Caso HTTP: /me con token válido.
        let request_autorizado = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response_autorizada = app.clone().oneshot(request_autorizado).await?;

        assert_eq!(response_autorizada.status(), StatusCode::OK);

        let bytes = to_bytes(response_autorizada.into_body(), 4096).await?;
        let datos: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(datos["establecimiento_id"], establecimiento_id.to_string());
        assert_eq!(datos["usuario_id"], usuario_id.to_string());
        assert_eq!(datos["terminal_id"], terminal_id.to_string());
        assert_eq!(datos["rol_id"], rol_id.to_string());

        assert!(
            datos.get("token").is_none(),
            "El endpoint /me nunca debe devolver el token"
        );

        // Un token inexistente no debe autenticar.
        let token_inexistente = crate::auth::token::generar_token();

        let resultado_inexistente =
            crate::auth::session::validar_token_bearer(&pool, &token_inexistente).await?;

        assert!(
            resultado_inexistente.is_none(),
            "Un token inexistente debe rechazarse"
        );

        // Un token con formato inválido también debe rechazarse.
        let resultado_formato =
            crate::auth::session::validar_token_bearer(&pool, "token-invalido").await?;

        assert!(resultado_formato.is_none());

        // Caso HTTP: componentes desactivados invalidan el acceso Bearer.
        let componentes = [
            ("usuarios", usuario_id),
            ("roles", rol_id),
            ("terminales", terminal_id),
            ("establecimientos", establecimiento_id),
        ];

        for (tabla, id) in componentes {
            // Los nombres de tabla son constantes definidas arriba,
            // nunca provienen de una solicitud HTTP.
            let desactivar = format!("UPDATE {tabla} SET activo = FALSE WHERE id = $1");

            sqlx::query(&desactivar).bind(id).execute(&pool).await?;

            let request_inactivo = Request::builder()
                .method(Method::GET)
                .uri("/api/v1/auth/me")
                .header("authorization", format!("Bearer {token}"))
                .body(Body::empty())?;

            let response_inactivo = app.clone().oneshot(request_inactivo).await?;

            assert_eq!(
                response_inactivo.status(),
                StatusCode::UNAUTHORIZED,
                "Un componente desactivado debe impedir acceso: {tabla}"
            );

            let bytes = to_bytes(response_inactivo.into_body(), 4096).await?;
            let error: serde_json::Value = serde_json::from_slice(&bytes)?;

            assert_eq!(
                error,
                serde_json::json!({"error": "token_invalido"}),
                "Error HTTP incorrecto para {tabla}"
            );

            // Restaurar el componente antes de probar el siguiente.
            let activar = format!("UPDATE {tabla} SET activo = TRUE WHERE id = $1");

            sqlx::query(&activar).bind(id).execute(&pool).await?;

            let request_restaurado = Request::builder()
                .method(Method::GET)
                .uri("/api/v1/auth/me")
                .header("authorization", format!("Bearer {token}"))
                .body(Body::empty())?;

            let response_restaurado = app.clone().oneshot(request_restaurado).await?;

            assert_eq!(
                response_restaurado.status(),
                StatusCode::OK,
                "La sesión debe recuperar acceso al reactivar {tabla}"
            );
        }

        // Cerrar la sesión y comprobar su revocación.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET cerrada_en = GREATEST(NOW(), iniciada_en),
                 motivo_cierre = 'prueba_bearer'
             WHERE id = $1",
        )
        .bind(sesion.sesion_id)
        .execute(&pool)
        .await?;

        let sesion_cerrada = crate::auth::session::validar_token_bearer(&pool, token).await?;

        assert!(
            sesion_cerrada.is_none(),
            "Una sesión cerrada no debe autenticar"
        );

        // Caso HTTP: un token revocado no puede acceder a /me.
        let request_revocado = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response_revocado = app.clone().oneshot(request_revocado).await?;

        assert_eq!(
            response_revocado.status(),
            StatusCode::UNAUTHORIZED,
            "Una sesión cerrada debe perder acceso HTTP"
        );

        let bytes = to_bytes(response_revocado.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            error,
            serde_json::json!({
                "error": "token_invalido"
            })
        );

        // Caso RBAC HTTP: sin token.
        let request_rbac_sin_token = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/test/rbac")
            .body(Body::empty())?;

        let response_rbac_sin_token = app.clone().oneshot(request_rbac_sin_token).await?;

        assert_eq!(response_rbac_sin_token.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_rbac_sin_token.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Administración RBAC HTTP: rechazar solicitud sin Bearer.
        let request_permisos_sin_token = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_permisos_sin_token = app.clone().oneshot(request_permisos_sin_token).await?;

        assert_eq!(
            response_permisos_sin_token.status(),
            StatusCode::UNAUTHORIZED
        );

        let bytes = to_bytes(response_permisos_sin_token.into_body(), 4096).await?;

        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Caso HTTP: logout voluntario.
        let request_nuevo_login = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(solicitud.to_string()))?;

        let response_nuevo_login = app.clone().oneshot(request_nuevo_login).await?;

        assert_eq!(response_nuevo_login.status(), StatusCode::OK);

        let bytes = to_bytes(response_nuevo_login.into_body(), 4096).await?;
        let login_json: serde_json::Value = serde_json::from_slice(&bytes)?;

        let nuevo_token = login_json["token"]
            .as_str()
            .expect("El nuevo login debe devolver un token");

        // Caso RBAC HTTP: token válido sin permiso.
        let request_rbac_sin_permiso = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/test/rbac")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_rbac_sin_permiso = app.clone().oneshot(request_rbac_sin_permiso).await?;

        assert_eq!(response_rbac_sin_permiso.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(response_rbac_sin_permiso.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "permiso_denegado"}));

        // Administración RBAC HTTP: token válido sin roles.gestionar.
        let request_permisos_sin_autorizacion = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_permisos_sin_autorizacion = app
            .clone()
            .oneshot(request_permisos_sin_autorizacion)
            .await?;

        assert_eq!(
            response_permisos_sin_autorizacion.status(),
            StatusCode::FORBIDDEN
        );

        let bytes = to_bytes(response_permisos_sin_autorizacion.into_body(), 4096).await?;

        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "permiso_denegado"}));

        // El intento no debe haber concedido el permiso.
        assert!(
            !crate::auth::permissions::rol_tiene_permiso(
                &pool,
                establecimiento_id,
                rol_id,
                "caja.cobrar",
            )
            .await?
        );

        // Administración RBAC HTTP: habilitar temporalmente al administrador.
        let permiso_administrativo = crate::auth::permissions::conceder_permiso(
            &pool,
            establecimiento_id,
            rol_id,
            "roles.gestionar",
        )
        .await?;

        assert!(permiso_administrativo);

        // Primera concesión: debe insertar la asignación.
        let request_concesion_autorizada = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_concesion_autorizada =
            app.clone().oneshot(request_concesion_autorizada).await?;

        assert_eq!(response_concesion_autorizada.status(), StatusCode::OK);

        let bytes = to_bytes(response_concesion_autorizada.into_body(), 4096).await?;

        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": true
            })
        );

        // Segunda concesión: debe ser idempotente.
        let request_concesion_repetida = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_concesion_repetida = app.clone().oneshot(request_concesion_repetida).await?;

        assert_eq!(response_concesion_repetida.status(), StatusCode::OK);

        let bytes = to_bytes(response_concesion_repetida.into_body(), 4096).await?;

        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": false
            })
        );

        // Verificar que PostgreSQL contiene una sola asignación.
        let cantidad_asignaciones: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM rol_permisos rp
             JOIN permisos p ON p.id = rp.permiso_id
             WHERE rp.establecimiento_id = $1
               AND rp.rol_id = $2
               AND p.codigo = $3",
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("caja.cobrar")
        .fetch_one(&pool)
        .await?;

        assert_eq!(cantidad_asignaciones, 1);

        // Administración RBAC HTTP: impedir revocar al último administrador.
        let request_revocacion_ultimo_admin = Request::builder()
            .method(Method::DELETE)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "roles.gestionar"}).to_string(),
            ))?;

        let response_revocacion_ultimo_admin =
            app.clone().oneshot(request_revocacion_ultimo_admin).await?;

        assert_eq!(
            response_revocacion_ultimo_admin.status(),
            StatusCode::CONFLICT
        );

        let bytes = to_bytes(response_revocacion_ultimo_admin.into_body(), 4096).await?;

        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "ultimo_administrador"}));

        // La revocación rechazada no debe modificar PostgreSQL.
        assert!(
            crate::auth::permissions::rol_tiene_permiso(
                &pool,
                establecimiento_id,
                rol_id,
                "roles.gestionar",
            )
            .await?,
            "El último administrador debe conservar roles.gestionar"
        );

        // Administración RBAC HTTP: revocar un permiso ordinario.
        let request_revocacion_ordinaria = Request::builder()
            .method(Method::DELETE)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_revocacion_ordinaria =
            app.clone().oneshot(request_revocacion_ordinaria).await?;

        assert_eq!(response_revocacion_ordinaria.status(), StatusCode::OK);

        let bytes = to_bytes(response_revocacion_ordinaria.into_body(), 4096).await?;

        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": true
            })
        );

        // Repetir la revocación debe ser idempotente.
        let request_revocacion_repetida = Request::builder()
            .method(Method::DELETE)
            .uri(format!("/api/v1/roles/{rol_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_revocacion_repetida = app.clone().oneshot(request_revocacion_repetida).await?;

        assert_eq!(response_revocacion_repetida.status(), StatusCode::OK);

        let bytes = to_bytes(response_revocacion_repetida.into_body(), 4096).await?;

        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": false
            })
        );

        // Confirmar que el permiso fue retirado.
        assert!(
            !crate::auth::permissions::rol_tiene_permiso(
                &pool,
                establecimiento_id,
                rol_id,
                "caja.cobrar",
            )
            .await?,
            "El permiso ordinario debe quedar revocado"
        );

        // Aislamiento multi-tenant: el rol ajeno pertenece a otro establecimiento.
        assert!(
            crate::auth::permissions::conceder_permiso(
                &pool,
                establecimiento_ajeno_id,
                rol_ajeno_id,
                "caja.cobrar",
            )
            .await?
        );

        // POST cruzado: no debe modificar el establecimiento ajeno.
        let request_tenant_ajeno_post = Request::builder()
            .method(Method::POST)
            .uri(format!("/api/v1/roles/{rol_ajeno_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "productos.gestionar"}).to_string(),
            ))?;

        let response_tenant_ajeno_post = app.clone().oneshot(request_tenant_ajeno_post).await?;

        assert_eq!(response_tenant_ajeno_post.status(), StatusCode::OK);

        let bytes = to_bytes(response_tenant_ajeno_post.into_body(), 4096).await?;
        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": false
            })
        );

        assert!(
            !crate::auth::permissions::rol_tiene_permiso(
                &pool,
                establecimiento_ajeno_id,
                rol_ajeno_id,
                "productos.gestionar",
            )
            .await?
        );

        // DELETE cruzado: no debe revocar un permiso del establecimiento ajeno.
        let request_tenant_ajeno_delete = Request::builder()
            .method(Method::DELETE)
            .uri(format!("/api/v1/roles/{rol_ajeno_id}/permisos"))
            .header("authorization", format!("Bearer {nuevo_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({"codigo": "caja.cobrar"}).to_string(),
            ))?;

        let response_tenant_ajeno_delete = app.clone().oneshot(request_tenant_ajeno_delete).await?;

        assert_eq!(response_tenant_ajeno_delete.status(), StatusCode::OK);

        let bytes = to_bytes(response_tenant_ajeno_delete.into_body(), 4096).await?;
        let resultado: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(
            resultado,
            serde_json::json!({
                "status": "procesado",
                "modificado": false
            })
        );

        assert!(
            crate::auth::permissions::rol_tiene_permiso(
                &pool,
                establecimiento_ajeno_id,
                rol_ajeno_id,
                "caja.cobrar",
            )
            .await?,
            "El permiso del establecimiento ajeno debe conservarse"
        );

        // Caso RBAC HTTP: conceder y revocar permiso.
        //
        // El catálogo global de permisos puede compartirse
        // entre establecimientos. Aquí creamos un permiso
        // temporal para esta prueba.

        sqlx::query(
            "INSERT INTO permisos (id, codigo, descripcion)
             VALUES ($1, $2, $3)",
        )
        .bind(permiso_rbac_id)
        .bind("pos.rbac.prueba")
        .bind("Permiso temporal de integración HTTP RBAC")
        .execute(&pool)
        .await?;

        // Conceder el permiso al rol del establecimiento.
        sqlx::query(
            "INSERT INTO rol_permisos
             (establecimiento_id, rol_id, permiso_id)
             VALUES ($1, $2, $3)",
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind(permiso_rbac_id)
        .execute(&pool)
        .await?;

        // La misma sesión debe obtener ahora 200 OK.
        let request_rbac_autorizado = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/test/rbac")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_rbac_autorizado = app.clone().oneshot(request_rbac_autorizado).await?;

        assert_eq!(response_rbac_autorizado.status(), StatusCode::OK);

        let bytes = to_bytes(response_rbac_autorizado.into_body(), 4096).await?;
        let respuesta: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(respuesta, serde_json::json!({"status": "autorizado"}));

        // Revocar el permiso sin cerrar la sesión.
        sqlx::query(
            "DELETE FROM rol_permisos
             WHERE establecimiento_id = $1
               AND rol_id = $2
               AND permiso_id = $3",
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind(permiso_rbac_id)
        .execute(&pool)
        .await?;

        // La misma sesión debe obtener inmediatamente 403.
        let request_rbac_revocado = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/test/rbac")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_rbac_revocado = app.clone().oneshot(request_rbac_revocado).await?;

        assert_eq!(response_rbac_revocado.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(response_rbac_revocado.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "permiso_denegado"}));

        // Revocar un permiso no debe cerrar la sesión Bearer.
        let request_me_tras_revocacion = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_me_tras_revocacion = app.clone().oneshot(request_me_tras_revocacion).await?;

        assert_eq!(
            response_me_tras_revocacion.status(),
            StatusCode::OK,
            "La sesión debe seguir válida después de revocar un permiso"
        );

        let bytes = to_bytes(response_me_tras_revocacion.into_body(), 4096).await?;
        let datos: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(datos["usuario_id"], usuario_id.to_string());
        assert_eq!(datos["rol_id"], rol_id.to_string());

        let sesiones_abiertas: i64 = sqlx::query_scalar(
            "SELECT COUNT(*)
             FROM sesiones_usuario
             WHERE establecimiento_id = $1
               AND usuario_id = $2
               AND cerrada_en IS NULL",
        )
        .bind(establecimiento_id)
        .bind(usuario_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(
            sesiones_abiertas, 1,
            "La revocación del permiso no debe cerrar la sesión"
        );

        let request_logout = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/logout")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_logout = app.clone().oneshot(request_logout).await?;

        assert_eq!(response_logout.status(), StatusCode::OK);

        let bytes = to_bytes(response_logout.into_body(), 4096).await?;
        let logout_json: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(logout_json, serde_json::json!({"status": "sesion_cerrada"}));

        // El token revocado por logout ya no puede acceder a /me.
        let request_despues_logout = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/auth/me")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_despues_logout = app.clone().oneshot(request_despues_logout).await?;

        assert_eq!(response_despues_logout.status(), StatusCode::UNAUTHORIZED);

        // Caso HTTP: logout sin token.
        let request_logout_sin_token = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/logout")
            .body(Body::empty())?;

        let response_logout_sin_token = app.clone().oneshot(request_logout_sin_token).await?;

        assert_eq!(response_logout_sin_token.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_logout_sin_token.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Caso HTTP: logout con token inexistente.
        let token_logout_falso = crate::auth::token::generar_token();

        let request_logout_falso = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/logout")
            .header("authorization", format!("Bearer {token_logout_falso}"))
            .body(Body::empty())?;

        let response_logout_falso = app.clone().oneshot(request_logout_falso).await?;

        assert_eq!(response_logout_falso.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_logout_falso.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Caso HTTP: repetir logout con un token revocado.
        let request_logout_repetido = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/auth/logout")
            .header("authorization", format!("Bearer {nuevo_token}"))
            .body(Body::empty())?;

        let response_logout_repetido = app.clone().oneshot(request_logout_repetido).await?;

        assert_eq!(response_logout_repetido.status(), StatusCode::UNAUTHORIZED);

        let bytes = to_bytes(response_logout_repetido.into_body(), 4096).await?;
        let error: serde_json::Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, serde_json::json!({"error": "token_invalido"}));

        // Verificar el motivo de cierre en PostgreSQL.
        let motivo: String = sqlx::query_scalar(
            "SELECT motivo_cierre
             FROM sesiones_usuario
             WHERE token_hash = $1",
        )
        .bind(hash_token(nuevo_token))
        .fetch_one(&pool)
        .await?;

        assert_eq!(motivo, "logout");

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar los registros incluso si la tarea de prueba falla.
    // Eliminar todas las asignaciones del establecimiento temporal
    // antes de borrar el permiso global y los roles.
    sqlx::query(
        "DELETE FROM rol_permisos
         WHERE establecimiento_id = $1",
    )
    .bind(establecimiento_id)
    .execute(&pool)
    .await?;

    sqlx::query("DELETE FROM permisos WHERE id = $1")
        .bind(permiso_rbac_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM sesiones_usuario WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM usuarios WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE establecimiento_id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    // Limpiar el segundo establecimiento de la prueba multi-tenant.
    sqlx::query("DELETE FROM rol_permisos WHERE establecimiento_id = $1")
        .bind(establecimiento_ajeno_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE establecimiento_id = $1")
        .bind(establecimiento_ajeno_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_ajeno_id)
        .execute(&pool)
        .await?;

    prueba??;

    Ok(())
}
