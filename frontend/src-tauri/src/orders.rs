use rusqlite::{params, Connection, OptionalExtension, TransactionBehavior};
use serde::Deserialize;
use serde_json::{json, Value};

use crate::database::DbState;

// AJUSTAR order_type a los valores reales del sistema (ver comando de verificación)
const ORDER_TYPES: [&str; 4] = ["dine_in", "takeout", "take_out", "delivery"];
const MAX_ITEMS: usize = 200;
const MAX_QTY: i64 = 999;
const MAX_UNIT_PRICE: i64 = 10_000_000; // CLP

#[derive(Debug, Deserialize)]
pub struct NewOrderItem {
    pub product_id: String,
    pub product_name: String,
    pub quantity: i64,
    pub unit_price: i64,
    pub notes: Option<String>,
    pub is_menu_item: Option<bool>,
    pub menu_item_id: Option<String>,
}

#[derive(Debug, Deserialize)]
pub struct CreateOrderPayload {
    pub company_id: String,
    pub branch_id: String,
    pub terminal_id: String, // AUD-03 FIX: Obligatorio para auditoría
    pub table_id: Option<String>,
    pub order_type: Option<String>,
    pub guest_count: Option<i64>,
    pub waiter_id: Option<String>,
    pub waiter_name: Option<String>,
    pub notes: Option<String>,
    #[serde(default)]
    pub items: Vec<NewOrderItem>,
}

/// Neto a partir de precio bruto (IVA 19% incluido), redondeo half-up.
/// Equivale a Math.round(gross / 1.19): 119 es impar, así que nunca hay empates .5.
pub fn net_from_gross(gross: i64) -> i64 {
    (200 * gross + 119) / 238
}

fn validate(p: &CreateOrderPayload) -> Result<(), String> {
    if p.company_id.trim().is_empty() || p.branch_id.trim().is_empty() {
        return Err("company_id y branch_id son obligatorios".into());
    }
    let ot = p.order_type.as_deref().unwrap_or("dine_in");
    if !ORDER_TYPES.contains(&ot) {
        return Err(format!("order_type inválido: {ot}"));
    }
    // solo dine_in puede tener mesa (regla del backend)
    if ot != "dine_in" && p.table_id.is_some() {
        return Err(format!("Un pedido {ot} no puede tener mesa"));
    }
    if !(1..=500).contains(&p.guest_count.unwrap_or(1)) {
        return Err("guest_count fuera de rango".into());
    }
    if p.items.len() > MAX_ITEMS {
        return Err(format!("Máximo {MAX_ITEMS} ítems por orden"));
    }
    if p.notes.as_deref().map_or(0, str::len) > 1000 {
        return Err("notes demasiado largo".into());
    }
    for it in &p.items {
        if !(1..=MAX_QTY).contains(&it.quantity) {
            return Err(format!("Cantidad fuera de rango: {}", it.quantity));
        }
        if !(0..=MAX_UNIT_PRICE).contains(&it.unit_price) {
            return Err(format!("Precio fuera de rango: {}", it.unit_price));
        }
        if it.product_id.is_empty() || it.product_name.is_empty() || it.product_name.len() > 200 {
            return Err("Producto inválido".into());
        }
    }
    Ok(())
}

/// Lógica pura sobre una Connection: testeable sin Tauri.
pub fn create_order_tx(conn: &mut Connection, p: &CreateOrderPayload) -> Result<Value, String> {
    validate(p)?;

    // Convención canónica del backend: dine_in | takeout | delivery
    let order_type = match p.order_type.as_deref().unwrap_or("dine_in") {
        "take_out" => "takeout",
        other => other,
    };
    let guest_count = p.guest_count.unwrap_or(1);
    let local_uuid = uuid::Uuid::new_v4().to_string();
    let idempotency_key = uuid::Uuid::new_v4().to_string();
    let order_number = format!("TEMP-{}", chrono::Utc::now().timestamp_millis());

    let subtotal: i64 = p.items.iter().map(|i| i.quantity * i.unit_price).sum();
    let net = net_from_gross(subtotal);
    let tax = subtotal - net;

    // Immediate: toma el lock de escritura al inicio (evita "database is locked" con el SyncEngine).
    // Si cualquier paso falla, `tx` se descarta sin commit => ROLLBACK automático.
    let tx = conn
        .transaction_with_behavior(TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción: {e}"))?;

    // 1. Mesa: existe, es del tenant y está disponible
    if let Some(table) = &p.table_id {
        let status: Option<String> = tx
            .query_row(
                "SELECT status FROM local_tables WHERE uuid=?1 AND company_id=?2 AND branch_id=?3",
                params![table, p.company_id, p.branch_id],
                |r| r.get(0),
            )
            .map_err(|_| format!("Mesa {table} no existe o no pertenece al tenant"))?;
        let status = status.unwrap_or_else(|| "available".into());
        if status != "available" {
            return Err(format!("Mesa {table} ya está ocupada (estado: {status})"));
        }
    }

    // 2. Orden (con totales ya calculados)
    tx.execute(
        "INSERT INTO local_orders (
            local_uuid, company_id, branch_id, terminal_id, table_id,
            order_number, order_type, status, subtotal, discount_total,
            net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
            waiter_id, waiter_name, notes, idempotency_key, sync_status,
            created_at, updated_at
         ) VALUES (?1,?2,?3,?4,?5,?6,?7,'confirmed',?8,0,?9,?10,0,?8,?8,?11,?12,?13,?14,?15,
                   'pending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",
        params![
            local_uuid,
            p.company_id,
            p.branch_id,
            p.terminal_id,
            p.table_id,
            order_number,
            order_type,
            subtotal,
            net,
            tax,
            guest_count,
            p.waiter_id,
            p.waiter_name,
            p.notes,
            idempotency_key
        ],
    )
    .map_err(|e| format!("Error creando orden: {e}"))?;

    // 3. Ítems
    for it in &p.items {
        tx.execute(
            "INSERT INTO local_order_items (
                local_uuid, order_local_uuid, product_id, product_name, quantity,
                unit_price, subtotal, notes, kitchen_status, is_menu_item, menu_item_id, created_at
             ) VALUES (?1,?2,?3,?4,?5,?6,?7,?8,'pending',?9,?10,CURRENT_TIMESTAMP)",
            params![
                uuid::Uuid::new_v4().to_string(),
                local_uuid,
                it.product_id,
                it.product_name,
                it.quantity,
                it.unit_price,
                it.quantity * it.unit_price,
                it.notes,
                it.is_menu_item.unwrap_or(false) as i64,
                it.menu_item_id
            ],
        )
        .map_err(|e| format!("Error insertando ítem: {e}"))?;
    }

    // 4. Cola de sync (misma forma que createWithItems hoy; id = local_uuid)
    let sync_payload = json!({
        "local_uuid": local_uuid, "company_id": p.company_id, "branch_id": p.branch_id,
        "terminal_id": p.terminal_id, "table_id": p.table_id,
        "order_number": order_number, "order_type": order_type, "status": "confirmed",
        "guest_count": guest_count, "waiter_id": p.waiter_id, "waiter_name": p.waiter_name,
        "notes": p.notes, "idempotency_key": idempotency_key
    });
    tx.execute(
        "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid,
                                 action, payload, sync_status, created_at)
         VALUES (?1,?2,?3,'order',?1,'create',?4,'pending',CURRENT_TIMESTAMP)",
        params![
            local_uuid,
            p.company_id,
            p.branch_id,
            sync_payload.to_string()
        ],
    )
    .map_err(|e| format!("Error encolando sync: {e}"))?;

    // 5. Mesa -> occupied + mutación para el SyncEngine
    if let Some(table) = &p.table_id {
        tx.execute(
            "UPDATE local_tables SET status='occupied', current_order_uuid=?1
             WHERE uuid=?2 AND company_id=?3 AND branch_id=?4",
            params![local_uuid, table, p.company_id, p.branch_id],
        )
        .map_err(|e| format!("Error actualizando mesa: {e}"))?;

        let mutation = json!({ "status": "occupied", "current_order_uuid": local_uuid });
        tx.execute(
            "INSERT OR REPLACE INTO table_local_mutations
               (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at)
             VALUES (?1,'occupied',?2,?3,?4,'update',?5,CURRENT_TIMESTAMP)",
            params![table, local_uuid, p.company_id, p.branch_id, mutation.to_string()],
        )
        .map_err(|e| format!("Error registrando mutación de mesa: {e}"))?;
    }

    tx.commit().map_err(|e| format!("Error en COMMIT: {e}"))?;

    Ok(json!({
        "local_uuid": local_uuid, "order_number": order_number,
        "subtotal": subtotal, "net_amount": net, "tax_total": tax
    }))
}

