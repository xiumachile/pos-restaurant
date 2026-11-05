use axum::{
    Router,
    body::{Body, to_bytes},
    http::{Method, Request, StatusCode},
    routing::get,
};
use serde_json::{Value, json};
use sqlx::PgPool;
use tower::ServiceExt;

use crate::AppState;

#[tokio::test]
async fn listar_zonas_sin_token_retorna_401() -> Result<(), Box<dyn std::error::Error + Send + Sync>>
{
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let app = Router::new()
        .route("/api/v1/zonas", get(crate::http::zonas::listar))
        .with_state(AppState { db: pool.clone() });

    let request = Request::builder()
        .method(Method::GET)
        .uri("/api/v1/zonas")
        .body(Body::empty())?;

    let response = app.oneshot(request).await?;

    assert_eq!(response.status(), StatusCode::UNAUTHORIZED);

    let bytes = to_bytes(response.into_body(), 4096).await?;
    let error: Value = serde_json::from_slice(&bytes)?;

    assert_eq!(error, json!({"error": "token_invalido"}));

    pool.close().await;

    Ok(())
}

#[tokio::test]
async fn listar_zonas_sin_permiso_retorna_403()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use crate::auth::token::{generar_token, hash_token};
    use uuid::Uuid;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let sesion_id = Uuid::new_v4();

    let establecimiento_ajeno_id = Uuid::new_v4();
    let zona_propia_id = Uuid::new_v4();
    let zona_ajena_id = Uuid::new_v4();
    let zona_eliminada_id = Uuid::new_v4();

    let token = generar_token();

    let mut tx = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2)",
    )
    .bind(establecimiento_id)
    .bind("TEST_ZONAS_403")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_ZONAS")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_ZONAS")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind("TEST_USUARIO_ZONAS")
    .bind("hash_de_prueba")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() + INTERVAL '1 hour')",
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(hash_token(&token))
    .execute(&mut *tx)
    .await?;

    // Segundo establecimiento: no pertenece a la sesión.
    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2)",
    )
    .bind(establecimiento_ajeno_id)
    .bind("TEST_ZONAS_ESTABLECIMIENTO_B")
    .execute(&mut *tx)
    .await?;

    // Zona vigente del establecimiento autenticado.
    sqlx::query(
        "INSERT INTO zonas
         (id, establecimiento_id, codigo, nombre_traducciones)
         VALUES ($1, $2, $3, $4)",
    )
    .bind(zona_propia_id)
    .bind(establecimiento_id)
    .bind("SALON_A")
    .bind(json!({"es": "Salón A", "zh": "A厅"}))
    .execute(&mut *tx)
    .await?;

    // Zona vigente de otro establecimiento.
    sqlx::query(
        "INSERT INTO zonas
         (id, establecimiento_id, codigo, nombre_traducciones)
         VALUES ($1, $2, $3, $4)",
    )
    .bind(zona_ajena_id)
    .bind(establecimiento_ajeno_id)
    .bind("SALON_B")
    .bind(json!({"es": "Salón B", "zh": "B厅"}))
    .execute(&mut *tx)
    .await?;

    // Zona eliminada lógicamente del establecimiento autenticado.
    sqlx::query(
        "INSERT INTO zonas
         (id, establecimiento_id, codigo, nombre_traducciones, deleted_at)
         VALUES ($1, $2, $3, $4, NOW())",
    )
    .bind(zona_eliminada_id)
    .bind(establecimiento_id)
    .bind("SALON_ELIMINADO")
    .bind(json!({"es": "Salón eliminado"}))
    .execute(&mut *tx)
    .await?;

    // Confirmar los datos para que el router pueda consultarlos.
    tx.commit().await?;

    // Ejecutar las comprobaciones sin interrumpir la limpieza.
    let resultado_prueba = async {
        let app = Router::new()
            .route("/api/v1/zonas", get(crate::http::zonas::listar))
            .with_state(AppState { db: pool.clone() });

        let request = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response = app.clone().oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(response.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "permiso_denegado"}));

        // Una sesión válida sin mesas.configurar no puede crear zonas.
        let app_creacion = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_creacion = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_SIN_PERMISO",
                    "nombre_traducciones": {
                        "es": "Zona sin permiso",
                        "zh": "无权限区域"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_creacion = app_creacion.oneshot(solicitud_creacion).await?;

        assert_eq!(respuesta_creacion.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(respuesta_creacion.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "permiso_denegado"}));

        // Una sesión válida sin mesas.configurar no puede crear zonas.
        let app_creacion = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_creacion = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_SIN_PERMISO",
                    "nombre_traducciones": {
                        "es": "Zona sin permiso",
                        "zh": "无权限区域"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_creacion = app_creacion.oneshot(solicitud_creacion).await?;

        assert_eq!(respuesta_creacion.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(respuesta_creacion.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "permiso_denegado"}));

        // Autorizar la creación de zonas.
        let concedido = crate::auth::permissions::conceder_permiso(
            &pool,
            establecimiento_id,
            rol_id,
            "mesas.configurar",
        )
        .await?;

        assert!(concedido, "Debe concederse mesas.configurar");

        // Crear una zona real con la misma sesión.
        let app_creacion_autorizada = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_autorizada = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_CREADA_TEST",
                    "nombre_traducciones": {
                        "es": "Zona creada",
                        "zh": "已创建区域"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_autorizada = app_creacion_autorizada
            .oneshot(solicitud_autorizada)
            .await?;

        assert_eq!(respuesta_autorizada.status(), StatusCode::CREATED);

        let bytes = to_bytes(respuesta_autorizada.into_body(), 4096).await?;
        let zona_creada: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(zona_creada["codigo"], "ZONA_CREADA_TEST");
        assert_eq!(zona_creada["version"], 1);
        assert_eq!(zona_creada["color"], "#64748b");
        assert_eq!(zona_creada["activo"], true);
        assert_eq!(zona_creada["nombre_traducciones"]["es"], "Zona creada");
        assert_eq!(zona_creada["nombre_traducciones"]["zh"], "已创建区域");

        let zona_creada_id = uuid::Uuid::parse_str(
            zona_creada["id"]
                .as_str()
                .expect("La zona debe incluir un UUID"),
        )?;

        // Comprobar que PostgreSQL la asignó al establecimiento autenticado.
        let establecimiento_guardado: uuid::Uuid =
            sqlx::query_scalar("SELECT establecimiento_id FROM zonas WHERE id = $1")
                .bind(zona_creada_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(establecimiento_guardado, establecimiento_id);

        // El mismo código no puede repetirse en el establecimiento.
        let app_duplicado = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_duplicada = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_CREADA_TEST",
                    "nombre_traducciones": {
                        "es": "Zona duplicada",
                        "zh": "重复区域"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_duplicada = app_duplicado.oneshot(solicitud_duplicada).await?;

        assert_eq!(
            respuesta_duplicada.status(),
            StatusCode::CONFLICT,
            "Un código duplicado debe devolver HTTP 409"
        );

        let bytes = to_bytes(respuesta_duplicada.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "codigo_duplicado"}));

        // El intento duplicado no debe crear otra fila.
        let cantidad: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM zonas
             WHERE establecimiento_id = $1 AND codigo = $2",
        )
        .bind(establecimiento_id)
        .bind("ZONA_CREADA_TEST")
        .fetch_one(&pool)
        .await?;

        assert_eq!(cantidad, 1, "Debe existir una sola zona con ese código");

        // Ambas traducciones son obligatorias: es y zh.
        let app_sin_chino = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_sin_chino = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_SIN_CHINO_TEST",
                    "nombre_traducciones": {
                        "es": "Zona sin traducción china"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_sin_chino = app_sin_chino.oneshot(solicitud_sin_chino).await?;

        assert_eq!(
            respuesta_sin_chino.status(),
            StatusCode::UNPROCESSABLE_ENTITY
        );

        let bytes = to_bytes(respuesta_sin_chino.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "datos_invalidos"}));

        // Una solicitud inválida no debe persistir ninguna zona.
        let cantidad_invalida: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM zonas
             WHERE establecimiento_id = $1 AND codigo = $2",
        )
        .bind(establecimiento_id)
        .bind("ZONA_SIN_CHINO_TEST")
        .fetch_one(&pool)
        .await?;

        assert_eq!(cantidad_invalida, 0);

        // La traducción española también es obligatoria.
        let app_sin_espanol = Router::new()
            .route(
                "/api/v1/zonas",
                axum::routing::post(crate::http::zonas::crear),
            )
            .with_state(AppState { db: pool.clone() });

        let solicitud_sin_espanol = Request::builder()
            .method(Method::POST)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "codigo": "ZONA_SIN_ESPANOL_TEST",
                    "nombre_traducciones": {
                        "zh": "没有西班牙语名称的区域"
                    }
                })
                .to_string(),
            ))?;

        let respuesta_sin_espanol = app_sin_espanol.oneshot(solicitud_sin_espanol).await?;

        assert_eq!(
            respuesta_sin_espanol.status(),
            StatusCode::UNPROCESSABLE_ENTITY
        );

        let bytes = to_bytes(respuesta_sin_espanol.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "datos_invalidos"}));

        let cantidad_sin_espanol: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM zonas
             WHERE establecimiento_id = $1 AND codigo = $2",
        )
        .bind(establecimiento_id)
        .bind("ZONA_SIN_ESPANOL_TEST")
        .fetch_one(&pool)
        .await?;

        assert_eq!(
            cantidad_sin_espanol, 0,
            "No debe guardarse una zona sin traducción española"
        );

        // Conceder mesas.ver al rol del establecimiento.
        let concedido = crate::auth::permissions::conceder_permiso(
            &pool,
            establecimiento_id,
            rol_id,
            "mesas.ver",
        )
        .await?;

        assert!(concedido, "Debe concederse mesas.ver");

        // El mismo token debe obtener acceso inmediatamente.
        let request_autorizado = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/zonas")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response_autorizada = app.oneshot(request_autorizado).await?;

        assert_eq!(response_autorizada.status(), StatusCode::OK);

        let bytes = to_bytes(response_autorizada.into_body(), 4096).await?;
        let zonas: Value = serde_json::from_slice(&bytes)?;

        let lista = zonas
            .as_array()
            .expect("La respuesta debe ser un arreglo JSON");

        assert_eq!(
            lista.len(),
            2,
            "Deben aparecer las dos zonas vigentes del establecimiento"
        );

        let zona_propia = lista
            .iter()
            .find(|zona| zona["id"] == json!(zona_propia_id))
            .expect("Debe aparecer SALON_A");

        assert_eq!(zona_propia["codigo"], "SALON_A");
        assert_eq!(zona_propia["nombre_traducciones"]["es"], "Salón A");

        let zona_nueva = lista
            .iter()
            .find(|zona| zona["id"] == json!(zona_creada_id))
            .expect("Debe aparecer la zona recién creada");

        assert_eq!(zona_nueva["codigo"], "ZONA_CREADA_TEST");
        assert_eq!(zona_nueva["version"], 1);

        assert!(
            lista.iter().all(|zona| zona["id"] != json!(zona_ajena_id)),
            "No deben filtrarse datos de otro establecimiento"
        );

        assert!(
            lista
                .iter()
                .all(|zona| zona["id"] != json!(zona_eliminada_id)),
            "No deben aparecer zonas eliminadas"
        );
        // PATCH: ninguna zona ajena, eliminada o inexistente
        // debe ser accesible desde el establecimiento autenticado.
        for zona_no_accesible in [zona_ajena_id, zona_eliminada_id, uuid::Uuid::new_v4()] {
            let app_patch = Router::new()
                .route(
                    "/api/v1/zonas/{id}",
                    axum::routing::patch(crate::http::zonas::actualizar),
                )
                .with_state(AppState { db: pool.clone() });

            let solicitud = Request::builder()
                .method(Method::PATCH)
                .uri(format!("/api/v1/zonas/{zona_no_accesible}"))
                .header("authorization", format!("Bearer {token}"))
                .header("content-type", "application/json")
                .body(Body::from(
                    json!({
                        "version": 1,
                        "color": "#EF4444"
                    })
                    .to_string(),
                ))?;

            let respuesta = app_patch.oneshot(solicitud).await?;

            assert_eq!(
                respuesta.status(),
                StatusCode::NOT_FOUND,
                "Una zona ajena, eliminada o inexistente debe devolver HTTP 404"
            );

            let bytes = to_bytes(respuesta.into_body(), 4096).await?;
            let error: Value = serde_json::from_slice(&bytes)?;

            assert_eq!(error, json!({"error": "zona_no_encontrada"}));
        }

        // Confirmar que las zonas protegidas no fueron modificadas.
        for zona_protegida in [zona_ajena_id, zona_eliminada_id] {
            let (color, version): (String, i32) =
                sqlx::query_as("SELECT color, version FROM zonas WHERE id = $1")
                    .bind(zona_protegida)
                    .fetch_one(&pool)
                    .await?;

            assert_eq!(color, "#64748b");
            assert_eq!(version, 1);
        }

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    sqlx::query(
        "DELETE FROM zonas
         WHERE establecimiento_id = $1
           AND codigo = 'ZONA_CREADA_TEST'",
    )
    .bind(establecimiento_id)
    .execute(&pool)
    .await?;

    // Eliminar primero las zonas, respetando sus claves foráneas.
    sqlx::query("DELETE FROM zonas WHERE id = ANY($1)")
        .bind(vec![zona_propia_id, zona_ajena_id, zona_eliminada_id])
        .execute(&pool)
        .await?;

    // Eliminar únicamente los datos creados por esta prueba.
    sqlx::query("DELETE FROM sesiones_usuario WHERE id = $1")
        .bind(sesion_id)
        .execute(&pool)
        .await?;

    sqlx::query(
        "DELETE FROM rol_permisos
         WHERE establecimiento_id = $1 AND rol_id = $2",
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .execute(&pool)
    .await?;

    sqlx::query("DELETE FROM usuarios WHERE id = $1")
        .bind(usuario_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE id = $1")
        .bind(rol_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE id = $1")
        .bind(terminal_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_ajeno_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    pool.close().await;

    resultado_prueba?;

    Ok(())
}

#[tokio::test]
async fn crear_zona_sin_token_retorna_401() -> Result<(), Box<dyn std::error::Error + Send + Sync>>
{
    use axum::routing::post;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let app = Router::new()
        .route("/api/v1/zonas", post(crate::http::zonas::crear))
        .with_state(AppState { db: pool.clone() });

    let request = Request::builder()
        .method(Method::POST)
        .uri("/api/v1/zonas")
        .header("content-type", "application/json")
        .body(Body::from(
            json!({
                "codigo": "SALON_PRINCIPAL",
                "nombre_traducciones": {
                    "es": "Salón principal",
                    "zh": "主厅"
                }
            })
            .to_string(),
        ))?;

    let response = app.oneshot(request).await?;

    assert_eq!(response.status(), StatusCode::UNAUTHORIZED);

    let bytes = to_bytes(response.into_body(), 4096).await?;
    let error: Value = serde_json::from_slice(&bytes)?;

    assert_eq!(error, json!({"error": "token_invalido"}));

    pool.close().await;

    Ok(())
}

#[tokio::test]
async fn actualizar_zona_sin_token_retorna_401()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use axum::routing::patch;
    use uuid::Uuid;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let app = Router::new()
        .route("/api/v1/zonas/{id}", patch(crate::http::zonas::actualizar))
        .with_state(AppState { db: pool.clone() });

    let zona_id = Uuid::new_v4();

    let request = Request::builder()
        .method(Method::PATCH)
        .uri(format!("/api/v1/zonas/{zona_id}"))
        .header("content-type", "application/json")
        .body(Body::from(
            json!({
                "version": 1,
                "color": "#22C55E"
            })
            .to_string(),
        ))?;

    let response = app.oneshot(request).await?;

    assert_eq!(response.status(), StatusCode::UNAUTHORIZED);

    let bytes = to_bytes(response.into_body(), 4096).await?;
    let error: Value = serde_json::from_slice(&bytes)?;

    assert_eq!(error, json!({"error": "token_invalido"}));

    pool.close().await;

    Ok(())
}

#[tokio::test]
async fn actualizar_zona_sin_permiso_retorna_403()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use crate::auth::token::{generar_token, hash_token};
    use axum::routing::patch;
    use uuid::Uuid;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let sesion_id = Uuid::new_v4();
    let zona_id = Uuid::new_v4();
    let token = generar_token();

    let mut tx = pool.begin().await?;

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_ZONAS_PATCH_403")
        .execute(&mut *tx)
        .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_PATCH")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_PATCH")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind("TEST_USUARIO_PATCH")
    .bind("hash_de_prueba")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() + INTERVAL '1 hour')",
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(hash_token(&token))
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO zonas
         (id, establecimiento_id, codigo, nombre_traducciones)
         VALUES ($1, $2, $3, $4)",
    )
    .bind(zona_id)
    .bind(establecimiento_id)
    .bind("SALON_PATCH_403")
    .bind(json!({"es": "Salón de prueba", "zh": "测试厅"}))
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    // Ejecutar la comprobación y conservar su resultado
    // para limpiar los registros incluso si falla una aserción.
    let resultado_prueba = async {
        let app = Router::new()
            .route("/api/v1/zonas/{id}", patch(crate::http::zonas::actualizar))
            .with_state(AppState { db: pool.clone() });

        let request = Request::builder()
            .method(Method::PATCH)
            .uri(format!("/api/v1/zonas/{zona_id}"))
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "version": 1,
                    "color": "#22C55E"
                })
                .to_string(),
            ))?;

        let response = app.oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(response.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "permiso_denegado"}));

        // Una solicitud rechazada no debe modificar la zona.
        let (color, version): (String, i32) =
            sqlx::query_as("SELECT color, version FROM zonas WHERE id = $1")
                .bind(zona_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(color, "#64748b");
        assert_eq!(version, 1);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpieza limitada a los UUID creados por esta prueba.
    sqlx::query("DELETE FROM zonas WHERE id = $1")
        .bind(zona_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM sesiones_usuario WHERE id = $1")
        .bind(sesion_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM usuarios WHERE id = $1")
        .bind(usuario_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE id = $1")
        .bind(rol_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE id = $1")
        .bind(terminal_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    pool.close().await;

    resultado_prueba?;

    Ok(())
}

#[tokio::test]
async fn actualizar_zona_valida_retorna_200() -> Result<(), Box<dyn std::error::Error + Send + Sync>>
{
    use crate::auth::token::{generar_token, hash_token};
    use axum::routing::patch;
    use uuid::Uuid;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let sesion_id = Uuid::new_v4();
    let zona_id = Uuid::new_v4();
    let token = generar_token();

    let mut tx = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2)",
    )
    .bind(establecimiento_id)
    .bind("TEST_ZONAS_PATCH_200")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_PATCH_200")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_PATCH_200")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind("TEST_USUARIO_PATCH_200")
    .bind("hash_de_prueba")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() + INTERVAL '1 hour')",
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(hash_token(&token))
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO zonas
         (id, establecimiento_id, codigo, nombre_traducciones,
          color, nivel_piso, orden, activo)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8)",
    )
    .bind(zona_id)
    .bind(establecimiento_id)
    .bind("SALON_PATCH_200")
    .bind(json!({"es": "Salón principal", "zh": "主厅"}))
    .bind("#64748b")
    .bind(2_i32)
    .bind(5_i32)
    .bind(true)
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    let resultado_prueba = async {
        let concedido = crate::auth::permissions::conceder_permiso(
            &pool,
            establecimiento_id,
            rol_id,
            "mesas.configurar",
        )
        .await?;

        assert!(concedido);

        let app = Router::new()
            .route("/api/v1/zonas/{id}", patch(crate::http::zonas::actualizar))
            .with_state(AppState { db: pool.clone() });

        let request = Request::builder()
            .method(Method::PATCH)
            .uri(format!("/api/v1/zonas/{zona_id}"))
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "version": 1,
                    "color": "#22C55E"
                })
                .to_string(),
            ))?;

        let response = app.oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::OK);

        let bytes = to_bytes(response.into_body(), 4096).await?;
        let zona: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(zona["id"], json!(zona_id));
        assert_eq!(zona["codigo"], "SALON_PATCH_200");
        assert_eq!(zona["color"], "#22C55E");
        assert_eq!(zona["version"], 2);
        assert_eq!(zona["nivel_piso"], 2);
        assert_eq!(zona["orden"], 5);
        // Simular una segunda terminal que intenta actualizar
        // utilizando la versión 1, ya reemplazada por la versión 2.
        let app_conflicto = Router::new()
            .route("/api/v1/zonas/{id}", patch(crate::http::zonas::actualizar))
            .with_state(AppState { db: pool.clone() });

        let solicitud_obsoleta = Request::builder()
            .method(Method::PATCH)
            .uri(format!("/api/v1/zonas/{zona_id}"))
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                json!({
                    "version": 1,
                    "color": "#EF4444"
                })
                .to_string(),
            ))?;

        let respuesta_conflicto = app_conflicto.oneshot(solicitud_obsoleta).await?;

        assert_eq!(
            respuesta_conflicto.status(),
            StatusCode::CONFLICT,
            "Una versión obsoleta debe devolver HTTP 409"
        );

        let bytes = to_bytes(respuesta_conflicto.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "version_conflicto"}));

        // Verificar que el intento rechazado no sobrescribió
        // la actualización válida de la primera terminal.
        let (color_final, version_final): (String, i32) =
            sqlx::query_as("SELECT color, version FROM zonas WHERE id = $1")
                .bind(zona_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(color_final, "#22C55E");
        assert_eq!(version_final, 2);
// PATCH: las solicitudes inválidas deben devolver HTTP 422
// sin modificar la zona existente.
let solicitudes_invalidas = [
    // Campo inmutable.
    json!({
        "version": 2,
        "codigo": "CODIGO_MODIFICADO",
        "color": "#EF4444"
    }),
    // null explícito.
    json!({
        "version": 2,
        "color": null
    }),
    // Solicitud sin cambios.
    json!({
        "version": 2
    }),
    // Versión inválida.
    json!({
        "version": 0,
        "color": "#EF4444"
    }),
    // Traducción china obligatoria.
    json!({
        "version": 2,
        "nombre_traducciones": {
            "es": "Salón actualizado"
        }
    }),
    // Color inválido.
    json!({
        "version": 2,
        "color": "rojo"
    }),
];

for datos in solicitudes_invalidas {
    let app_invalida = Router::new()
        .route(
            "/api/v1/zonas/{id}",
            patch(crate::http::zonas::actualizar),
        )
        .with_state(AppState { db: pool.clone() });

    let solicitud = Request::builder()
        .method(Method::PATCH)
        .uri(format!("/api/v1/zonas/{zona_id}"))
        .header("authorization", format!("Bearer {token}"))
        .header("content-type", "application/json")
        .body(Body::from(datos.to_string()))?;

    let respuesta = app_invalida.oneshot(solicitud).await?;

    assert_eq!(
        respuesta.status(),
        StatusCode::UNPROCESSABLE_ENTITY,
        "La solicitud inválida debe devolver HTTP 422: {datos}"
    );

    let bytes = to_bytes(respuesta.into_body(), 4096).await?;
    let error: Value = serde_json::from_slice(&bytes)?;

    assert_eq!(error, json!({"error": "datos_invalidos"}));
}

// Confirmar que ninguna solicitud inválida alteró PostgreSQL.
let (codigo_final, color_final, version_final): (String, String, i32) =
    sqlx::query_as(
        "SELECT codigo, color, version
         FROM zonas WHERE id = $1",
    )
    .bind(zona_id)
    .fetch_one(&pool)
    .await?;

assert_eq!(codigo_final, "SALON_PATCH_200");
assert_eq!(color_final, "#22C55E");
assert_eq!(version_final, 2);
        assert_eq!(zona["activo"], true);
        assert_eq!(zona["nombre_traducciones"]["es"], "Salón principal");
        assert_eq!(zona["nombre_traducciones"]["zh"], "主厅");

        let (codigo, color, version, nivel_piso, orden): (String, String, i32, i32, i32) =
            sqlx::query_as(
                "SELECT codigo, color, version, nivel_piso, orden
             FROM zonas WHERE id = $1",
            )
            .bind(zona_id)
            .fetch_one(&pool)
            .await?;

        assert_eq!(codigo, "SALON_PATCH_200");
        assert_eq!(color, "#22C55E");
        assert_eq!(version, 2);
        assert_eq!(nivel_piso, 2);
        assert_eq!(orden, 5);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpiar exclusivamente los registros creados por esta prueba.
    sqlx::query("DELETE FROM zonas WHERE id = $1")
        .bind(zona_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM sesiones_usuario WHERE id = $1")
        .bind(sesion_id)
        .execute(&pool)
        .await?;

    sqlx::query(
        "DELETE FROM rol_permisos
         WHERE establecimiento_id = $1 AND rol_id = $2",
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .execute(&pool)
    .await?;

    sqlx::query("DELETE FROM usuarios WHERE id = $1")
        .bind(usuario_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM roles WHERE id = $1")
        .bind(rol_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM terminales WHERE id = $1")
        .bind(terminal_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    pool.close().await;

    resultado_prueba?;

    Ok(())
}
