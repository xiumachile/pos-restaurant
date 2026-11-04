/// Resultado de una operación administrativa de cambio de rol.
///
/// El cambio debe conservar al menos un administrador activo
/// con el permiso roles.gestionar en el establecimiento.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ResultadoCambioRolUsuario {
    Cambiado,
    SinCambios,
    UsuarioNoEncontrado,
    RolDestinoNoDisponible,
    UltimoAdministrador,
}

use sqlx::PgPool;
use uuid::Uuid;

/// Cambia el rol de un usuario sin permitir que el establecimiento
/// pierda su último administrador activo.
///
/// Todas las operaciones administrativas que alteran esta condición
/// deben bloquear primero la fila del establecimiento.
pub async fn cambiar_rol_usuario_protegido(
    pool: &PgPool,
    establecimiento_id: Uuid,
    usuario_id: Uuid,
    nuevo_rol_id: Uuid,
) -> Result<ResultadoCambioRolUsuario, sqlx::Error> {
    let mut tx = pool.begin().await?;

    // 1. Bloquear el establecimiento para serializar cambios administrativos.
    let establecimiento: Option<Uuid> =
        sqlx::query_scalar("SELECT id FROM establecimientos WHERE id = $1 FOR UPDATE")
            .bind(establecimiento_id)
            .fetch_optional(&mut *tx)
            .await?;

    if establecimiento.is_none() {
        return Ok(ResultadoCambioRolUsuario::UsuarioNoEncontrado);
    }

    // 2. Bloquear el usuario y obtener su rol actual.
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

    let Some((rol_actual_id, usuario_activo)) = usuario else {
        return Ok(ResultadoCambioRolUsuario::UsuarioNoEncontrado);
    };

    // 3. Verificar que el rol de destino exista y esté activo.
    let rol_destino_activo: Option<bool> = sqlx::query_scalar(
        r#"
        SELECT activo
        FROM roles
        WHERE establecimiento_id = $1
          AND id = $2
        "#,
    )
    .bind(establecimiento_id)
    .bind(nuevo_rol_id)
    .fetch_optional(&mut *tx)
    .await?;

    if rol_destino_activo != Some(true) {
        return Ok(ResultadoCambioRolUsuario::RolDestinoNoDisponible);
    }

    if rol_actual_id == nuevo_rol_id {
        return Ok(ResultadoCambioRolUsuario::SinCambios);
    }

    // 4. Determinar si el usuario pierde el permiso administrativo.
    let rol_actual_administrativo: bool = sqlx::query_scalar(
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
    .bind(rol_actual_id)
    .fetch_one(&mut *tx)
    .await?;

    let rol_nuevo_administrativo: bool = sqlx::query_scalar(
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
    .bind(nuevo_rol_id)
    .fetch_one(&mut *tx)
    .await?;

    if usuario_activo && rol_actual_administrativo && !rol_nuevo_administrativo {
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
            return Ok(ResultadoCambioRolUsuario::UltimoAdministrador);
        }
    }

    // 5. Actualizar el rol del usuario.
    sqlx::query(
        r#"
        UPDATE usuarios
        SET rol_id = $3,
            updated_at = NOW()
        WHERE establecimiento_id = $1
          AND id = $2
        "#,
    )
    .bind(establecimiento_id)
    .bind(usuario_id)
    .bind(nuevo_rol_id)
    .execute(&mut *tx)
    .await?;

    // 6. Revocar las sesiones abiertas emitidas bajo el rol anterior.
    sqlx::query(
        r#"
        UPDATE sesiones_usuario
        SET cerrada_en = GREATEST(NOW(), iniciada_en),
            motivo_cierre = 'rol_cambiado'
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

    Ok(ResultadoCambioRolUsuario::Cambiado)
}