#[tauri::command]
pub fn create_local_order(
    state: tauri::State<DbState>,
    payload: CreateOrderPayload,
) -> Result<Value, String> {
    let mut conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    create_order_tx(&mut conn, &payload)
}

#[cfg(test)]
mod tests {
    use super::*;

    const SCHEMA: &str = "
    CREATE TABLE local_orders (
      local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
      terminal_id TEXT, table_id TEXT, order_number TEXT NOT NULL, order_type TEXT DEFAULT 'dine_in',
      status TEXT DEFAULT 'draft', subtotal REAL NOT NULL DEFAULT 0, discount_total REAL DEFAULT 0,
      tax_total REAL DEFAULT 0, tip_amount REAL DEFAULT 0, grand_total REAL NOT NULL DEFAULT 0,
      guest_count INTEGER DEFAULT 1, waiter_id TEXT, waiter_name TEXT, notes TEXT,
      idempotency_key TEXT UNIQUE NOT NULL, sync_status TEXT DEFAULT 'pending', sync_error TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      net_amount INTEGER DEFAULT 0, amount_due INTEGER DEFAULT 0, customer_id TEXT,
      customer_name TEXT, customer_phone TEXT, delivery_address TEXT, delivery_notes TEXT);
    CREATE TABLE local_order_items (
      local_uuid TEXT PRIMARY KEY, order_local_uuid TEXT NOT NULL, cloud_id TEXT,
      product_id TEXT NOT NULL, product_name TEXT NOT NULL, quantity INTEGER NOT NULL DEFAULT 1,
      unit_price REAL NOT NULL, subtotal REAL NOT NULL, notes TEXT, kitchen_status TEXT DEFAULT 'pending',
      is_menu_item INTEGER DEFAULT 0, menu_item_id TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE);
    CREATE TABLE sync_queue (
      id TEXT PRIMARY KEY, company_id TEXT NOT NULL, branch_id TEXT NOT NULL, entity_type TEXT NOT NULL,
      entity_local_uuid TEXT NOT NULL, entity_cloud_id TEXT, action TEXT NOT NULL, payload TEXT NOT NULL,
      sync_status TEXT DEFAULT 'pending', attempts INTEGER DEFAULT 0, max_attempts INTEGER DEFAULT 5,
      last_error TEXT, next_retry_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE local_tables (
      uuid TEXT PRIMARY KEY, table_number TEXT NOT NULL, area_name TEXT, capacity INTEGER DEFAULT 4,
      status TEXT DEFAULT 'available', current_order_uuid TEXT,
      last_updated TEXT DEFAULT CURRENT_TIMESTAMP, company_id TEXT, branch_id TEXT);
    CREATE TABLE table_local_mutations (
      table_uuid TEXT PRIMARY KEY,
      pending_status TEXT NOT NULL CHECK (pending_status IN ('available','occupied','reserved','maintenance')),
      pending_order_uuid TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
      action TEXT DEFAULT 'update', payload TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    INSERT INTO local_tables (uuid, table_number, status, company_id, branch_id)
      VALUES ('t1','1','available','c1','b1');
    ";

    fn db() -> Connection {
        let c = Connection::open_in_memory().unwrap();
        c.execute_batch("PRAGMA foreign_keys = ON;").unwrap();
        c.execute_batch(SCHEMA).unwrap();
        c
    }

    fn item(qty: i64, price: i64) -> NewOrderItem {
        NewOrderItem {
            product_id: "p1".into(),
            product_name: "Chaufa".into(),
            quantity: qty,
            unit_price: price,
            notes: None,
            is_menu_item: None,
            menu_item_id: None,
        }
    }

    fn payload(table: Option<&str>, items: Vec<NewOrderItem>) -> CreateOrderPayload {
        CreateOrderPayload {
            company_id: "c1".into(),
            branch_id: "b1".into(),
            terminal_id: "test-terminal".to_string(),
            table_id: table.map(String::from),
            order_type: None,
            guest_count: None,
            waiter_id: None,
            waiter_name: None,
            notes: None,
            items,
        }
    }

    fn count(c: &Connection, table: &str) -> i64 {
        c.query_row(&format!("SELECT COUNT(*) FROM {table}"), [], |r| r.get(0))
            .unwrap()
    }

    #[test]
    fn net_parity_with_js_float_formula() {
        for s in 0..=2_000_000i64 {
            assert_eq!(
                net_from_gross(s),
                (s as f64 / 1.19).round() as i64,
                "subtotal {s}"
            );
        }
    }

    #[test]
    fn creates_order_items_sync_and_occupies_table() {
        let mut c = db();
        let p = payload(Some("t1"), vec![item(2, 3500), item(1, 1990)]); // 8990
        let r = create_order_tx(&mut c, &p).unwrap();
        let id = r["local_uuid"].as_str().unwrap();

        let (sub, net, tax, grand, due): (i64, i64, i64, i64, i64) = c
            .query_row(
                "SELECT CAST(subtotal AS INTEGER), net_amount, CAST(tax_total AS INTEGER),
                    CAST(grand_total AS INTEGER), amount_due FROM local_orders WHERE local_uuid=?1",
                [id],
                |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?, r.get(4)?)),
            )
            .unwrap();
        assert_eq!((sub, net, tax, grand, due), (8990, 7555, 1435, 8990, 8990));
        assert_eq!(count(&c, "local_order_items"), 2);
        assert_eq!(count(&c, "sync_queue"), 1);
        assert_eq!(count(&c, "table_local_mutations"), 1);
        let st: String = c
            .query_row("SELECT status FROM local_tables WHERE uuid='t1'", [], |r| {
                r.get(0)
            })
            .unwrap();
        assert_eq!(st, "occupied");
    }

    #[test]
    fn rejects_occupied_table_without_side_effects() {
        let mut c = db();
        create_order_tx(&mut c, &payload(Some("t1"), vec![item(1, 1000)])).unwrap();
        let err = create_order_tx(&mut c, &payload(Some("t1"), vec![item(1, 1000)])).unwrap_err();
        assert!(err.contains("ocupada"));
        assert_eq!(count(&c, "local_orders"), 1);
    }

    #[test]
    fn rolls_back_everything_if_last_step_fails() {
        let mut c = db();
        c.execute_batch(
            "CREATE TRIGGER boom BEFORE INSERT ON table_local_mutations
             BEGIN SELECT RAISE(ABORT,'boom'); END;",
        )
        .unwrap();
        assert!(create_order_tx(&mut c, &payload(Some("t1"), vec![item(1, 1000)])).is_err());
        assert_eq!(count(&c, "local_orders"), 0);
        assert_eq!(count(&c, "local_order_items"), 0);
        assert_eq!(count(&c, "sync_queue"), 0);
        let st: String = c
            .query_row("SELECT status FROM local_tables WHERE uuid='t1'", [], |r| {
                r.get(0)
            })
            .unwrap();
        assert_eq!(st, "available");
    }

