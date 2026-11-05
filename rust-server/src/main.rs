mod auth;
mod http;
mod zonas;
use axum::{
    Json, Router,
    routing::{get, post},
};
use serde::Serialize;
use sqlx::PgPool;
use sqlx::postgres::PgPoolOptions;
use std::net::SocketAddr;
use tokio::net::TcpListener;

#[derive(Clone)]
struct AppState {
    db: PgPool,
}

#[derive(Serialize)]
struct HealthResponse {
    status: &'static str,
    service: &'static str,
    version: &'static str,
}

#[derive(Serialize)]
struct DatabaseResponse {
    status: &'static str,
    database: &'static str,
}

async fn health() -> Json<HealthResponse> {
    Json(HealthResponse {
        status: "ok",
        service: "pos-edge",
        version: env!("CARGO_PKG_VERSION"),
    })
}

async fn database_health(
    axum::extract::State(state): axum::extract::State<AppState>,
) -> Result<Json<DatabaseResponse>, axum::http::StatusCode> {
    sqlx::query_scalar::<_, i32>("SELECT 1")
        .fetch_one(&state.db)
        .await
        .map_err(|_| axum::http::StatusCode::SERVICE_UNAVAILABLE)?;

    Ok(Json(DatabaseResponse {
        status: "ok",
        database: "postgresql",
    }))
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    // La contraseña se obtiene del entorno; no se escribe en el código.
    let database_url = std::env::var("DATABASE_URL")?;

    let pool = PgPoolOptions::new()
        .max_connections(5)
        .connect(&database_url)
        .await?;

    let app = Router::new()
        .route("/api/v1/health", get(health))
        .route("/api/v1/health/db", get(database_health))
        .route("/api/v1/auth/login", post(http::auth::login))
        .route("/api/v1/auth/me", get(http::me::me))
        .route("/api/v1/auth/logout", post(http::logout::logout))
        .route(
            "/api/v1/zonas",
            get(http::zonas::listar).post(http::zonas::crear),
        )
        .route(
            "/api/v1/zonas/{id}",
            axum::routing::patch(http::zonas::actualizar),
        )
        .with_state(AppState { db: pool });

    let address = SocketAddr::from(([127, 0, 0, 1], 3001));
    let listener = TcpListener::bind(address).await?;

    println!("POS Edge escuchando en http://{address}");

    axum::serve(listener, app).await?;

    Ok(())
}
