use super::session::{cerrar_sesion_vencida, crear_sesion_usuario, usuario_tiene_sesion_abierta};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn cerrar_sesion_vencida_postgresql() -> Result<(), Box<dyn std::error::Error + Send + Sync>>
{
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;
    let mut tx = pool.begin().await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();
    let otro_usuario_id = Uuid::new_v4();
    let sesion_id = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("TEST_SESION_VENCIDA")
        .execute(&mut *tx)
        .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("TEST_TERMINAL_SESION")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("TEST_ROL_SESION")
    .execute(&mut *tx)
    .await?;

    for id in [usuario_id, otro_usuario_id] {
        sqlx::query(
            "INSERT INTO usuarios
             (id, establecimiento_id, rol_id, nombre, pin_hash)
             VALUES ($1, $2, $3, $4, $5)",
        )
        .bind(id)
        .bind(establecimiento_id)
        .bind(rol_id)
        .bind("TEST_USUARIO_SESION")
        .bind("hash_de_prueba")
        .execute(&mut *tx)
        .await?;
    }

    // Caso 1: sesión vencida.
    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, iniciada_en, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() - INTERVAL '2 hours',
                 NOW() - INTERVAL '1 hour')",
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(format!("test-token-{}", Uuid::new_v4()))
    .execute(&mut *tx)
    .await?;

    let cerrada = cerrar_sesion_vencida(&mut tx, establecimiento_id, usuario_id).await?;

    assert!(cerrada, "La sesión vencida debe cerrarse");

    let (cerrada_en, motivo): (Option<chrono::DateTime<chrono::Utc>>, Option<String>) =
        sqlx::query_as(
            "SELECT cerrada_en, motivo_cierre
             FROM sesiones_usuario WHERE id = $1",
        )
        .bind(sesion_id)
        .fetch_one(&mut *tx)
        .await?;

    assert!(cerrada_en.is_some());
    assert_eq!(motivo.as_deref(), Some("expirada"));

    // Caso 2: sesión vigente del mismo usuario.
    let sesion_vigente_id = Uuid::new_v4();

    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() + INTERVAL '1 hour')",
    )
    .bind(sesion_vigente_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(format!("test-token-{}", Uuid::new_v4()))
    .execute(&mut *tx)
    .await?;

    let cerrada = cerrar_sesion_vencida(&mut tx, establecimiento_id, usuario_id).await?;

    assert!(!cerrada, "Una sesión vigente no debe cerrarse");

    // Caso 3: sesión vencida de otro usuario.
    let sesion_ajena_id = Uuid::new_v4();

    sqlx::query(
        "INSERT INTO sesiones_usuario
         (id, establecimiento_id, usuario_id, terminal_id,
          token_hash, iniciada_en, expira_en)
         VALUES ($1, $2, $3, $4, $5,
                 NOW() - INTERVAL '2 hours',
                 NOW() - INTERVAL '1 hour')",
    )
    .bind(sesion_ajena_id)
    .bind(establecimiento_id)
    .bind(otro_usuario_id)
    .bind(terminal_id)
    .bind(format!("test-token-{}", Uuid::new_v4()))
    .execute(&mut *tx)
    .await?;

    let cerrada = cerrar_sesion_vencida(&mut tx, establecimiento_id, usuario_id).await?;

    assert!(!cerrada);

    let ajena_cerrada: Option<chrono::DateTime<chrono::Utc>> =
        sqlx::query_scalar("SELECT cerrada_en FROM sesiones_usuario WHERE id = $1")
            .bind(sesion_ajena_id)
            .fetch_one(&mut *tx)
            .await?;

    assert!(
        ajena_cerrada.is_none(),
        "La sesión de otro usuario no debe modificarse"
    );

    // Caso 4: impedir dos sesiones abiertas del mismo usuario.
    //
    // Usamos un SAVEPOINT para que el error esperado de PostgreSQL
    // no invalide toda la transacción de prueba.
    sqlx::query("SAVEPOINT prueba_sesion_duplicada")
        .execute(&mut *tx)
        .await?;

    let token_duplicado = super::token::generar_token();
    let hash_duplicado = super::token::hash_token(&token_duplicado);

    let duplicada = crear_sesion_usuario(
        &mut tx,
        establecimiento_id,
        usuario_id,
        terminal_id,
        &hash_duplicado,
        60,
    )
    .await;

    assert!(
        duplicada.is_err(),
        "PostgreSQL debe rechazar una segunda sesión abierta"
    );

    sqlx::query("ROLLBACK TO SAVEPOINT prueba_sesion_duplicada")
        .execute(&mut *tx)
        .await?;

    sqlx::query("RELEASE SAVEPOINT prueba_sesion_duplicada")
        .execute(&mut *tx)
        .await?;

    // Caso 5: cerrar la sesión vigente y crear una nueva.
    sqlx::query(
        "UPDATE sesiones_usuario
         SET cerrada_en = GREATEST(NOW(), iniciada_en),
             motivo_cierre = 'prueba'
         WHERE id = $1",
    )
    .bind(sesion_vigente_id)
    .execute(&mut *tx)
    .await?;

    let token = super::token::generar_token();
    let token_hash = super::token::hash_token(&token);

    let (nueva_sesion_id, expira_en) = crear_sesion_usuario(
        &mut tx,
        establecimiento_id,
        usuario_id,
        terminal_id,
        &token_hash,
        60,
    )
    .await?;

    assert!(
        expira_en > chrono::Utc::now(),
        "La nueva sesión debe tener una expiración futura"
    );

    let (hash_guardado, establecimiento_guardado, usuario_guardado, terminal_guardada): (
        String,
        Uuid,
        Uuid,
        Uuid,
    ) = sqlx::query_as(
        "SELECT token_hash, establecimiento_id, usuario_id, terminal_id
             FROM sesiones_usuario WHERE id = $1",
    )
    .bind(nueva_sesion_id)
    .fetch_one(&mut *tx)
    .await?;

    assert_eq!(hash_guardado, token_hash);
    assert_ne!(hash_guardado, token);
    assert_eq!(establecimiento_guardado, establecimiento_id);
    assert_eq!(usuario_guardado, usuario_id);
    assert_eq!(terminal_guardada, terminal_id);

    // Caso 6: validar límites de duración.
    for duracion in [-1_i64, 0, 1441] {
        let token = super::token::generar_token();
        let hash = super::token::hash_token(&token);

        let resultado = crear_sesion_usuario(
            &mut tx,
            establecimiento_id,
            otro_usuario_id,
            terminal_id,
            &hash,
            duracion,
        )
        .await;

        assert!(resultado.is_err(), "La duración {duracion} debe rechazarse");
    }

    // Las duraciones inválidas no deben crear sesiones nuevas.
    let sesiones_abiertas: i64 = sqlx::query_scalar(
        "SELECT COUNT(*)
         FROM sesiones_usuario
         WHERE usuario_id = $1
           AND cerrada_en IS NULL",
    )
    .bind(otro_usuario_id)
    .fetch_one(&mut *tx)
    .await?;

    // El otro usuario conserva únicamente su sesión vencida
    // creada en el caso 3.
    assert_eq!(sesiones_abiertas, 1);

    // Cerrar esa sesión vencida antes de probar duraciones válidas.
    let cerrada = cerrar_sesion_vencida(&mut tx, establecimiento_id, otro_usuario_id).await?;

    assert!(cerrada);

    // Caso 7: aceptar los límites válidos.
    for duracion in [1_i64, 1440] {
        let token = super::token::generar_token();
        let hash = super::token::hash_token(&token);

        let (sesion_id, expira_en) = crear_sesion_usuario(
            &mut tx,
            establecimiento_id,
            otro_usuario_id,
            terminal_id,
            &hash,
            duracion,
        )
        .await?;

        assert!(expira_en > chrono::Utc::now());

        // Cerrar la sesión para permitir la siguiente iteración.
        sqlx::query(
            "UPDATE sesiones_usuario
             SET cerrada_en = GREATEST(NOW(), iniciada_en),
                 motivo_cierre = 'prueba_duracion'
             WHERE id = $1",
        )
        .bind(sesion_id)
        .execute(&mut *tx)
        .await?;
    }

    // Caso 8: consultar sesiones abiertas.

    // El usuario principal conserva la sesión creada en el caso 5.
    let principal_abierta =
        usuario_tiene_sesion_abierta(&mut tx, establecimiento_id, usuario_id).await?;

    assert!(
        principal_abierta,
        "El usuario principal debe tener una sesión abierta"
    );

    // El segundo usuario tiene todas sus sesiones cerradas.
    let segunda_abierta =
        usuario_tiene_sesion_abierta(&mut tx, establecimiento_id, otro_usuario_id).await?;

    assert!(
        !segunda_abierta,
        "El segundo usuario no debe tener sesiones abiertas"
    );

    // Una consulta con un establecimiento diferente no debe
    // encontrar sesiones del establecimiento original.
    let otro_establecimiento_id = Uuid::new_v4();

    let sesion_ajena =
        usuario_tiene_sesion_abierta(&mut tx, otro_establecimiento_id, usuario_id).await?;

    assert!(
        !sesion_ajena,
        "No deben exponerse sesiones de otro establecimiento"
    );

    tx.rollback().await?;

    Ok(())
}