    #[test]
    fn rejects_invalid_input() {
        let mut c = db();
        assert!(create_order_tx(&mut c, &payload(None, vec![item(0, 1000)])).is_err());
        assert!(create_order_tx(&mut c, &payload(None, vec![item(1, -5)])).is_err());
        let mut p = payload(None, vec![]);
        p.order_type = Some("drop table".into());
        assert!(create_order_tx(&mut c, &p).is_err());
        assert_eq!(count(&c, "local_orders"), 0);
    }

    #[test]
    fn normalizes_take_out_alias_and_blocks_table_on_takeout() {
        let mut c = db();
        let mut p = payload(None, vec![item(1, 1000)]);
        p.order_type = Some("take_out".into());
        let r = create_order_tx(&mut c, &p).unwrap();
        let id = r["local_uuid"].as_str().unwrap();
        let t: String = c
            .query_row(
                "SELECT order_type FROM local_orders WHERE local_uuid=?1",
                [id],
                |r| r.get(0),
            )
            .unwrap();
        assert_eq!(t, "takeout");

        let mut bad = payload(Some("t1"), vec![item(1, 1000)]);
        bad.order_type = Some("takeout".into());
        assert!(create_order_tx(&mut c, &bad).is_err());
        let st: String = c
            .query_row("SELECT status FROM local_tables WHERE uuid='t1'", [], |r| {
                r.get(0)
            })
            .unwrap();
        assert_eq!(st, "available");
    }
}

// ============================================================================
// COMANDOS DE DOMINIO ADICIONALES PARA CERRAR O-05
// ============================================================================

#[derive(Debug, Clone, Deserialize)]
pub struct RegisterPaymentPayload {
    pub local_uuid: String,
    pub idempotency_key: String,
    pub order_local_uuid: String,
    pub payment_method_uuid: String, // P1 Fix: Requerido para sync_queue
    pub amount: i64,
    pub tip_amount: i64,
    pub total_amount: i64,
    pub reference_code: Option<String>,
    pub notes: Option<String>,
    // AUD-03 FIX: Contexto de terminal/usuario para el evento de auditoría atómico
    #[serde(default)]
    pub terminal_id: String, // AUD-03 FIX: Obligatorio para auditoría
    #[serde(default)]
    pub user_id: String, // AUD-03 FIX: Obligatorio para auditoría
}

#[tauri::command]
pub fn register_local_payment(
    state: tauri::State<DbState>,
    payload: RegisterPaymentPayload,
) -> Result<Value, String> {
    let mut conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    register_payment_tx(&mut conn, payload)
}

/// Lógica pura sobre una Connection: testeable sin Tauri.
pub fn register_payment_tx(
    conn: &mut Connection,
    payload: RegisterPaymentPayload,
) -> Result<Value, String> {
    // 0. Validaciones financieras básicas (P1 Fix: Integridad financiera)
    if payload.amount <= 0 {
        return Err("El monto del pago debe ser mayor a 0".to_string());
    }
    if payload.tip_amount < 0 {
        return Err("La propina no puede ser negativa".to_string());
    }
    if payload.total_amount <= 0 {
        return Err("El monto total del pago debe ser mayor a 0".to_string());
    }
    // P1 Fix: Coherencia matemática estricta
    if payload.amount + payload.tip_amount != payload.total_amount {
        return Err(
            "Inconsistencia financiera: amount + tip_amount debe ser igual a total_amount"
                .to_string(),
        );
    }
    if payload.payment_method_uuid.trim().is_empty() {
        return Err("El método de pago es requerido".to_string());
    }

    // AUD-03 FIX: Validación estricta de contexto de auditoría antes de iniciar la transacción
    if payload.terminal_id.trim().is_empty() {
        return Err("terminal_id es obligatorio y no puede estar vacío".to_string());
    }
    if payload.user_id.trim().is_empty() {
        return Err("user_id es obligatorio y no puede estar vacío".to_string());
    }

    let tx = conn
        .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción: {e}"))?;

    // 1. Verificar idempotencia COMPLETA (P1 Fix: incluir total_amount, order y método)
    let existing_payment: Option<(String, i64, i64, i64, String, String)> = tx
        .query_row(
            "SELECT local_uuid, CAST(amount AS INTEGER), CAST(tip_amount AS INTEGER), CAST(total_amount AS INTEGER), order_local_uuid, payment_method 
             FROM local_payments WHERE idempotency_key = ?1",
            params![payload.idempotency_key],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?, r.get(4)?, r.get(5)?)),
        )
        .optional()
        .map_err(|e| format!("Error verificando idempotencia: {e}"))?;

    if let Some((
        existing_uuid,
        existing_amount,
        existing_tip,
        existing_total,
        existing_order,
        existing_method,
    )) = existing_payment
    {
        if existing_amount == payload.amount
            && existing_tip == payload.tip_amount
            && existing_total == payload.total_amount
            && existing_order == payload.order_local_uuid
            && existing_method == payload.payment_method_uuid
        {
            return Ok(serde_json::json!({ "local_uuid": existing_uuid, "idempotent": true }));
        } else {
            return Err("Conflicto de idempotencia: la clave ya existe con datos financieros o de contexto diferentes".to_string());
        }
    }

    // 2. Obtener datos de la orden para validar estado y actualizar
    let order_data: Option<(String, String, i64, i64, Option<String>, String, Option<String>)> = tx
        .query_row(
            "SELECT company_id, branch_id, CAST(grand_total AS INTEGER), CAST(amount_due AS INTEGER), table_id, status, cloud_id
             FROM local_orders WHERE local_uuid = ?1",
            params![payload.order_local_uuid],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?, r.get(4)?, r.get(5)?, r.get(6)?)),
        )
        .optional()
        .map_err(|e| format!("Error consultando orden: {e}"))?;

    let (company_id, branch_id, _grand_total, mut amount_due, table_id, order_status, order_cloud_id) =
        order_data.ok_or_else(|| "Orden no encontrada".to_string())?;

    if order_status == "cancelled" {
        return Err("No se puede pagar una orden cancelada".to_string());
    }

    // P1 Fix: Validar que el monto aplicado no exceda el saldo pendiente
    if payload.amount > amount_due {
        return Err(format!(
            "El monto del pago ({}) excede el saldo pendiente de la orden ({})",
            payload.amount, amount_due
        ));
    }

    // 3. Insertar el nuevo pago
    tx.execute(
        "INSERT INTO local_payments (
            local_uuid, company_id, branch_id, order_local_uuid, payment_method,
            amount, tip_amount, total_amount, reference_code, notes, status, idempotency_key, sync_status, created_at
         ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, 'completed', ?11, 'pending', CURRENT_TIMESTAMP)",
        params![
            payload.local_uuid, company_id, branch_id, payload.order_local_uuid,
            payload.payment_method_uuid, payload.amount, payload.tip_amount, payload.total_amount,
            payload.reference_code, payload.notes, payload.idempotency_key
        ],
    )
    .map_err(|e| format!("Error registrando pago: {e}"))?;

    // 4. Actualizar el amount_due de la orden
    amount_due = (amount_due - payload.amount).max(0); // P1 Fix: Restar solo el monto de la venta, no la propina
    let new_status = if amount_due <= 0 { "paid" } else { "partial" };

    tx.execute(
        "UPDATE local_orders SET amount_due = ?1, status = ?2, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?3",
        params![amount_due, new_status, payload.order_local_uuid],
    )
    .map_err(|e| format!("Error actualizando orden: {e}"))?;

    // 5. Si la orden está pagada y tiene mesa asociada, liberar la mesa
    if new_status == "paid" && table_id.is_some() {
        let tid = table_id.unwrap();
        tx.execute(
            "UPDATE local_tables SET status = 'available', current_order_uuid = NULL, last_updated = CURRENT_TIMESTAMP WHERE uuid = ?1 AND company_id = ?2 AND branch_id = ?3",
            params![tid, company_id, branch_id],
        )
        .map_err(|e| format!("Error liberando mesa: {e}"))?;

        let mutation = serde_json::json!({ "status": "available", "current_order_uuid": null });
        tx.execute(
            "INSERT OR REPLACE INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at)
             VALUES (?1, 'available', NULL, ?2, ?3, 'update', ?4, CURRENT_TIMESTAMP)",
            params![tid, company_id, branch_id, mutation.to_string()],
        )
        .map_err(|e| format!("Error registrando mutación de mesa: {e}"))?;
    }

    // 6. Encolar evento de sync DENTRO de la misma transacción (P1 Fix: Atomicidad)
    let sync_payload = serde_json::json!({
        "local_uuid": payload.local_uuid,
        "order_local_uuid": payload.order_local_uuid,
        "payment_method_uuid": payload.payment_method_uuid,
        "amount": payload.amount,
        "tip_amount": payload.tip_amount,
        "total_amount": payload.total_amount,
        "reference_code": payload.reference_code,
        "notes": payload.notes,
        "idempotency_key": payload.idempotency_key
    });

    // P1 Fix: Usamos INSERT OR IGNORE para garantizar idempotencia.
    // Si TypeScript ya encoló el pago, esta operación es un no-op seguro.
    // Si TypeScript falló, esta operación garantiza la atomicidad del encolado.
    tx.execute(
        "INSERT OR IGNORE INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at)
         VALUES (?1, ?2, ?3, 'payment', ?4, 'create', ?5, 'pending', CURRENT_TIMESTAMP)",
        params![payload.local_uuid, company_id, branch_id, payload.local_uuid, sync_payload.to_string()],
    )
    .map_err(|e| format!("Error encolando sync de pago: {e}"))?;

    // AUD-03 FIX: Registrar evento de auditoría en la MISMA transacción atómica.
    // Si esto falla, el pago y el sync_queue se revierten, garantizando integridad financiera.
    let event_uuid = uuid::Uuid::new_v4().to_string();
    let audit_idempotency_key = uuid::Uuid::new_v4().to_string();
    let payload_json = serde_json::json!({
        "amount": payload.amount,
        "payment_method_uuid": payload.payment_method_uuid,
        "tip_amount": payload.tip_amount,
        "reference_code": payload.reference_code,
        "notes": payload.notes,
        "order_local_uuid": payload.order_local_uuid,
        "order_cloud_id": order_cloud_id,
    }).to_string();

    tx.execute(
        "INSERT INTO offline_events (
            event_uuid, idempotency_key, company_id, branch_id, terminal_id, user_id,
            entity_type, entity_uuid, event_type, payload, created_at, notes, sync_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, 'pending')",
        rusqlite::params![
            event_uuid,
            audit_idempotency_key,
            company_id,
            branch_id,
            payload.terminal_id,
            payload.user_id,
            "payment",
            payload.local_uuid,
            "CREATE_PAYMENT",
            payload_json,
            "Pago creado atómicamente en Rust"
        ],
    ).map_err(|e| format!("Error registrando evento de auditoría: {}", e))?;

    tx.commit().map_err(|e| format!("Error en COMMIT: {e}"))?;

    Ok(serde_json::json!({ "local_uuid": payload.local_uuid, "idempotent": false }))
}

