use super::pin::hash_pin;
use super::service::{ResultadoValidacionPin, autenticar_pin};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn login_pin_postgresql() -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    let pool_prueba = pool.clone();

    let resultado = tokio::spawn(async move {
        let pool = pool_prueba;

        let pin_hash = hash_pin("1234")?;

        sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
            .bind(establecimiento_id)
            .bind("TEST_LOGIN_PIN")
            .execute(&pool)
            .await?;

        sqlx::query(
            "INSERT INTO terminales (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(terminal_id)
        .bind(establecimiento_id)
        .bind("TEST_TERMINAL")
        .execute(&pool)
        .await?;

        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(rol_id)
        .bind(establecimiento_id)
        .bind("TEST_ROL")
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
        .bind("TEST_USUARIO")
        .bind(pin_hash)
        .execute(&pool)
        .await?;

        let incorrecto =
            autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "9999").await?;

        assert_eq!(incorrecto, ResultadoValidacionPin::Incorrecto);

        let correcto =
            autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234").await?;

        assert_eq!(correcto, ResultadoValidacionPin::Correcto);

        let intentos: i32 =
            sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(intentos, 0);

        // Probar cinco intentos fallidos consecutivos.
        for intento in 1..=5 {
            let resultado =
                autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "9999").await?;

            assert_eq!(resultado, ResultadoValidacionPin::Incorrecto);

            let contador: i32 =
                sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                    .bind(usuario_id)
                    .fetch_one(&pool)
                    .await?;

            assert_eq!(contador, intento);
        }

        // El quinto intento debe establecer un bloqueo futuro.
        let bloqueado_hasta: Option<chrono::DateTime<chrono::Utc>> =
            sqlx::query_scalar("SELECT bloqueado_hasta FROM usuarios WHERE id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert!(
            bloqueado_hasta.is_some_and(|fecha| fecha > chrono::Utc::now()),
            "El quinto intento debe bloquear al usuario"
        );

        // Incluso el PIN correcto debe rechazarse durante el bloqueo.
        let resultado =
            autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234").await?;

        assert_eq!(resultado, ResultadoValidacionPin::Bloqueado);

        // Los intentos durante el bloqueo no deben alterar su duración.
        let (contador, bloqueo_actual): (i32, Option<chrono::DateTime<chrono::Utc>>) =
            sqlx::query_as(
                "SELECT intentos_fallidos, bloqueado_hasta
             FROM usuarios WHERE id = $1",
            )
            .bind(usuario_id)
            .fetch_one(&pool)
            .await?;

        assert_eq!(contador, 5);
        assert_eq!(bloqueo_actual, bloqueado_hasta);

        // Simular que transcurrieron los 15 minutos.
        sqlx::query(
            "UPDATE usuarios
             SET bloqueado_hasta = NOW() - INTERVAL '1 minute'
             WHERE id = $1",
        )
        .bind(usuario_id)
        .execute(&pool)
        .await?;

        // Una vez vencido el bloqueo, el PIN correcto debe funcionar.
        let resultado =
            autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "1234").await?;

        assert_eq!(resultado, ResultadoValidacionPin::Correcto);

        let (contador_final, bloqueo_final): (i32, Option<chrono::DateTime<chrono::Utc>>) =
            sqlx::query_as(
                "SELECT intentos_fallidos, bloqueado_hasta
             FROM usuarios WHERE id = $1",
            )
            .bind(usuario_id)
            .fetch_one(&pool)
            .await?;

        assert_eq!(contador_final, 0);
        assert!(bloqueo_final.is_none());

        // Probar seis solicitudes concurrentes con PIN incorrecto.
        // Solo cinco pueden contabilizarse antes del bloqueo.
        let mut tareas = tokio::task::JoinSet::new();

        for _ in 0..6 {
            let pool = pool.clone();

            tareas.spawn(async move {
                autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "9999").await
            });
        }

        let mut incorrectos = 0;
        let mut bloqueados = 0;

        while let Some(resultado) = tareas.join_next().await {
            match resultado?? {
                ResultadoValidacionPin::Incorrecto => incorrectos += 1,
                ResultadoValidacionPin::Bloqueado => bloqueados += 1,
                ResultadoValidacionPin::Correcto => {
                    return Err("Un PIN incorrecto fue aceptado".into());
                }
            }
        }

        assert_eq!(incorrectos, 5);
        assert_eq!(bloqueados, 1);

        let (intentos_concurrentes, bloqueo_concurrente): (
            i32,
            Option<chrono::DateTime<chrono::Utc>>,
        ) = sqlx::query_as(
            "SELECT intentos_fallidos, bloqueado_hasta
             FROM usuarios WHERE id = $1",
        )
        .bind(usuario_id)
        .fetch_one(&pool)
        .await?;

        assert_eq!(intentos_concurrentes, 5);
        assert!(
            bloqueo_concurrente.is_some_and(|fecha| fecha > chrono::Utc::now()),
            "Cinco intentos concurrentes deben bloquear al usuario"
        );

        // Durante el bloqueo, una nueva solicitud no debe
        // incrementar el contador.
        let resultado =
            autenticar_pin(&pool, establecimiento_id, terminal_id, usuario_id, "9999").await?;

        assert_eq!(resultado, ResultadoValidacionPin::Bloqueado);

        let intentos_finales: i32 =
            sqlx::query_scalar("SELECT intentos_fallidos FROM usuarios WHERE id = $1")
                .bind(usuario_id)
                .fetch_one(&pool)
                .await?;

        assert_eq!(intentos_finales, 5);

        Ok::<(), Box<dyn std::error::Error + Send + Sync>>(())
    })
    .await;

    // Limpiar en orden inverso a las claves foráneas.
    let limpieza = async {
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

        Ok::<(), sqlx::Error>(())
    }
    .await;

    limpieza?;
    resultado??;
    Ok(())
}
