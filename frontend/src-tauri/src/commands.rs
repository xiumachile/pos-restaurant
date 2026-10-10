use crate::database::DbState;
use serde::Deserialize;
use tauri::State;

/// ═══════════════════════════════════════════════════════════════════════
/// COMANDOS DE DOMINIO (Hallazgo 08 - Seguridad)
/// ═══════════════════════════════════════════════════════════════════════
/// 
/// Estos comandos reemplazan la API genérica de SQL (execute_transaction).
/// El frontend NO puede ejecutar SQL arbitrario, solo comandos específicos.
/// Esto previene inyección SQL si el frontend es comprometido.

#[derive(Debug, Deserialize)]
pub struct CreateOrderRequest {
    pub local_uuid: String,
    pub company_id: String,
    pub branch_id: String,
    pub terminal_id: Option<String>,
    pub table_id: Option<String>,
    pub order_number: String,
    pub order_type: String,
    pub status: String,
    pub guest_count: i32,
    pub waiter_id: Option<String>,
    pub waiter_name: Option<String>,
    pub notes: Option<String>,
    pub customer_id: Option<String>,
    pub customer_name: Option<String>,
    pub customer_phone: Option<String>,
    pub delivery_address: Option<String>,
    pub delivery_notes: Option<String>,
    pub idempotency_key: String,
}

#[tauri::command]
pub fn create_local_order(
    state: State<DbState>,
    order: CreateOrderRequest,
) -> Result<(), String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    conn.execute(
        "INSERT INTO local_orders (
            local_uuid, company_id, branch_id, terminal_id, table_id,
            order_number, order_type, status, subtotal, discount_total,
            net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
            waiter_id, waiter_name, notes, customer_id, customer_name, customer_phone,
            delivery_address, delivery_notes, idempotency_key, sync_status,
            created_at, updated_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, 0, 0, 0, 0, 0, 0, 0, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16, ?17, ?18, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        rusqlite::params![
            order.local_uuid,
            order.company_id,
            order.branch_id,
            order.terminal_id,
            order.table_id,
            order.order_number,
            order.order_type,
            order.status,
            order.guest_count,
            order.waiter_id,
            order.waiter_name,
            order.notes,
            order.customer_id,
            order.customer_name,
            order.customer_phone,
            order.delivery_address,
            order.delivery_notes,
            order.idempotency_key,
        ],
    )
    .map_err(|e| format!("Error creando order: {}", e))?;
    
    Ok(())
}

#[derive(Debug, Deserialize)]
pub struct CreateOrderItemRequest {
    pub local_uuid: String,
    pub order_local_uuid: String,
    pub product_id: String,
    pub product_name: String,
    pub quantity: i32,
    pub unit_price: i64,
    pub subtotal: i64,
    pub notes: Option<String>,
    pub kitchen_status: String,
    pub is_menu_item: bool,
    pub menu_item_id: Option<String>,
}

#[tauri::command]
pub fn create_order_item(
    state: State<DbState>,
    item: CreateOrderItemRequest,
) -> Result<(), String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    conn.execute(
        "INSERT INTO local_order_items (
            local_uuid, order_local_uuid, cloud_id, product_id, product_name,
            quantity, unit_price, subtotal, notes, kitchen_status,
            is_menu_item, menu_item_id, created_at
        ) VALUES (?1, ?2, NULL, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, CURRENT_TIMESTAMP)",
        rusqlite::params![
            item.local_uuid,
            item.order_local_uuid,
            item.product_id,
            item.product_name,
            item.quantity,
            item.unit_price,
            item.subtotal,
            item.notes,
            item.kitchen_status,
            item.is_menu_item,
            item.menu_item_id,
        ],
    )
    .map_err(|e| format!("Error creando order item: {}", e))?;
    
    Ok(())
}

#[derive(Debug, Deserialize)]
pub struct EnqueueSyncRequest {
    pub company_id: String,
    pub branch_id: String,
    pub entity_type: String,
    pub entity_local_uuid: String,
    pub action: String,
    pub payload: String,
}

