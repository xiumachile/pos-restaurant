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

/// Una solicitud sin token no puede consultar las mesas.
#[tokio::test]
async fn listar_mesas_sin_token_retorna_401()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let app = Router::new()
        .route("/api/v1/mesas", get(crate::http::mesas::listar))
        .with_state(AppState { db: pool.clone() });

    let request = Request::builder()
        .method(Method::GET)
        .uri("/api/v1/mesas")
        .body(Body::empty())?;

    let response = app.oneshot(request).await?;

    assert_eq!(response.status(), StatusCode::UNAUTHORIZED);

    let bytes = to_bytes(response.into_body(), 4096).await?;
    let error: Value = serde_json::from_slice(&bytes)?;

    assert_eq!(error, json!({"error": "token_invalido"}));

    pool.close().await;

    Ok(())
}

/// Una sesión válida sin mesas.ver debe recibir HTTP 403.
#[tokio::test]
async fn listar_mesas_sin_permiso_retorna_403()
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
    let token = generar_token();

    // Crear datos de prueba en una única transacción.
    let mut tx = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)",
    )
    .bind(establecimiento_id)
    .bind("TEST_MESAS_403")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_MESAS")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_MESAS")
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
    .bind("TEST_USUARIO_MESAS")
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

    tx.commit().await?;

    // Ejecutar la solicitud y conservar el resultado para limpiar
    // los datos incluso si alguna comprobación falla.
    let resultado_prueba = async {
        let app = Router::new()
            .route("/api/v1/mesas", get(crate::http::mesas::listar))
            .with_state(AppState { db: pool.clone() });

        let request = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/mesas")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response = app.oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::FORBIDDEN);

        let bytes = to_bytes(response.into_body(), 4096).await?;
        let error: Value = serde_json::from_slice(&bytes)?;

        assert_eq!(error, json!({"error": "permiso_denegado"}));

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpiar únicamente los registros de esta prueba.
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

/// Solo devuelve mesas vigentes del establecimiento autenticado.
#[tokio::test]
async fn listar_mesas_filtra_establecimiento_y_eliminadas()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use crate::auth::token::{generar_token, hash_token};
    use uuid::Uuid;

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let establecimiento_ajeno_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let sesion_id = Uuid::new_v4();

    let mesa_propia_id = Uuid::new_v4();
    let mesa_ajena_id = Uuid::new_v4();
    let mesa_eliminada_id = Uuid::new_v4();

    let token = generar_token();

    // Los datos se confirman para que el endpoint pueda consultarlos.
    let mut tx = pool.begin().await?;

    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2), ($3, $4)",
    )
    .bind(establecimiento_id)
    .bind("TEST_MESAS_ESTABLECIMIENTO_A")
    .bind(establecimiento_ajeno_id)
    .bind("TEST_MESAS_ESTABLECIMIENTO_B")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_MESAS_LISTAR")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_MESAS_LISTAR")
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
    .bind("TEST_USUARIO_MESAS_LISTAR")
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
        "INSERT INTO mesas
         (id, establecimiento_id, numero, capacidad)
         VALUES ($1, $2, $3, $4)",
    )
    .bind(mesa_propia_id)
    .bind(establecimiento_id)
    .bind("MESA_01")
    .bind(4)
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO mesas
         (id, establecimiento_id, numero, capacidad)
         VALUES ($1, $2, $3, $4)",
    )
    .bind(mesa_ajena_id)
    .bind(establecimiento_ajeno_id)
    .bind("MESA_02")
    .bind(6)
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO mesas
         (id, establecimiento_id, numero, deleted_at)
         VALUES ($1, $2, $3, NOW())",
    )
    .bind(mesa_eliminada_id)
    .bind(establecimiento_id)
    .bind("MESA_03")
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    let resultado_prueba = async {
        let concedido = crate::auth::permissions::conceder_permiso(
            &pool,
            establecimiento_id,
            rol_id,
            "mesas.ver",
        )
        .await?;

        assert!(concedido, "Debe concederse mesas.ver");

        let app = Router::new()
            .route("/api/v1/mesas", get(crate::http::mesas::listar))
            .with_state(AppState { db: pool.clone() });

        let request = Request::builder()
            .method(Method::GET)
            .uri("/api/v1/mesas")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())?;

        let response = app.oneshot(request).await?;

        assert_eq!(response.status(), StatusCode::OK);

        let bytes = to_bytes(response.into_body(), 16384).await?;
        let mesas: Value = serde_json::from_slice(&bytes)?;

        let lista = mesas
            .as_array()
            .expect("La respuesta debe ser un arreglo JSON");

        assert_eq!(
            lista.len(),
            1,
            "Solo debe aparecer la mesa vigente del establecimiento"
        );

        let mesa = &lista[0];

        assert_eq!(mesa["id"], json!(mesa_propia_id));
        assert_eq!(mesa["numero"], "MESA_01");
        assert_eq!(mesa["capacidad"], 4);
        assert_eq!(mesa["zona_id"], Value::Null);
        assert_eq!(mesa["activo"], true);
        assert_eq!(mesa["version_configuracion"], 1);

        assert!(
            mesa.get("establecimiento_id").is_none(),
            "No debe exponerse el establecimiento_id"
        );

        assert!(
            lista.iter().all(|mesa| mesa["id"] != json!(mesa_ajena_id)),
            "No deben aparecer mesas de otros establecimientos"
        );

        assert!(
            lista.iter().all(|mesa| mesa["id"] != json!(mesa_eliminada_id)),
            "No deben aparecer mesas eliminadas"
        );

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpiar exclusivamente los datos de esta prueba.
    sqlx::query("DELETE FROM mesas WHERE id = ANY($1)")
        .bind(vec![mesa_propia_id, mesa_ajena_id, mesa_eliminada_id])
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
