use sqlx::PgConnection;
use uuid::Uuid;

/// Cierra la sesión abierta de un usuario únicamente si ya venció.
///
/// Debe ejecutarse dentro de una transacción que haya bloqueado
/// previamente la fila del usuario mediante SELECT FOR UPDATE.
///
/// Retorna true si cerró una sesión y false si no había ninguna
/// sesión vencida pendiente de cierre.
pub async fn cerrar_sesion_vencida(
    conexion: &mut PgConnection,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
) -> Result<bool, sqlx::Error> {
    let resultado = sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET
            cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'expirada'
        WHERE establecimiento_id = $1
          AND usuario_id = $2
          AND cerrada_en IS NULL
          AND expira_en <= NOW()
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .execute(conexion)
    .await?;

    Ok(resultado.rows_affected() > 0)
}

/// Crea una sesión de usuario con duración limitada.
///
/// Requiere que el usuario no tenga otra sesión abierta.
/// Debe ejecutarse dentro de la transacción de autenticación,
/// después de bloquear la fila del usuario.
pub async fn crear_sesion_usuario(
    conexion: &mut PgConnection,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
    terminal_id: Uuid,
    token_hash: &str,
    duracion_minutos: i64,
) -> Result<(Uuid, chrono::DateTime<chrono::Utc>), sqlx::Error> {
    if !(1..=1440).contains(&duracion_minutos) {
        return Err(sqlx::Error::Protocol(
            "La duración de la sesión debe estar entre 1 y 1440 minutos".into(),
        ));
    }

    let sesion_id = Uuid::new_v4();

    let expira_en: chrono::DateTime<chrono::Utc> = sqlx::query_scalar(
        r#"
        INSERT INTO sesiones_usuario (
            id,
            establecimiento_id,
            usuario_id,
            terminal_id,
            token_hash,
            expira_en
        )
        VALUES (
            $1, $2, $3, $4, $5,
            NOW() + ($6 * INTERVAL '1 minute')
        )
        RETURNING expira_en
        "#,
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(terminal_id)
    .bind(token_hash)
    .bind(duracion_minutos)
    .fetch_one(conexion)
    .await?;

    Ok((sesion_id, expira_en))
}

/// Comprueba si existe una sesión todavía abierta para el usuario.
///
/// Debe llamarse dentro de la transacción de autenticación,
/// después de cerrar las sesiones vencidas.
pub async fn usuario_tiene_sesion_abierta(
    conexion: &mut PgConnection,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
) -> Result<bool, sqlx::Error> {
    let existe: bool = sqlx::query_scalar(
        r#"
        SELECT EXISTS (
            SELECT 1
            FROM sesiones_usuario
            WHERE establecimiento_id = $1
              AND usuario_id = $2
              AND cerrada_en IS NULL
        )
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .fetch_one(conexion)
    .await?;

    Ok(existe)
}

/// Identificadores asociados a una sesión autenticada.
#[derive(Debug, Clone, PartialEq, Eq, sqlx::FromRow)]
pub struct SesionValidada {
    pub sesion_id: Uuid,
    pub establecimiento_id: Uuid,
    pub usuario_id: Uuid,
    pub terminal_id: Uuid,
    pub rol_id: Uuid,
}

/// Valida un token sin consultar ni almacenar su valor original.
///
/// Devuelve None si el token no corresponde a una sesión válida,
/// si la sesión expiró o si alguna entidad asociada está inactiva.
pub async fn validar_token_sesion(
    pool: &sqlx::PgPool,
    token: &str,
    establecimiento_id: Uuid,
    terminal_id: Uuid,
) -> Result<Option<SesionValidada>, sqlx::Error> {
    // Los tokens emitidos tienen exactamente 64 caracteres hexadecimales.
    if token.len() != 64 || !token.bytes().all(|b| b.is_ascii_hexdigit()) {
        return Ok(None);
    }

    let token_hash = crate::auth::token::hash_token(token);

    sqlx::query_as::<_, SesionValidada>(
        r#"
        SELECT
            s.id AS sesion_id,
            s.establecimiento_id,
            s.usuario_id,
            s.terminal_id,
            u.rol_id
        FROM sesiones_usuario s
        INNER JOIN usuarios u
            ON u.id = s.usuario_id
           AND u.establecimiento_id = s.establecimiento_id
        INNER JOIN roles r
            ON r.id = u.rol_id
           AND r.establecimiento_id = s.establecimiento_id
        INNER JOIN terminales t
            ON t.id = s.terminal_id
           AND t.establecimiento_id = s.establecimiento_id
        INNER JOIN establecimientos e
            ON e.id = s.establecimiento_id
        WHERE s.token_hash = $1
          AND s.establecimiento_id = $2
          AND s.terminal_id = $3
          AND s.cerrada_en IS NULL
          AND s.expira_en > NOW()
          AND u.activo = TRUE
          AND r.activo = TRUE
          AND t.activo = TRUE
          AND e.activo = TRUE
        "#,
    )
    .bind(token_hash)
    .bind(establecimiento_id)
    .bind(terminal_id)
    .fetch_optional(pool)
    .await
}

/// Identifica y valida una sesión a partir de su token Bearer.
///
/// El cliente no necesita enviar establecimiento_id ni terminal_id.
/// Ambos identificadores se obtienen de la sesión almacenada.
///
/// La validación definitiva reutiliza validar_token_sesion().
pub async fn validar_token_bearer(
    pool: &sqlx::PgPool,
    token: &str,
) -> Result<Option<SesionValidada>, sqlx::Error> {
    if token.len() != 64 || !token.bytes().all(|b| b.is_ascii_hexdigit()) {
        return Ok(None);
    }

    let token_hash = crate::auth::token::hash_token(token);

    sqlx::query_as::<_, SesionValidada>(
        r#"
        SELECT
            s.id AS sesion_id,
            s.establecimiento_id,
            s.usuario_id,
            s.terminal_id,
            u.rol_id
        FROM sesiones_usuario s
        INNER JOIN usuarios u
            ON u.id = s.usuario_id
           AND u.establecimiento_id = s.establecimiento_id
        INNER JOIN roles r
            ON r.id = u.rol_id
           AND r.establecimiento_id = s.establecimiento_id
        INNER JOIN terminales t
            ON t.id = s.terminal_id
           AND t.establecimiento_id = s.establecimiento_id
        INNER JOIN establecimientos e
            ON e.id = s.establecimiento_id
        WHERE s.token_hash = $1
          AND s.cerrada_en IS NULL
          AND s.expira_en > NOW()
          AND u.activo = TRUE
          AND r.activo = TRUE
          AND t.activo = TRUE
          AND e.activo = TRUE
        "#,
    )
    .bind(token_hash)
    .fetch_optional(pool)
    .await
}

/// Cierra voluntariamente una sesión autenticada.
///
/// Solo modifica la sesión indicada y conserva su registro
/// para auditoría.
///
/// Devuelve true si la sesión fue cerrada y false si ya estaba
/// cerrada o no corresponde al establecimiento.
pub async fn cerrar_sesion_usuario(
    pool: &sqlx::PgPool,
    establecimiento_id: Uuid,
    sesion_id: Uuid,
) -> Result<bool, sqlx::Error> {
    let resultado = sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET
            cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'logout'
        WHERE id = $1
          AND establecimiento_id = $2
          AND cerrada_en IS NULL
        "#,
    )
    .bind(sesion_id)
    .bind(establecimiento_id)
    .execute(pool)
    .await?;

    Ok(resultado.rows_affected() == 1)
}

/// Resultado de una desactivación administrativa de usuario.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ResultadoDesactivacionUsuario {
    Desactivado,
    NoEncontrado,
    UltimoAdministrador,
}