#[tauri::command]
pub fn enqueue_sync_operation(
    state: State<DbState>,
    sync: EnqueueSyncRequest,
) -> Result<(), String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    conn.execute(
        "INSERT INTO sync_queue (
            company_id, branch_id, entity_type, entity_local_uuid,
            entity_cloud_id, action, payload, sync_status, attempts,
            max_attempts, last_error, next_retry_at, created_at, updated_at
        ) VALUES (?1, ?2, ?3, ?4, NULL, ?5, ?6, 'pending', 0, 5, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        rusqlite::params![
            sync.company_id,
            sync.branch_id,
            sync.entity_type,
            sync.entity_local_uuid,
            sync.action,
            sync.payload,
        ],
    )
    .map_err(|e| format!("Error encolando sync: {}", e))?;
    
    Ok(())
}

#[derive(Debug, Deserialize)]
pub struct UpdateOrderStatusRequest {
    pub local_uuid: String,
    pub status: String,
}

#[tauri::command]
pub fn update_order_status(
    state: State<DbState>,
    update: UpdateOrderStatusRequest,
) -> Result<(), String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let rows = conn.execute(
        "UPDATE local_orders SET status = ?1, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?2",
        rusqlite::params![update.status, update.local_uuid],
    )
    .map_err(|e| format!("Error actualizando status: {}", e))?;
    
    if rows == 0 {
        return Err(format!("Order {} no encontrado", update.local_uuid));
    }
    
    Ok(())
}

#[derive(Debug, Deserialize)]
pub struct CreateOrderWithSyncRequest {
    // Order fields
    pub local_uuid: String,
    pub company_id: String,
    pub branch_id: String,
    pub terminal_id: Option<String>,
    pub table_id: Option<String>,
    pub order_number: String,
    pub order_type: String,
    pub status: String,
    pub guest_count: i32,
    pub waiter_id: Option<String>,
    pub waiter_name: Option<String>,
    pub notes: Option<String>,
    pub customer_id: Option<String>,
    pub customer_name: Option<String>,
    pub customer_phone: Option<String>,
    pub delivery_address: Option<String>,
    pub delivery_notes: Option<String>,
    pub idempotency_key: String,
    // Sync payload
    pub sync_payload: String,
}

#[tauri::command]
pub fn create_order_with_sync(
    state: State<DbState>,
    request: CreateOrderWithSyncRequest,
) -> Result<(), String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    // Iniciar transacción para garantizar atomicidad
    let tx = conn.transaction().map_err(|e| format!("Error iniciando transacción: {}", e))?;
    
    // 1. Insertar order
    tx.execute(
        "INSERT INTO local_orders (
            local_uuid, company_id, branch_id, terminal_id, table_id,
            order_number, order_type, status, subtotal, discount_total,
            net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
            waiter_id, waiter_name, notes, customer_id, customer_name, customer_phone,
            delivery_address, delivery_notes, idempotency_key, sync_status,
            created_at, updated_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, 0, 0, 0, 0, 0, 0, 0, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16, ?17, ?18, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        rusqlite::params![
            request.local_uuid,
            request.company_id,
            request.branch_id,
            request.terminal_id,
            request.table_id,
            request.order_number,
            request.order_type,
            request.status,
            request.guest_count,
            request.waiter_id,
            request.waiter_name,
            request.notes,
            request.customer_id,
            request.customer_name,
            request.customer_phone,
            request.delivery_address,
            request.delivery_notes,
            request.idempotency_key,
        ],
    )
    .map_err(|e| format!("Error creando order: {}", e))?;
    
    // 2. Encolar evento de sincronización
    tx.execute(
        "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, 'pending', CURRENT_TIMESTAMP)",
        rusqlite::params![
            request.local_uuid,
            request.company_id,
            request.branch_id,
            "order",
            request.local_uuid,
            "create",
            request.sync_payload,
        ],
    )
    .map_err(|e| format!("Error encolando sync: {}", e))?;
    
    // 3. Actualizar estado de mesa (si aplica)
    if let Some(ref table_id) = request.table_id {
        tx.execute(
            "UPDATE local_tables SET status = 'occupied', current_order_uuid = ?1 WHERE uuid = ?2",
            rusqlite::params![request.local_uuid, table_id],
        )
        .map_err(|e| format!("Error actualizando mesa: {}", e))?;
        
        tx.execute(
            "INSERT OR REPLACE INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, created_at) VALUES (?1, 'occupied', ?2, ?3, ?4, CURRENT_TIMESTAMP)",
            rusqlite::params![table_id, request.local_uuid, request.company_id, request.branch_id],
        )
        .map_err(|e| format!("Error registrando mutación de mesa: {}", e))?;
    }
    
    // Commit de la transacción
    tx.commit().map_err(|e| format!("Error commit: {}", e))?;
    
    Ok(())
}
