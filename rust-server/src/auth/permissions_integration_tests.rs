use super::permissions::rol_tiene_permiso;
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn autorizacion_rbac_postgresql() -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;
    let mut tx = pool.begin().await?;

    let establecimiento_a = Uuid::new_v4();
    let establecimiento_b = Uuid::new_v4();

    let rol_a = Uuid::new_v4();
    let rol_b = Uuid::new_v4();

    let permiso_id = Uuid::new_v4();
    let codigo_permiso = format!("TEST_RBAC_{}", Uuid::new_v4());

    // Crear dos establecimientos temporales.
    for (id, nombre) in [
        (establecimiento_a, "TEST_RBAC_A"),
        (establecimiento_b, "TEST_RBAC_B"),
    ] {
        sqlx::query(
            "INSERT INTO establecimientos (id, nombre)
             VALUES ($1, $2)",
        )
        .bind(id)
        .bind(nombre)
        .execute(&mut *tx)
        .await?;
    }

    // Crear un rol por establecimiento.
    for (id, establecimiento_id) in [(rol_a, establecimiento_a), (rol_b, establecimiento_b)] {
        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(id)
        .bind(establecimiento_id)
        .bind("TEST_ROL_RBAC")
        .execute(&mut *tx)
        .await?;
    }

    // Crear permiso temporal.
    sqlx::query(
        "INSERT INTO permisos (id, codigo, descripcion)
         VALUES ($1, $2, $3)",
    )
    .bind(permiso_id)
    .bind(&codigo_permiso)
    .bind("Permiso temporal para pruebas RBAC")
    .execute(&mut *tx)
    .await?;

    // Asignar permiso solamente al rol A.
    sqlx::query(
        "INSERT INTO rol_permisos
         (establecimiento_id, rol_id, permiso_id)
         VALUES ($1, $2, $3)",
    )
    .bind(establecimiento_a)
    .bind(rol_a)
    .bind(permiso_id)
    .execute(&mut *tx)
    .await?;

    // Caso 1: permiso concedido.
    assert!(
        rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, &codigo_permiso).await?,
        "El rol A debe tener el permiso"
    );

    // Caso 2: rol sin permiso asignado.
    assert!(
        !rol_tiene_permiso(&mut *tx, establecimiento_b, rol_b, &codigo_permiso).await?,
        "El rol B no debe tener el permiso"
    );

    // Caso 3: permiso inexistente.
    assert!(
        !rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, "permiso.inexistente").await?,
        "Un permiso inexistente debe ser rechazado"
    );

    // Caso 4: aislamiento entre establecimientos.
    assert!(
        !rol_tiene_permiso(&mut *tx, establecimiento_b, rol_a, &codigo_permiso).await?,
        "No debe utilizarse un rol de otro establecimiento"
    );

    // Caso 5: desactivar rol.
    sqlx::query("UPDATE roles SET activo = FALSE WHERE id = $1")
        .bind(rol_a)
        .execute(&mut *tx)
        .await?;

    assert!(
        !rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, &codigo_permiso).await?,
        "Un rol desactivado no debe conceder permisos"
    );

    // Revertir todos los registros temporales.
    tx.rollback().await?;

    Ok(())
}

#[tokio::test]
async fn asignacion_revocacion_rbac_postgresql()
-> Result<(), Box<dyn std::error::Error + Send + Sync>> {
    use super::permissions::{conceder_permiso, revocar_permiso};

    let database_url = std::env::var("DATABASE_URL")?;
    let pool = PgPool::connect(&database_url).await?;
    let mut tx = pool.begin().await?;

    let establecimiento_a = Uuid::new_v4();
    let establecimiento_b = Uuid::new_v4();
    let rol_a = Uuid::new_v4();
    let rol_b = Uuid::new_v4();

    let codigo_permiso = "caja.cobrar";

    for (id, nombre) in [
        (establecimiento_a, "TEST_ASIGNACION_RBAC_A"),
        (establecimiento_b, "TEST_ASIGNACION_RBAC_B"),
    ] {
        sqlx::query(
            "INSERT INTO establecimientos (id, nombre)
             VALUES ($1, $2)",
        )
        .bind(id)
        .bind(nombre)
        .execute(&mut *tx)
        .await?;
    }

    for (id, establecimiento_id) in [(rol_a, establecimiento_a), (rol_b, establecimiento_b)] {
        sqlx::query(
            "INSERT INTO roles (id, establecimiento_id, nombre)
             VALUES ($1, $2, $3)",
        )
        .bind(id)
        .bind(establecimiento_id)
        .bind("TEST_ROL_ASIGNACION")
        .execute(&mut *tx)
        .await?;
    }

    // Caso 1: conceder un permiso existente.
    assert!(conceder_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    assert!(rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 2: conceder nuevamente no genera duplicados.
    assert!(!conceder_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 3: impedir asignación cruzada entre establecimientos.
    assert!(!conceder_permiso(&mut *tx, establecimiento_b, rol_a, codigo_permiso,).await?);

    assert!(!rol_tiene_permiso(&mut *tx, establecimiento_b, rol_b, codigo_permiso,).await?);

    // Caso 4: revocar el permiso.
    assert!(revocar_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    assert!(!rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 5: revocar nuevamente es idempotente.
    assert!(!revocar_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 6: no conceder permisos inexistentes.
    assert!(!conceder_permiso(&mut *tx, establecimiento_a, rol_a, "permiso.inexistente",).await?);

    // Caso 7: no conceder permisos a roles inactivos.
    sqlx::query(
        "UPDATE roles SET activo = FALSE
         WHERE establecimiento_id = $1 AND id = $2",
    )
    .bind(establecimiento_a)
    .bind(rol_a)
    .execute(&mut *tx)
    .await?;

    assert!(!conceder_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Reactivar para crear una asignación válida.
    sqlx::query(
        "UPDATE roles SET activo = TRUE
         WHERE establecimiento_id = $1 AND id = $2",
    )
    .bind(establecimiento_a)
    .bind(rol_a)
    .execute(&mut *tx)
    .await?;

    assert!(conceder_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 8: una revocación cruzada no debe modificar el rol A.
    assert!(!revocar_permiso(&mut *tx, establecimiento_b, rol_a, codigo_permiso,).await?);

    assert!(rol_tiene_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Caso 9: revocar incluso después de desactivar el rol.
    sqlx::query(
        "UPDATE roles SET activo = FALSE
         WHERE establecimiento_id = $1 AND id = $2",
    )
    .bind(establecimiento_a)
    .bind(rol_a)
    .execute(&mut *tx)
    .await?;

    assert!(revocar_permiso(&mut *tx, establecimiento_a, rol_a, codigo_permiso,).await?);

    // Verificar directamente la eliminación de la asignación.
    let asignaciones: i64 = sqlx::query_scalar(
        "SELECT COUNT(*) FROM rol_permisos
         WHERE establecimiento_id = $1 AND rol_id = $2",
    )
    .bind(establecimiento_a)
    .bind(rol_a)
    .fetch_one(&mut *tx)
    .await?;

    assert_eq!(asignaciones, 0);

    tx.rollback().await?;

    Ok(())
}
