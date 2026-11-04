use sqlx::{PgExecutor, Postgres};
use uuid::Uuid;

/// Comprueba si un rol activo tiene un permiso asignado
/// dentro de su propio establecimiento.
///
/// Acepta una conexión o una transacción PostgreSQL.
/// Devuelve false cuando no existe una asignación válida.
pub async fn rol_tiene_permiso<'e, E>(
    executor: E,
    establecimiento_id: Uuid,
    rol_id: Uuid,
    codigo_permiso: &str,
) -> Result<bool, sqlx::Error>
where
    E: PgExecutor<'e>,
{
    sqlx::query_scalar::<Postgres, bool>(
        r#"
        SELECT EXISTS (
            SELECT 1
            FROM rol_permisos rp
            INNER JOIN roles r
                ON r.id = rp.rol_id
               AND r.establecimiento_id = rp.establecimiento_id
            INNER JOIN permisos p
                ON p.id = rp.permiso_id
            WHERE rp.establecimiento_id = $1
              AND rp.rol_id = $2
              AND p.codigo = $3
              AND r.activo = TRUE
        )
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind(codigo_permiso)
    .fetch_one(executor)
    .await
}

/// Concede un permiso existente a un rol activo del establecimiento.
///
/// Devuelve true si se creó la asignación.
/// Devuelve false si el rol o permiso no existe, el rol está inactivo
/// o la asignación ya estaba registrada.
pub async fn conceder_permiso<'e, E>(
    executor: E,
    establecimiento_id: Uuid,
    rol_id: Uuid,
    codigo_permiso: &str,
) -> Result<bool, sqlx::Error>
where
    E: PgExecutor<'e>,
{
    let resultado = sqlx::query(
        r#"
        INSERT INTO rol_permisos
            (establecimiento_id, rol_id, permiso_id)
        SELECT r.establecimiento_id, r.id, p.id
        FROM roles r
        CROSS JOIN permisos p
        WHERE r.establecimiento_id = $1
          AND r.id = $2
          AND r.activo = TRUE
          AND p.codigo = $3
        ON CONFLICT (rol_id, permiso_id) DO NOTHING
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind(codigo_permiso)
    .execute(executor)
    .await?;

    Ok(resultado.rows_affected() == 1)
}

/// Revoca un permiso de un rol del establecimiento.
///
/// Devuelve true si se eliminó la asignación.
/// Devuelve false si no existía una asignación correspondiente.
pub async fn revocar_permiso<'e, E>(
    executor: E,
    establecimiento_id: Uuid,
    rol_id: Uuid,
    codigo_permiso: &str,
) -> Result<bool, sqlx::Error>
where
    E: PgExecutor<'e>,
{
    let resultado = sqlx::query(
        r#"
        DELETE FROM rol_permisos rp
        USING permisos p
        WHERE rp.establecimiento_id = $1
          AND rp.rol_id = $2
          AND rp.permiso_id = p.id
          AND p.codigo = $3
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind(codigo_permiso)
    .execute(executor)
    .await?;

    Ok(resultado.rows_affected() == 1)
}

/// Resultado de una revocación administrativa protegida.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ResultadoRevocacion {
    Revocado,
    SinCambios,
    UltimoAdministrador,
}

/// Revoca un permiso evitando eliminar el último acceso administrativo.
///
/// Las revocaciones protegidas del mismo establecimiento se serializan
/// bloqueando su fila con FOR UPDATE.
///
/// Esta función debe utilizarse en la API administrativa.
pub async fn revocar_permiso_protegido(
    pool: &sqlx::PgPool,
    establecimiento_id: Uuid,
    rol_id: Uuid,
    codigo_permiso: &str,
) -> Result<ResultadoRevocacion, sqlx::Error> {
    let mut tx = pool.begin().await?;

    // Serializar las operaciones administrativas del establecimiento.
    let establecimiento_existe: bool = sqlx::query_scalar(
        r#"
        SELECT EXISTS (
            SELECT 1
            FROM establecimientos
            WHERE id = $1
        )
        "#,
    )
    .bind(establecimiento_id)
    .fetch_one(&mut *tx)
    .await?;

    if !establecimiento_existe {
        tx.rollback().await?;
        return Ok(ResultadoRevocacion::SinCambios);
    }

    sqlx::query("SELECT id FROM establecimientos WHERE id = $1 FOR UPDATE")
        .bind(establecimiento_id)
        .fetch_one(&mut *tx)
        .await?;

    if codigo_permiso == "roles.gestionar" {
        // Verificar si el rol objetivo tiene actualmente el permiso.
        let tiene_asignacion: bool = sqlx::query_scalar(
            r#"
            SELECT EXISTS (
                SELECT 1
                FROM rol_permisos rp
                INNER JOIN permisos p ON p.id = rp.permiso_id
                WHERE rp.establecimiento_id = $1
                  AND rp.rol_id = $2
                  AND p.codigo = 'roles.gestionar'
            )
            "#,
        )
        .bind(establecimiento_id)
        .bind(rol_id)
        .fetch_one(&mut *tx)
        .await?;

        if tiene_asignacion {
            // Debe quedar otro rol activo con el permiso y un usuario activo.
            let existe_otro_administrador: bool = sqlx::query_scalar(
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
                      AND r.id <> $2
                      AND r.activo = TRUE
                      AND p.codigo = 'roles.gestionar'
                      AND EXISTS (
                          SELECT 1
                          FROM usuarios u
                          WHERE u.establecimiento_id = r.establecimiento_id
                            AND u.rol_id = r.id
                            AND u.activo = TRUE
                      )
                )
                "#,
            )
            .bind(establecimiento_id)
            .bind(rol_id)
            .fetch_one(&mut *tx)
            .await?;

            if !existe_otro_administrador {
                tx.rollback().await?;
                return Ok(ResultadoRevocacion::UltimoAdministrador);
            }
        }
    }

    let revocado = revocar_permiso(&mut *tx, establecimiento_id, rol_id, codigo_permiso).await?;

    tx.commit().await?;

    Ok(if revocado {
        ResultadoRevocacion::Revocado
    } else {
        ResultadoRevocacion::SinCambios
    })
}