/// Desactiva administrativamente un usuario sin eliminar el último
/// administrador activo del establecimiento.
///
/// Todas las operaciones administrativas que afecten a administradores
/// deben bloquear primero la misma fila de establecimientos.
pub async fn desactivar_usuario_protegido(
    pool: &sqlx::PgPool,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
) -> Result<ResultadoDesactivacionUsuario, sqlx::Error> {
    let mut tx = pool.begin().await?;

    // Bloqueo común para serializar cambios administrativos.
    let establecimiento: Option<Uuid> =
        sqlx::query_scalar("SELECT id FROM establecimientos WHERE id = $1 FOR UPDATE")
            .bind(establecimiento_id)
            .fetch_optional(&mut *tx)
            .await?;

    if establecimiento.is_none() {
        return Ok(ResultadoDesactivacionUsuario::NoEncontrado);
    }

    // Mantener el orden de bloqueos: establecimiento -> usuario.
    let usuario: Option<(Uuid, bool)> = sqlx::query_as(
        r#"
        SELECT rol_id, activo
        FROM usuarios
        WHERE establecimiento_id = $1
          AND id = $2
        FOR UPDATE
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .fetch_optional(&mut *tx)
    .await?;

    let Some((rol_id, activo)) = usuario else {
        return Ok(ResultadoDesactivacionUsuario::NoEncontrado);
    };

    if activo {
        let es_administrador: bool = sqlx::query_scalar(
            r#"
            SELECT EXISTS (
                SELECT 1
                FROM roles r
                INNER JOIN rol_permisos rp
                    ON rp.establecimiento_id = r.establecimiento_id
                   AND rp.rol_id = r.id
                INNER JOIN permisos p
                    ON p.id = rp.permiso_id
                WHERE r.establecimiento_id = $1
                  AND r.id = $2
                  AND r.activo = TRUE
                  AND p.codigo = 'roles.gestionar'
            )
            "#,
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .fetch_one(&mut *tx)
        .await?;

        if es_administrador {
            let existe_otro_administrador: bool = sqlx::query_scalar(
                r#"
                SELECT EXISTS (
                    SELECT 1
                    FROM usuarios u
                    INNER JOIN roles r
                        ON r.establecimiento_id = u.establecimiento_id
                       AND r.id = u.rol_id
                    INNER JOIN rol_permisos rp
                        ON rp.establecimiento_id = r.establecimiento_id
                       AND rp.rol_id = r.id
                    INNER JOIN permisos p
                        ON p.id = rp.permiso_id
                    WHERE u.establecimiento_id = $1
                      AND u.id <> $2
                      AND u.activo = TRUE
                      AND r.activo = TRUE
                      AND p.codigo = 'roles.gestionar'
                )
                "#,
            )
            .bind(establecimiento_id)
            .bind(usuario_id)
            .fetch_one(&mut *tx)
            .await?;

            if !existe_otro_administrador {
                return Ok(ResultadoDesactivacionUsuario::UltimoAdministrador);
            }
        }
    }

    sqlx::query(
        r#"
        UPDATE usuarios
        SET activo = FALSE,
            updated_at = NOW()
        WHERE establecimiento_id = $1
          AND id = $2
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'usuario_desactivado'
        WHERE establecimiento_id = $1
          AND usuario_id = $2
          AND cerrada_en IS NULL
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    Ok(ResultadoDesactivacionUsuario::Desactivado)
}

/// Desactiva una terminal y revoca permanentemente sus sesiones.
///
/// El bloqueo de la fila de la terminal serializa esta operación
/// con las validaciones de terminal que utilicen FOR UPDATE.
pub async fn desactivar_terminal_y_revocar_sesiones(
    pool: &sqlx::PgPool,
    establecimiento_id: Uuid,
    terminal_id: Uuid,
) -> Result<bool, sqlx::Error> {
    let mut tx = pool.begin().await?;

    let terminal: Option<Uuid> = sqlx::query_scalar(
        r#"
        SELECT id
        FROM terminales
        WHERE id = $1
          AND establecimiento_id = $2
        FOR UPDATE
        "#,
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .fetch_optional(&mut *tx)
    .await?;

    if terminal.is_none() {
        tx.rollback().await?;
        return Ok(false);
    }

    sqlx::query(
        r#"
        UPDATE terminales
        SET activo = FALSE,
            updated_at = NOW()
        WHERE id = $1
          AND establecimiento_id = $2
        "#,
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'terminal_desactivada'
        WHERE establecimiento_id = $1
          AND terminal_id = $2
          AND cerrada_en IS NULL
        "#,
    )
    .bind(establecimiento_id)
    .bind(terminal_id)
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    Ok(true)
}
