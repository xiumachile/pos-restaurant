use sqlx::PgPool;
use std::time::Duration;
use tokio::time::timeout;
use uuid::Uuid;

#[tokio::test]
async fn bloqueo_terminal_share_impide_update_concurrente()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();

    // Preparar datos temporales.
    sqlx::query(
        "INSERT INTO establecimientos (id, nombre)
         VALUES ($1, $2)",
    )
    .bind(establecimiento_id)
    .bind("TEST_BLOQUEO_TERMINAL")
    .execute(&pool)
    .await?;

    let resultado = async {
        sqlx::query(
            "INSERT INTO terminales (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("Terminal de prueba")
        .execute(&pool)
        .await?;

        let mut tx_lectura = pool.begin().await?;
        let mut tx_actualizacion = pool.begin().await?;

        // Transacción A: bloquear la terminal para lectura.
        sqlx::query(
            "SELECT id FROM terminales
             WHERE id = $1
             FOR SHARE",
        )
        .bind(terminal_id)
        .fetch_one(&mut *tx_lectura)
        .await?;

        // Transacción B: intentar obtener bloqueo exclusivo.
        let intento = timeout(
            Duration::from_millis(300),
            sqlx::query(
                "SELECT id FROM terminales
                 WHERE id = $1
                 FOR UPDATE",
            )
            .bind(terminal_id)
            .fetch_one(&mut *tx_actualizacion),
        )
        .await;

        assert!(
            intento.is_err(),
            "FOR UPDATE no debería completarse mientras existe FOR SHARE"
        );

        // Liberar el bloqueo de lectura.
        tx_lectura.rollback().await?;

        // Verificar que ahora se puede obtener FOR UPDATE.
        sqlx::query(
            "SELECT id FROM terminales
             WHERE id = $1
             FOR UPDATE",
        )
        .bind(terminal_id)
        .fetch_one(&mut *tx_actualizacion)
        .await?;

        tx_actualizacion.rollback().await?;

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    }
    .await;

    // Limpiar incluso si alguna consulta devuelve un error.
    sqlx::query("DELETE FROM terminales WHERE id = $1")
        .bind(terminal_id)
        .execute(&pool)
        .await?;

    sqlx::query("DELETE FROM establecimientos WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&pool)
        .await?;

    resultado?;

    Ok(())
}
