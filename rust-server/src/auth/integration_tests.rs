use super::repository::{
    bloquear_usuario_login, buscar_usuario, registrar_pin_incorrecto, reiniciar_intentos_pin,
    rol_activo, terminal_activa,
};
use sqlx::PgPool;
use uuid::Uuid;

#[tokio::test]
async fn consultas_autenticacion_postgresql() -> Result<(), sqlx::Error> {
    let database_url = std::env::var("DATABASE_URL").expect("DATABASE_URL debe estar configurada");

    let pool = PgPool::connect(&database_url).await?;
    let mut tx = pool.begin().await?;

    let establecimiento_id = Uuid::new_v4();
    let terminal_id = Uuid::new_v4();
    let rol_id = Uuid::new_v4();
    let usuario_id = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(establecimiento_id)
        .bind("Establecimiento de prueba")
        .execute(&mut *tx)
        .await?;

    sqlx::query(
        "INSERT INTO terminales (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(terminal_id)
    .bind(establecimiento_id)
    .bind("Terminal de prueba")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO roles (id, establecimiento_id, nombre)
         VALUES ($1, $2, $3)",
    )
    .bind(rol_id)
    .bind(establecimiento_id)
    .bind("Cajero de prueba")
    .execute(&mut *tx)
    .await?;

    sqlx::query(
        "INSERT INTO usuarios
         (id, establecimiento_id, rol_id, nombre, pin_hash)
         VALUES ($1, $2, $3, $4, $5)",
    )
    .bind(usuario_id)
    .bind(establecimiento_id)
    .bind(rol_id)
    .bind("Empleado de prueba")
    .bind("hash-ficticio-solo-para-test")
    .execute(&mut *tx)
    .await?;

    let usuario = buscar_usuario(&mut tx, establecimiento_id, usuario_id)
        .await?
        .expect("El usuario debe existir");

    assert_eq!(usuario.id, usuario_id);
    assert_eq!(usuario.establecimiento_id, establecimiento_id);
    assert_eq!(usuario.rol_id, rol_id);

    assert!(terminal_activa(&mut tx, establecimiento_id, terminal_id).await?);

    assert!(rol_activo(&mut tx, establecimiento_id, rol_id).await?);

    // Verificar aislamiento entre establecimientos.
    let otro_establecimiento_id = Uuid::new_v4();

    sqlx::query("INSERT INTO establecimientos (id, nombre) VALUES ($1, $2)")
        .bind(otro_establecimiento_id)
        .bind("Segundo establecimiento de prueba")
        .execute(&mut *tx)
        .await?;

    // El usuario pertenece al primer establecimiento.
    let usuario_ajeno = buscar_usuario(&mut tx, otro_establecimiento_id, usuario_id).await?;

    assert!(
        usuario_ajeno.is_none(),
        "No debe permitir consultar usuarios de otro establecimiento"
    );

    // La terminal pertenece al primer establecimiento.
    let terminal_ajena = terminal_activa(&mut tx, otro_establecimiento_id, terminal_id).await?;

    assert!(
        !terminal_ajena,
        "No debe permitir utilizar terminales de otro establecimiento"
    );

    // El rol pertenece al primer establecimiento.
    let rol_ajeno = rol_activo(&mut tx, otro_establecimiento_id, rol_id).await?;

    assert!(
        !rol_ajeno,
        "No debe permitir utilizar roles de otro establecimiento"
    );

    // 1. Un usuario desactivado no debe ser encontrado.
    sqlx::query("UPDATE usuarios SET activo = FALSE WHERE id = $1")
        .bind(usuario_id)
        .execute(&mut *tx)
        .await?;

    assert!(
        buscar_usuario(&mut tx, establecimiento_id, usuario_id)
            .await?
            .is_none(),
        "Un usuario inactivo no debe autenticarse"
    );

    sqlx::query("UPDATE usuarios SET activo = TRUE WHERE id = $1")
        .bind(usuario_id)
        .execute(&mut *tx)
        .await?;

    // 2. Una terminal desactivada debe ser rechazada.
    sqlx::query("UPDATE terminales SET activo = FALSE WHERE id = $1")
        .bind(terminal_id)
        .execute(&mut *tx)
        .await?;

    assert!(
        !terminal_activa(&mut tx, establecimiento_id, terminal_id).await?,
        "Una terminal inactiva debe ser rechazada"
    );

    sqlx::query("UPDATE terminales SET activo = TRUE WHERE id = $1")
        .bind(terminal_id)
        .execute(&mut *tx)
        .await?;

    // 3. Un rol desactivado debe ser rechazado.
    sqlx::query("UPDATE roles SET activo = FALSE WHERE id = $1")
        .bind(rol_id)
        .execute(&mut *tx)
        .await?;

    assert!(
        !rol_activo(&mut tx, establecimiento_id, rol_id).await?,
        "Un rol inactivo debe ser rechazado"
    );

    sqlx::query("UPDATE roles SET activo = TRUE WHERE id = $1")
        .bind(rol_id)
        .execute(&mut *tx)
        .await?;

    // 4. Un establecimiento desactivado debe impedir
    // el acceso de sus usuarios y terminales.
    sqlx::query("UPDATE establecimientos SET activo = FALSE WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&mut *tx)
        .await?;

    assert!(
        buscar_usuario(&mut tx, establecimiento_id, usuario_id)
            .await?
            .is_none(),
        "Un establecimiento inactivo debe impedir el acceso de usuarios"
    );

    assert!(
        !terminal_activa(&mut tx, establecimiento_id, terminal_id).await?,
        "Un establecimiento inactivo debe impedir el uso de terminales"
    );

    // Verificar SELECT FOR UPDATE dentro de la transacción.
    // El establecimiento principal quedó inactivo en la prueba anterior.
    sqlx::query("UPDATE establecimientos SET activo = TRUE WHERE id = $1")
        .bind(establecimiento_id)
        .execute(&mut *tx)
        .await?;

    let usuario_bloqueado = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id)
        .await?
        .expect("El usuario debe poder bloquearse para login");

    assert_eq!(usuario_bloqueado.id, usuario_id);
    assert_eq!(usuario_bloqueado.establecimiento_id, establecimiento_id);
    assert_eq!(usuario_bloqueado.rol_id, rol_id);
    assert_eq!(usuario_bloqueado.intentos_fallidos, 0);
    assert!(usuario_bloqueado.bloqueado_hasta.is_none());

    // No debe permitir obtener un usuario de otro establecimiento.
    let usuario_ajeno_bloqueado =
        bloquear_usuario_login(&mut tx, otro_establecimiento_id, usuario_id).await?;

    assert!(usuario_ajeno_bloqueado.is_none());

    // Probar cuatro intentos incorrectos.
    for intento in 1..=4 {
        registrar_pin_incorrecto(&mut tx, usuario_id).await?;

        let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id)
            .await?
            .expect("Usuario de prueba");

        assert_eq!(usuario.intentos_fallidos, intento);
        assert!(usuario.bloqueado_hasta.is_none());
    }

    // El quinto intento debe bloquear al usuario.
    registrar_pin_incorrecto(&mut tx, usuario_id).await?;

    let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id)
        .await?
        .expect("Usuario de prueba");

    assert_eq!(usuario.intentos_fallidos, 5);
    assert!(
        usuario.bloqueado_hasta.is_some(),
        "El quinto intento debe activar el bloqueo"
    );

    // Simular un bloqueo vencido.
    sqlx::query(
        "UPDATE usuarios
         SET bloqueado_hasta = NOW() - INTERVAL '1 minute'
         WHERE id = $1",
    )
    .bind(usuario_id)
    .execute(&mut *tx)
    .await?;

    // El siguiente intento debe iniciar un nuevo ciclo.
    registrar_pin_incorrecto(&mut tx, usuario_id).await?;

    let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id)
        .await?
        .expect("Usuario de prueba");

    assert_eq!(usuario.intentos_fallidos, 1);
    assert!(usuario.bloqueado_hasta.is_none());

    // Una autenticación correcta debe limpiar el contador.
    reiniciar_intentos_pin(&mut tx, usuario_id).await?;

    let usuario = bloquear_usuario_login(&mut tx, establecimiento_id, usuario_id)
        .await?
        .expect("Usuario de prueba");

    assert_eq!(usuario.intentos_fallidos, 0);
    assert!(usuario.bloqueado_hasta.is_none());

    tx.rollback().await?;

    Ok(())
}