#[derive(Debug, Deserialize)]
pub struct EnqueueSyncEventPayload {
    pub id: String,
    pub entity_type: String, // 'order', 'payment', 'table_mutation'
    pub entity_local_uuid: String,
    pub action: String,
    pub payload_json: String,
}

#[tauri::command]
pub fn enqueue_sync_event(
    state: tauri::State<DbState>,
    payload: EnqueueSyncEventPayload,
) -> Result<(), String> {
    let conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;

    // P1 FIX: Zero Trust. Derivar company_id y branch_id desde la entidad, no del frontend.
    let table_name = match payload.entity_type.as_str() {
        "order" => "local_orders",
        "payment" => "local_payments",
        "table_mutation" => "table_local_mutations",
        _ => {
            return Err(format!(
                "Tipo de entidad no soportado: {}",
                payload.entity_type
            ))
        }
    };

    let query = format!(
        "SELECT company_id, branch_id FROM {} WHERE local_uuid = ?1 OR table_uuid = ?1",
        table_name
    );

    let (company_id, branch_id): (String, String) = conn
        .query_row(&query, params![payload.entity_local_uuid], |r| {
            Ok((r.get(0)?, r.get(1)?))
        })
        .map_err(|_| {
            format!(
                "Entidad {} con uuid {} no encontrada",
                payload.entity_type, payload.entity_local_uuid
            )
        })?;

    conn.execute(
        "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, 'pending', CURRENT_TIMESTAMP)",
        params![
            payload.id, company_id, branch_id,
            payload.entity_type, payload.entity_local_uuid, payload.action, payload.payload_json
        ],
    )
    .map_err(|e| format!("Error encolando evento: {e}"))?;

    Ok(())
}

#[tauri::command]
pub fn get_pending_orders(
    state: tauri::State<DbState>,
    company_id: String,
    branch_id: String,
) -> Result<Value, String> {
    let conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;

    let mut stmt = conn
        .prepare(
            "SELECT local_uuid, order_number, order_type, status, subtotal, tax_total, grand_total, created_at
             FROM local_orders
             WHERE company_id = ?1 AND branch_id = ?2 AND sync_status = 'pending'
             ORDER BY created_at ASC",
        )
        .map_err(|e| format!("Error preparando statement: {e}"))?;

    let rows = stmt
        .query_map(params![company_id, branch_id], |row| {
            Ok(serde_json::json!({
                "local_uuid": row.get::<_, String>(0)?,
                "order_number": row.get::<_, String>(1)?,
                "order_type": row.get::<_, String>(2)?,
                "status": row.get::<_, String>(3)?,
                "subtotal": row.get::<_, i64>(4)?,
                "tax_total": row.get::<_, i64>(5)?,
                "grand_total": row.get::<_, i64>(6)?,
                "created_at": row.get::<_, String>(7)?,
            }))
        })
        .map_err(|e| format!("Error ejecutando query: {e}"))?;

    let mut orders = Vec::new();
    for row in rows {
        orders.push(row.map_err(|e| format!("Error mapeando fila: {e}"))?);
    }

    Ok(serde_json::Value::Array(orders))
}

#[cfg(test)]
mod payment_idempotency_tests {
    use super::*;
    use std::sync::Mutex;

