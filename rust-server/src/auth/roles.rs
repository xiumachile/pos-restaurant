/// Resultado de una desactivación administrativa de rol.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ResultadoDesactivacionRol {
    Desactivado,
    NoEncontrado,
    UltimoAdministrador,
}

use sqlx::PgPool;
use uuid::Uuid;

/// Desactiva un rol y revoca las sesiones de sus usuarios.
/// Protege al último administrador activo del establecimiento.
///
/// Las demás operaciones administrativas deben respetar el mismo
/// bloqueo inicial de establecimientos para evitar carreras.
pub async fn desactivar_rol_protegido(
    pool: &PgPool,
    establecimiento_id: Uuid,
    rol_id: Uuid,
) -> Result<ResultadoDesactivacionRol, sqlx::Error> {
    let mut tx = pool.begin().await?;

    // 1. Serializar cambios administrativos del establecimiento.
    let establecimiento: Option<Uuid> =
        sqlx::query_scalar("SELECT id FROM establecimientos WHERE id = $1 FOR UPDATE")
            .bind(establecimiento_id)
            .fetch_optional(&mut *tx)
            .await?;

    if establecimiento.is_none() {
        return Ok(ResultadoDesactivacionRol::NoEncontrado);
    }

    // 2. Bloquear el rol dentro de su establecimiento.
    let rol: Option<bool> = sqlx::query_scalar(
        r#"
        SELECT activo
        FROM roles
        WHERE establecimiento_id = $1
          AND id = $2
        FOR UPDATE
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .fetch_optional(&mut *tx)
    .await?;

    let Some(rol_activo) = rol else {
        return Ok(ResultadoDesactivacionRol::NoEncontrado);
    };

    // 3. Si el rol está activo, verificar que su desactivación
    // no elimine al último administrador activo.
    if rol_activo {
        let tiene_permiso_administrativo: bool = sqlx::query_scalar(
            r#"
            SELECT EXISTS (
                SELECT 1
                FROM rol_permisos rp
                INNER JOIN permisos p
                    ON p.id = rp.permiso_id
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

        if tiene_permiso_administrativo {
            let administradores_en_rol: bool = sqlx::query_scalar(
                r#"
                SELECT EXISTS (
                    SELECT 1
                    FROM usuarios
                    WHERE establecimiento_id = $1
                      AND rol_id = $2
                      AND activo = TRUE
                )
                "#,
            )
            .bind(establecimiento_id)
            .bind(rol_id)
            .fetch_one(&mut *tx)
            .await?;

            if administradores_en_rol {
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
                          AND u.rol_id <> $2
                          AND u.activo = TRUE
                          AND r.activo = TRUE
                          AND p.codigo = 'roles.gestionar'
                    )
                    "#,
                )
                .bind(establecimiento_id)
                .bind(rol_id)
                .fetch_one(&mut *tx)
                .await?;

                if !existe_otro_administrador {
                    return Ok(ResultadoDesactivacionRol::UltimoAdministrador);
                }
            }
        }
    }

    // 4. Desactivar el rol.
    sqlx::query(
        r#"
        UPDATE roles
        SET activo = FALSE,
            updated_at = NOW()
        WHERE establecimiento_id = $1
          AND id = $2
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .execute(&mut *tx)
    .await?;

    // 5. Revocar todas las sesiones abiertas de sus usuarios.
    sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'rol_desactivado'
        WHERE establecimiento_id = $1
          AND cerrada_en IS NULL
          AND usuario_id IN (
              SELECT id
              FROM usuarios
              WHERE establecimiento_id = $1
                AND rol_id = $2
          )
        "#,
    )
    .bind(establecimiento_id)
    .bind(rol_id)
    .execute(&mut *tx)
    .await?;

    tx.commit().await?;

    Ok(ResultadoDesactivacionRol::Desactivado)
}
