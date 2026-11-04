use sqlx::{FromRow, PgConnection};
use uuid::Uuid;

#[derive(Debug, FromRow)]
pub struct UsuarioAuth {
    pub id: Uuid,
    pub establecimiento_id: Uuid,
    pub rol_id: Uuid,
    pub pin_hash: String,
    pub activo: bool,
    pub intentos_fallidos: i32,
    pub bloqueado_hasta: Option<sqlx::types::chrono::DateTime<sqlx::types::chrono::Utc>>,
}

pub async fn buscar_usuario(
    conn: &mut PgConnection,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
) -> Result<Option<UsuarioAuth>, sqlx::Error> {
    sqlx::query_as::<_, UsuarioAuth>(
        r#"
        SELECT
            u.id,
            u.establecimiento_id,
            u.rol_id,
            u.pin_hash,
            u.activo,
            u.intentos_fallidos,
            u.bloqueado_hasta
        FROM usuarios u
        INNER JOIN establecimientos e
            ON e.id = u.establecimiento_id
        WHERE u.id = $1
          AND u.establecimiento_id = $2
          AND u.activo = TRUE
          AND e.activo = TRUE
        "#,
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .fetch_optional(conn)
    .await
}

pub async fn terminal_activa(
    conn: &mut PgConnection,
    establecimiento_id: Uuid,
    terminal_id: Uuid,
) -> Result<bool, sqlx::Error> {
    // Bloquear la terminal durante la transacción de login.
    //
    // FOR SHARE permite validaciones concurrentes, pero impide
    // que la terminal sea desactivada hasta finalizar el login.
    let terminal: Option<Uuid> = sqlx::query_scalar(
        r#"
        SELECT t.id
        FROM terminales t
        INNER JOIN establecimientos e
            ON e.id = t.establecimiento_id
        WHERE t.id = $1
          AND t.establecimiento_id = $2
          AND t.activo = TRUE
          AND e.activo = TRUE
        FOR SHARE OF t
        "#,
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .fetch_optional(conn)
    .await?;

    Ok(terminal.is_some())
}

pub async fn rol_activo(
    conn: &mut PgConnection,
    establecimiento_id: Uuid,
    rol_id: Uuid,
) -> Result<bool, sqlx::Error> {
    sqlx::query_scalar::<_, bool>(
        r#"
        SELECT EXISTS (
            SELECT 1
            FROM roles
            WHERE id = $1
              AND establecimiento_id = $2
              AND activo = TRUE
        )
        "#,
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .fetch_one(conn)
    .await
}

pub async fn bloquear_usuario_login(
    conn: &mut PgConnection,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
) -> Result<Option<UsuarioAuth>, sqlx::Error> {
    sqlx::query_as::<_, UsuarioAuth>(
        r#"
        SELECT
            u.id,
            u.establecimiento_id,
            u.rol_id,
            u.pin_hash,
            u.activo,
            u.intentos_fallidos,
            u.bloqueado_hasta
        FROM usuarios u
        INNER JOIN establecimientos e
            ON e.id = u.establecimiento_id
        WHERE u.id = $1
          AND u.establecimiento_id = $2
          AND u.activo = TRUE
          AND e.activo = TRUE
        FOR UPDATE OF u
        "#,
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .fetch_optional(conn)
    .await
}

pub async fn registrar_pin_incorrecto(
    conn: &mut PgConnection,
    usuario_id: Uuid,
) -> Result<(), sqlx::Error> {
    sqlx::query(
        r#"
        UPDATE usuarios
        SET
            intentos_fallidos = CASE
                WHEN bloqueado_hasta IS NOT NULL
                     AND bloqueado_hasta <= NOW()
                THEN 1
                ELSE intentos_fallidos + 1
            END,
            bloqueado_hasta = CASE
                WHEN (
                    CASE
                        WHEN bloqueado_hasta IS NOT NULL
                             AND bloqueado_hasta <= NOW()
                        THEN 1
                        ELSE intentos_fallidos + 1
                    END
                ) >= 5
                THEN NOW() + INTERVAL '15 minutes'
                ELSE NULL
            END,
            updated_at = NOW()
        WHERE id = $1
        "#,
    )
    .bind(usuario_id)
    .execute(conn)
    .await?;

    Ok(())
}

pub async fn reiniciar_intentos_pin(
    conn: &mut PgConnection,
    usuario_id: Uuid,
) -> Result<(), sqlx::Error> {
    sqlx::query(
        r#"
        UPDATE usuarios
        SET
            intentos_fallidos = 0,
            bloqueado_hasta = NULL,
            updated_at = NOW()
        WHERE id = $1
        "#,
    )
    .bind(usuario_id)
    .execute(conn)
    .await?;

    Ok(())
}