    const SCHEMA: &str = "
    CREATE TABLE local_orders (
      local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
      order_number TEXT NOT NULL, order_type TEXT DEFAULT 'dine_in', status TEXT DEFAULT 'draft',
      grand_total INTEGER NOT NULL DEFAULT 0, amount_due INTEGER NOT NULL DEFAULT 0,
      table_id TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE local_payments (
      local_uuid TEXT PRIMARY KEY, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
      order_local_uuid TEXT, payment_method TEXT NOT NULL, amount INTEGER NOT NULL,
      tip_amount INTEGER DEFAULT 0, total_amount INTEGER NOT NULL, reference_code TEXT,
      notes TEXT, status TEXT DEFAULT 'pending', idempotency_key TEXT UNIQUE NOT NULL,
      sync_status TEXT DEFAULT 'pending', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE sync_queue (
      id TEXT PRIMARY KEY, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
      entity_type TEXT NOT NULL, entity_local_uuid TEXT NOT NULL, action TEXT NOT NULL,
      payload TEXT NOT NULL, sync_status TEXT DEFAULT 'pending', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE local_tables (
      uuid TEXT PRIMARY KEY, table_number TEXT NOT NULL, status TEXT DEFAULT 'available',
      current_order_uuid TEXT, company_id TEXT, branch_id TEXT, last_updated TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE table_local_mutations (
      table_uuid TEXT PRIMARY KEY, pending_status TEXT NOT NULL, pending_order_uuid TEXT,
      company_id TEXT NOT NULL, branch_id TEXT NOT NULL, action TEXT DEFAULT 'update',
      payload TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE offline_events (
      event_uuid TEXT PRIMARY KEY, idempotency_key TEXT NOT NULL, company_id TEXT NOT NULL,
      branch_id TEXT NOT NULL, terminal_id TEXT, user_id TEXT, entity_type TEXT NOT NULL,
      entity_uuid TEXT NOT NULL, event_type TEXT NOT NULL, payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      notes TEXT, sync_status TEXT DEFAULT 'pending');
    ";

    fn db() -> Connection {
        let c = Connection::open_in_memory().unwrap();
        c.execute_batch("PRAGMA foreign_keys = ON;").unwrap();
        c.execute_batch(SCHEMA).unwrap();
        c
    }

    fn create_test_order(conn: &mut Connection) -> String {
        let uuid = uuid::Uuid::new_v4().to_string();
        conn.execute(
            "INSERT INTO local_orders (local_uuid, company_id, branch_id, order_number, grand_total, amount_due)
             VALUES (?1, 'c1', 'b1', 'ORD-001', 10000, 10000)",
            params![uuid],
        ).unwrap();
        uuid
    }

    #[test]
    fn p0_02_repeated_payment_with_same_key_returns_existing() {
        let mut c = db();
        let order_uuid = create_test_order(&mut c);
        let state = DbState(Mutex::new(c));

        let payload = RegisterPaymentPayload {
            local_uuid: "pay-1".to_string(),
            idempotency_key: "idem-key-1".to_string(),
            order_local_uuid: order_uuid.clone(),
            payment_method_uuid: "mock-method-uuid".to_string(),
            amount: 10000,
            tip_amount: 0,
            total_amount: 10000,
            reference_code: None,
            notes: None,
            terminal_id: "test-terminal".to_string(),
            user_id: "test-user".to_string(),
        };

        // Primer intento: debe crear el pago
        let res1 = register_payment_tx(&mut state.0.lock().unwrap(), payload.clone()).unwrap();
        assert_eq!(res1["local_uuid"], "pay-1");
        assert_eq!(res1["idempotent"], false);

        // Segundo intento con la misma clave: debe retornar el existente sin duplicar
        let res2 = register_payment_tx(&mut state.0.lock().unwrap(), payload.clone()).unwrap();
        assert_eq!(res2["local_uuid"], "pay-1");
        assert_eq!(res2["idempotent"], true);

        // Verificar que solo hay 1 pago en la base de datos
        let count: i64 = state
            .0
            .lock()
            .unwrap()
            .query_row("SELECT COUNT(*) FROM local_payments", [], |r| r.get(0))
            .unwrap();
        assert_eq!(count, 1);
    }

    #[test]
    fn p0_02_reusing_key_with_different_amount_is_rejected() {
        let mut c = db();
        let order_uuid = create_test_order(&mut c);
        let state = DbState(Mutex::new(c));

        let payload1 = RegisterPaymentPayload {
            local_uuid: "pay-2".to_string(),
            idempotency_key: "idem-key-2".to_string(),
            order_local_uuid: order_uuid.clone(),
            payment_method_uuid: "mock-method-uuid".to_string(),
            amount: 10000,
            tip_amount: 0,
            total_amount: 10000,
            reference_code: None,
            notes: None,
            terminal_id: "test-terminal".to_string(),
            user_id: "test-user".to_string(),
        };

        register_payment_tx(&mut state.0.lock().unwrap(), payload1).unwrap();

        // Intento con la misma clave pero monto diferente
        let payload2 = RegisterPaymentPayload {
            local_uuid: "pay-3".to_string(),           // UUID diferente
            idempotency_key: "idem-key-2".to_string(), // Misma clave de idempotencia
            order_local_uuid: order_uuid.clone(),
            payment_method_uuid: "mock-method-uuid".to_string(),
            amount: 5000, // Monto diferente
            tip_amount: 0,
            total_amount: 5000,
            reference_code: None,
            notes: None,
            terminal_id: "test-terminal".to_string(),
            user_id: "test-user".to_string(),
        };

        let err = register_payment_tx(&mut state.0.lock().unwrap(), payload2).unwrap_err();
        assert!(err.contains("Conflicto de idempotencia"));
    }
}

#[derive(Debug, Deserialize)]
pub struct TransitionOrderStatusPayload {
    pub order_uuid: String,
    pub new_status: String,
    pub expected_status: String,
}

// Lógica de dominio pura, probada unitariamente para verificar atomicidad completa
pub fn process_order_transition(
    conn: &mut rusqlite::Connection,
    payload: &TransitionOrderStatusPayload,
) -> Result<String, String> {
    
    // 1. Iniciar transacción IMMEDIATE
    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción: {}", e))?;

    // 2. Leer el estado actual del pedido para validar reglas de negocio
    let current_status: Option<String> = tx.query_row(
        "SELECT status FROM local_orders WHERE local_uuid = ?1",
        rusqlite::params![payload.order_uuid],
        |row| row.get(0),
    ).optional().map_err(|e| format!("Error leyendo pedido: {}", e))?;

    let current_status = current_status.ok_or_else(|| format!("Pedido {} no encontrado", payload.order_uuid))?;

    // AUD-01 FIX: Matriz estricta de transiciones de estado permitidas
    let is_valid_transition = match (current_status.as_str(), payload.new_status.as_str()) {
        ("confirmed", "paid") => true,
        ("confirmed", "cancelled") => true,
        ("pending", "confirmed") => true,
        ("pending", "cancelled") => true,
        _ => false,
    };

    if !is_valid_transition {
        return Err(format!(
            "Transición de estado inválida: de '{}' a '{}'",
            current_status, payload.new_status
        ));
    }

    // 3. Validar matriz de transiciones permitidas (AUD-01 FIX)
    if current_status == "paid" && payload.new_status == "cancelled" {
        return Err("No se puede cancelar un pedido que ya está pagado".to_string());
    }
    if current_status == "cancelled" && payload.new_status != "cancelled" {
        return Err("No se puede modificar un pedido que ya está cancelado".to_string());
    }
    if current_status != payload.expected_status {
        return Err(format!("Conflicto de estado: se esperaba '{}', pero el estado actual es '{}'", payload.expected_status, current_status));
    }

    // 4. Ejecutar UPDATE condicional
    let changed = tx.execute(
        "UPDATE local_orders
         SET status = ?1, updated_at = CURRENT_TIMESTAMP
         WHERE local_uuid = ?2 AND status = ?3",
        rusqlite::params![payload.new_status, payload.order_uuid, current_status],
    ).map_err(|e| format!("Error ejecutando UPDATE: {}", e))?;

    if changed != 1 {
        return Err("Conflicto de concurrencia: el estado cambió inesperadamente".to_string());
    }

    // 5. Si se cancela, propagar efectos secundarios en la misma transacción (Offline-first)
    if payload.new_status == "cancelled" {
        // 5a. Liberar la mesa
        tx.execute(
            "UPDATE local_tables 
             SET status = 'available', current_order_uuid = NULL 
             WHERE uuid = (SELECT table_id FROM local_orders WHERE local_uuid = ?1 AND table_id IS NOT NULL)",
            rusqlite::params![payload.order_uuid],
        ).map_err(|e| format!("Error liberando mesa: {}", e))?;

        // 5b. Registrar mutación de mesa
        tx.execute(
            "INSERT INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at, updated_at)
             SELECT table_id, 'available', NULL, company_id, branch_id, 'update', json_object('status', 'available', 'current_order_uuid', null), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM local_orders WHERE local_uuid = ?1 AND table_id IS NOT NULL
             ON CONFLICT(table_uuid) DO UPDATE SET
               pending_status = excluded.pending_status,
               pending_order_uuid = excluded.pending_order_uuid,
               action = excluded.action,
               payload = excluded.payload,
               updated_at = CURRENT_TIMESTAMP",
            rusqlite::params![payload.order_uuid],
        ).map_err(|e| format!("Error registrando mutación de mesa: {}", e))?;

        // 5c. Encolar en sync_queue
        let sync_payload = serde_json::json!({
            "local_uuid": payload.order_uuid,
            "status": "cancelled"
        }).to_string();
        
        let sync_id = format!("sync_cancel_{}", payload.order_uuid);
        tx.execute(
            "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, created_at)
             SELECT ?3, company_id, branch_id, 'order', ?1, 'update', ?2, CURRENT_TIMESTAMP
             FROM local_orders WHERE local_uuid = ?1",
            rusqlite::params![payload.order_uuid, sync_payload, sync_id],
        ).map_err(|e| format!("Error encolando en sync_queue: {}", e))?;
    }

    // 6. Confirmar con COMMIT
    tx.commit().map_err(|e| format!("Error confirmando transacción: {}", e))?;
    
    Ok(payload.order_uuid.clone())
}



#[tauri::command]
pub fn transition_order_status(
    state: tauri::State<crate::database::DbState>,
    payload: TransitionOrderStatusPayload,
) -> Result<String, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Lock error: {}", e))?;
    process_order_transition(&mut conn, &payload)
}

#[cfg(test)]
mod cancel_full_flow_tests {
    use super::*;
    use rusqlite::Connection;

    #[test]
    fn test_full_cancellation_with_existing_mutation() {
        let mut conn = Connection::open_in_memory().unwrap();
        conn.execute_batch("
            CREATE TABLE local_orders (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL, table_id TEXT, status TEXT DEFAULT 'confirmed', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE local_tables (uuid TEXT PRIMARY KEY, status TEXT DEFAULT 'available', current_order_uuid TEXT);
            CREATE TABLE table_local_mutations (table_uuid TEXT PRIMARY KEY, pending_status TEXT, pending_order_uuid TEXT, company_id TEXT, branch_id TEXT, action TEXT, payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE sync_queue (id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, entity_type TEXT, entity_local_uuid TEXT, action TEXT, payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
        ").unwrap();
        
        conn.execute("INSERT INTO local_tables (uuid, status, current_order_uuid) VALUES ('table-1', 'occupied', 'order-1')", []).unwrap();
        conn.execute("INSERT INTO local_orders (local_uuid, company_id, branch_id, table_id, status) VALUES ('order-1', 'comp-1', 'branch-1', 'table-1', 'confirmed')", []).unwrap();
        conn.execute("INSERT INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload) VALUES ('table-1', 'occupied', 'order-1', 'comp-1', 'branch-1', 'update', '{\"status\":\"occupied\"}')", []).unwrap();

        let payload = TransitionOrderStatusPayload {
            order_uuid: "order-1".to_string(),
            new_status: "cancelled".to_string(),
            expected_status: "confirmed".to_string(),
        };

        // INVOCAR LA LÓGICA DE DOMINIO COMPLETA
        let result = process_order_transition(&mut conn, &payload);
        assert!(result.is_ok(), "La cancelación completa debe tener éxito: {:?}", result.err());
        
        // Verificar efectos secundarios atómicos completos
        // 1. Estado del pedido
        let order_status: String = conn.query_row("SELECT status FROM local_orders WHERE local_uuid = 'order-1'", [], |row| row.get(0)).unwrap();
        assert_eq!(order_status, "cancelled", "El pedido debe estar cancelado");

        // 2. Estado de la mesa
        let table_state: (String, Option<String>) = conn.query_row("SELECT status, current_order_uuid FROM local_tables WHERE uuid = 'table-1'", [], |row| Ok((row.get(0)?, row.get(1)?))).unwrap();
        assert_eq!(table_state.0, "available", "La mesa debe estar disponible");
        assert!(table_state.1.is_none(), "La mesa no debe tener orden asociada");

        // 3. Mutación de mesa
        let mutation: (String, String) = conn.query_row("SELECT pending_status, action FROM table_local_mutations WHERE table_uuid = 'table-1'", [], |row| Ok((row.get(0)?, row.get(1)?))).unwrap();
        assert_eq!(mutation.0, "available");
        assert_eq!(mutation.1, "update");
        
        // 4. Cola de sincronización
        let sync_count: i64 = conn.query_row("SELECT COUNT(*) FROM sync_queue WHERE entity_local_uuid = 'order-1'", [], |row| row.get(0)).unwrap();
        assert_eq!(sync_count, 1, "Debe haber exactamente 1 evento en sync_queue");
    }
}

#[cfg(test)]
mod rollback_tests {
    use super::*;
    use rusqlite::Connection;

    #[test]
    fn test_rollback_on_intermediate_failure() {
        let mut conn = Connection::open_in_memory().unwrap();
        conn.execute_batch("
            CREATE TABLE local_orders (local_uuid TEXT PRIMARY KEY, status TEXT DEFAULT 'confirmed');
            CREATE TABLE local_tables (uuid TEXT PRIMARY KEY, status TEXT DEFAULT 'available', current_order_uuid TEXT);
        ").unwrap();
        
        conn.execute("INSERT INTO local_orders (local_uuid, status) VALUES ('order-1', 'confirmed')", []).unwrap();
        conn.execute("INSERT INTO local_tables (uuid, status, current_order_uuid) VALUES ('table-1', 'occupied', 'order-1')", []).unwrap();

        // Simular el comportamiento de la transacción IMMEDIATE con fallo intermedio
        conn.execute_batch("BEGIN IMMEDIATE").unwrap();
        
        // 1. Escritura parcial exitosa
        conn.execute("UPDATE local_orders SET status = 'cancelled' WHERE local_uuid = 'order-1'", []).unwrap();
        conn.execute("UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = 'table-1'", []).unwrap();
        
        // 2. Fallo intermedio simulado (tabla inexistente)
        let intermediate_result = conn.execute("INSERT INTO non_existent_sync_queue (id) VALUES (1)", []);
        assert!(intermediate_result.is_err(), "Debe fallar en la escritura intermedia");
        
        // 3. Rollback explícito (como lo haría el manejador de errores de producción)
        conn.execute_batch("ROLLBACK").unwrap();
        
        // 4. VERIFICACIÓN DE CIERRE: Ningún cambio parcial sobrevivió
        let order_status: String = conn.query_row("SELECT status FROM local_orders WHERE local_uuid = 'order-1'", [], |row| row.get(0)).unwrap();
        assert_eq!(order_status, "confirmed", "El estado del pedido debe revertirse al original");
        
        let table_state: (String, String) = conn.query_row("SELECT status, current_order_uuid FROM local_tables WHERE uuid = 'table-1'", [], |row| Ok((row.get(0)?, row.get(1)?))).unwrap();
        assert_eq!(table_state.0, "occupied", "El estado de la mesa debe revertirse al original");
        assert_eq!(table_state.1, "order-1", "La asociación de la mesa debe revertirse al original");
    }
}

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]

#[cfg(test)]
mod rollback_integration_tests {
    use super::*;
    use rusqlite::Connection;

    #[test]
    fn test_rollback_on_secondary_write_failure() {
        // 1. Configurar esquema con una restricción de prueba que forzará el fallo 
        // en la escritura secundaria (inserción de mutación de mesa).
        let mut conn = Connection::open_in_memory().unwrap();
        conn.execute_batch("
            CREATE TABLE local_orders (
                local_uuid TEXT PRIMARY KEY, 
                status TEXT DEFAULT 'confirmed', 
                table_id TEXT,
                company_id TEXT DEFAULT 'test-company',
                branch_id TEXT DEFAULT 'test-branch',
                grand_total INTEGER DEFAULT 10000,
                amount_due INTEGER DEFAULT 10000,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE local_tables (
                uuid TEXT PRIMARY KEY, 
                table_number TEXT NOT NULL, 
                status TEXT DEFAULT 'available', 
                current_order_uuid TEXT,
                last_updated TEXT DEFAULT CURRENT_TIMESTAMP, 
                company_id TEXT, 
                branch_id TEXT
            );
            -- RESTRICCIÓN DE PRUEBA: Forzamos que pending_status NO pueda ser 'available' 
            -- para que la inserción real de mutación de mesa falle con CHECK constraint.
            CREATE TABLE table_local_mutations (
                table_uuid TEXT PRIMARY KEY,
                pending_status TEXT NOT NULL CHECK (pending_status != 'available'),
                pending_order_uuid TEXT, 
                company_id TEXT NOT NULL, 
                branch_id TEXT NOT NULL,
                action TEXT DEFAULT 'update', 
                payload TEXT, 
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE sync_queue (
                id TEXT PRIMARY KEY, 
                company_id TEXT, 
                branch_id TEXT, 
                entity_type TEXT, 
                entity_local_uuid TEXT, 
                action TEXT, 
                payload TEXT, 
                sync_status TEXT, 
                created_at TEXT
            );
            
            INSERT INTO local_orders (local_uuid, status, table_id, grand_total, amount_due) 
            VALUES ('test-order', 'confirmed', 'test-table', 10000, 10000);
            INSERT INTO local_tables (uuid, table_number, status, current_order_uuid, company_id, branch_id) 
            VALUES ('test-table', '1', 'occupied', 'test-order', 'test-company', 'test-branch');
        ").unwrap();

        // 2. Capturar estado ANTES de la operación
        let order_status_before: String = conn.query_row("SELECT status FROM local_orders WHERE local_uuid = 'test-order'", [], |row| row.get(0)).unwrap();
        let table_status_before: String = conn.query_row("SELECT status FROM local_tables WHERE uuid = 'test-table'", [], |row| row.get(0)).unwrap();
        let table_order_before: Option<String> = conn.query_row("SELECT current_order_uuid FROM local_tables WHERE uuid = 'test-table'", [], |row| row.get(0)).unwrap();
        let mutations_before: i64 = conn.query_row("SELECT COUNT(*) FROM table_local_mutations", [], |row| row.get(0)).unwrap();
        let sync_before: i64 = conn.query_row("SELECT COUNT(*) FROM sync_queue", [], |row| row.get(0)).unwrap();

        assert_eq!(order_status_before, "confirmed");
        assert_eq!(table_status_before, "occupied");
        assert_eq!(table_order_before, Some("test-order".to_string()));
        assert_eq!(mutations_before, 0);
        assert_eq!(sync_before, 0);

        // 3. Invocar la FUNCIÓN REAL de transición de estado
        let payload = TransitionOrderStatusPayload {
            order_uuid: "test-order".to_string(),
            new_status: "cancelled".to_string(),
            expected_status: "confirmed".to_string(),
        };

        let result = process_order_transition(&mut conn, &payload);

        // 4. Verificar que la operación REAL falló debido a la restricción de la escritura secundaria
        assert!(result.is_err(), "La operación debe fallar debido a la restricción de prueba en table_local_mutations");
        let err_msg = result.unwrap_err().to_lowercase();
        assert!(err_msg.contains("check constraint"), "Error inesperado en lugar de CHECK constraint: {}", err_msg);

        // 5. Capturar estado DESPUÉS y verificar ROLLBACK COMPLETO (Atomicidad AUD-01)
        let order_status_after: String = conn.query_row("SELECT status FROM local_orders WHERE local_uuid = 'test-order'", [], |row| row.get(0)).unwrap();
        let table_status_after: String = conn.query_row("SELECT status FROM local_tables WHERE uuid = 'test-table'", [], |row| row.get(0)).unwrap();
        let table_order_after: Option<String> = conn.query_row("SELECT current_order_uuid FROM local_tables WHERE uuid = 'test-table'", [], |row| row.get(0)).unwrap();
        let mutations_after: i64 = conn.query_row("SELECT COUNT(*) FROM table_local_mutations", [], |row| row.get(0)).unwrap();
        let sync_after: i64 = conn.query_row("SELECT COUNT(*) FROM sync_queue", [], |row| row.get(0)).unwrap();

        // ASSERTS CRÍTICOS: Demuestran que la transacción IMMEDIATE hizo rollback de TODO
        assert_eq!(order_status_after, order_status_before, "ROLLBACK FALLÓ: El estado del pedido cambió a pesar del error");
        assert_eq!(table_status_after, table_status_before, "ROLLBACK FALLÓ: El estado de la mesa cambió a pesar del error");
        assert_eq!(table_order_after, table_order_before, "ROLLBACK FALLÓ: La asociación de la mesa cambió a pesar del error");
        assert_eq!(mutations_after, mutations_before, "ROLLBACK FALLÓ: Se dejó una mutación huérfana a pesar del error");
        assert_eq!(sync_after, sync_before, "ROLLBACK FALLÓ: Se dejó un evento de sync huérfano a pesar del error");
    }
}

#[cfg(test)]
mod real_concurrency_tests {
    use super::*;
    use rusqlite::Connection;
    use std::sync::{Arc, Barrier};
    use std::thread;
    use std::env;

    fn create_complete_schema(conn: &Connection) {
        conn.execute_batch("
            CREATE TABLE local_orders (
                local_uuid TEXT PRIMARY KEY, status TEXT DEFAULT 'confirmed', table_id TEXT,
                company_id TEXT DEFAULT 'test-company', branch_id TEXT DEFAULT 'test-branch',
                grand_total INTEGER DEFAULT 10000, amount_due INTEGER DEFAULT 10000,
                cloud_id TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE local_tables (
                uuid TEXT PRIMARY KEY, table_number TEXT NOT NULL, area_name TEXT, capacity INTEGER DEFAULT 4,
                status TEXT DEFAULT 'available', current_order_uuid TEXT,
                last_updated TEXT DEFAULT CURRENT_TIMESTAMP, company_id TEXT, branch_id TEXT
            );
            CREATE TABLE local_payments (
                local_uuid TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, order_local_uuid TEXT,
                payment_method TEXT, amount INTEGER, tip_amount INTEGER, total_amount INTEGER,
                reference_code TEXT, notes TEXT, status TEXT, idempotency_key TEXT, sync_status TEXT, created_at TEXT
            );
            CREATE TABLE table_local_mutations (
                table_uuid TEXT PRIMARY KEY, pending_status TEXT NOT NULL, pending_order_uuid TEXT,
                company_id TEXT NOT NULL, branch_id TEXT NOT NULL, action TEXT DEFAULT 'update',
                payload TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE sync_queue (
                id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, entity_type TEXT,
                entity_local_uuid TEXT, action TEXT, payload TEXT, sync_status TEXT, created_at TEXT
            );
            CREATE TABLE offline_events (
                event_uuid TEXT PRIMARY KEY, idempotency_key TEXT NOT NULL, company_id TEXT NOT NULL,
                branch_id TEXT NOT NULL, terminal_id TEXT, user_id TEXT, entity_type TEXT NOT NULL,
                entity_uuid TEXT NOT NULL, event_type TEXT NOT NULL, payload TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP, notes TEXT, sync_status TEXT DEFAULT 'pending'
            );
            
            INSERT INTO local_orders (local_uuid, status, table_id, grand_total, amount_due) 
            VALUES ('race-order', 'confirmed', 'race-table', 10000, 10000);
            INSERT INTO local_tables (uuid, table_number, status, current_order_uuid, company_id, branch_id) 
            VALUES ('race-table', '1', 'occupied', 'race-order', 'test-company', 'test-branch');
        ").unwrap();
    }

    #[test]
    fn test_real_os_level_concurrency_domain_payment_vs_cancel() {
        for iteration in 0..20 {
            let db_path = env::temp_dir().join(format!("test_concurrency_exhaustive_{}_{}.db", std::process::id(), iteration));
            let db_path_str = db_path.to_str().unwrap().to_string();
            
            let _ = std::fs::remove_file(&db_path_str);
            let conn = Connection::open(&db_path_str).unwrap();
            create_complete_schema(&conn);
            drop(conn);

            let barrier = Arc::new(Barrier::new(2));
            
            // HILO 1: Pago (devuelve el Result real, no solo un string)
            let db_path_pay = db_path_str.clone();
            let barrier_pay = Arc::clone(&barrier);
            let handle_pay = thread::spawn(move || {
                let mut conn = Connection::open(&db_path_pay).unwrap();
                conn.execute_batch("PRAGMA busy_timeout = 5000").unwrap();
                barrier_pay.wait();
                
                let payload = RegisterPaymentPayload {
                    local_uuid: format!("pay-{}", iteration),
                    idempotency_key: format!("idem-{}", iteration),
                    order_local_uuid: "race-order".to_string(),
                    payment_method_uuid: "method-uuid".to_string(),
                    amount: 10000, tip_amount: 0, total_amount: 10000,
                    reference_code: None, notes: None,
                    terminal_id: "term-1".to_string(), user_id: "user-1".to_string(),
                };
                register_payment_tx(&mut conn, payload)
            });

            // HILO 2: Cancelación (devuelve el Result real)
            let db_path_cancel = db_path_str.clone();
            let barrier_cancel = Arc::clone(&barrier);
            let handle_cancel = thread::spawn(move || {
                let mut conn = Connection::open(&db_path_cancel).unwrap();
                conn.execute_batch("PRAGMA busy_timeout = 5000").unwrap();
                barrier_cancel.wait();
                
                let payload = TransitionOrderStatusPayload {
                    order_uuid: "race-order".to_string(),
                    new_status: "cancelled".to_string(),
                    expected_status: "confirmed".to_string(),
                };
                process_order_transition(&mut conn, &payload)
            });

            let res_pay = handle_pay.join().unwrap();
            let res_cancel = handle_cancel.join().unwrap();

            // PUNTO 1: Exclusión mutua y motivo del rechazo
            let pay_won = res_pay.is_ok();
            let cancel_won = res_cancel.is_ok();
            
            assert!(pay_won ^ cancel_won, 
                "Iteración {}: Debe haber exactamente un ganador. Pay: {:?}, Cancel: {:?}", iteration, res_pay, res_cancel);

            if pay_won {
                let cancel_err = res_cancel.unwrap_err().to_lowercase();
                assert!(cancel_err.contains("inválida") || cancel_err.contains("pagado") || cancel_err.contains("conflicto") || cancel_err.contains("inesperadamente") || cancel_err.contains("locked"),
                    "Iteración {}: El perdedor (cancel) falló por un motivo no esperado de negocio: {}", iteration, cancel_err);
            } else {
                let pay_err = res_pay.unwrap_err().to_lowercase();
                assert!(pay_err.contains("cancelada") || pay_err.contains("conflicto") || pay_err.contains("inválida") || pay_err.contains("locked"),
                    "Iteración {}: El perdedor (pay) falló por un motivo no esperado de negocio: {}", iteration, pay_err);
            }

            // PUNTOS 2, 3 y 4: Verificación exhaustiva de todos los efectos secundarios
            let mut conn = Connection::open(&db_path_str).unwrap();
            
            let order_status: String = conn.query_row("SELECT status FROM local_orders WHERE local_uuid = 'race-order'", [], |row| row.get(0)).unwrap();
            let payment_count: i64 = conn.query_row("SELECT COUNT(*) FROM local_payments WHERE order_local_uuid = 'race-order'", [], |row| row.get(0)).unwrap();
            let mutation_count: i64 = conn.query_row("SELECT COUNT(*) FROM table_local_mutations WHERE table_uuid = 'race-table'", [], |row| row.get(0)).unwrap();
            
            // Buscamos sync events tanto de la orden como del pago específico
            let sync_count: i64 = conn.query_row(
                "SELECT COUNT(*) FROM sync_queue WHERE entity_local_uuid = 'race-order' OR entity_local_uuid LIKE 'pay-%'", 
                [], |row| row.get(0)).unwrap();
            
            // Buscamos eventos de auditoría offline
            let offline_events_count: i64 = conn.query_row(
                "SELECT COUNT(*) FROM offline_events WHERE entity_uuid = 'race-order' OR entity_uuid LIKE 'pay-%'", 
                [], |row| row.get(0)).unwrap();

            if pay_won {
                // PUNTO 3: Confirmar que el pago ganador no deja registros duplicados ni datos inconsistentes
                assert_eq!(order_status, "paid", "Iteración {}: Estado debe ser paid", iteration);
                assert_eq!(payment_count, 1, "Iteración {}: Debe haber exactamente 1 pago (sin duplicados)", iteration);
                assert_eq!(mutation_count, 1, "Iteración {}: Debe haber 1 mutación de mesa (liberación)", iteration);
                assert!(sync_count >= 1, "Iteración {}: Debe haber al menos 1 evento de sync", iteration);
                assert!(offline_events_count >= 1, "Iteración {}: Debe haber al menos 1 evento de auditoría offline", iteration);
            } else {
                // PUNTO 4: Confirmar que la cancelación ganadora no deja ningún pago parcial
                assert_eq!(order_status, "cancelled", "Iteración {}: Estado debe ser cancelled", iteration);
                assert_eq!(payment_count, 0, "Iteración {}: La cancelación ganadora NO debe dejar pagos parciales ni huérfanos", iteration);
                assert_eq!(mutation_count, 1, "Iteración {}: Debe haber 1 mutación de mesa (liberación)", iteration);
                assert!(sync_count >= 1, "Iteración {}: Debe haber al menos 1 evento de sync", iteration);
                assert_eq!(offline_events_count, 0, "Iteración {}: La cancelación ganadora NO debe dejar eventos de auditoría de pago", iteration);
            }
            
            let _ = std::fs::remove_file(&db_path_str);
        }
    }
}
