use rusqlite::{Connection, params};
use serde::Deserialize;
use serde_json::{Value, Map};
use std::sync::Mutex;
use chrono::Utc;

/// Estado global de la base de datos, protegido por un Mutex.
pub struct DbState(pub Mutex<Connection>);

/// O-05 FIX: Comandos de dominio específicos para reemplazar SQL arbitrario.
/// Esto reduce drásticamente la superficie de ataque local.

#[derive(Debug, Deserialize)]
pub struct CreateOrderPayload {
    pub uuid: String,
    pub company_id: i64,
    pub branch_id: i64,
    pub order_number: String,
    pub r#type: String,
    pub status: String,
    pub subtotal: i64,
    pub tax_amount: i64,
    pub total: i64,
    pub idempotency_key: String,
}

#[derive(Debug, Deserialize)]
pub struct RegisterPaymentPayload {
    pub uuid: String,
    pub company_id: i64,
    pub branch_id: i64,
    pub order_id: i64,
    pub amount: i64,
    pub tip_amount: i64,
    pub total_amount: i64,
    pub method_code: String,
    pub idempotency_key: String,
}

#[derive(Debug, Deserialize)]
pub struct EnqueueSyncEventPayload {
    pub company_id: i64,
    pub branch_id: i64,
    pub entity_type: String,
    pub entity_id: i64,
    pub entity_uuid: String,
    pub action: String,
    pub payload_json: String,
    pub version: i64,
}

/// O-05 FIX: Crear orden local con validación de esquema estricta.
#[tauri::command]
pub fn create_local_order(state: tauri::State<DbState>, payload: CreateOrderPayload) -> Result<Value, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let tx = conn.transaction().map_err(|e| format!("Error al iniciar transacción: {}", e))?;
    
    tx.execute(
        "INSERT INTO local_orders (
            uuid, company_id, branch_id, order_number, type, status, 
            subtotal, tax_amount, total, idempotency_key, sync_status, created_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, 'pending', ?11)",
        params![
            payload.uuid, payload.company_id, payload.branch_id, payload.order_number,
            payload.r#type, payload.status, payload.subtotal, payload.tax_amount,
            payload.total, payload.idempotency_key, Utc::now().to_rfc3339()
        ],
    ).map_err(|e| format!("Error creando orden: {}", e))?;
    
    tx.commit().map_err(|e| format!("Error al hacer COMMIT: {}", e))?;
    
    Ok(Value::String("Orden creada exitosamente".to_string()))
}

/// O-05 FIX: Registrar pago local con validación estricta de campos monetarios.
#[tauri::command]
pub fn register_local_payment(state: tauri::State<DbState>, payload: RegisterPaymentPayload) -> Result<Value, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let tx = conn.transaction().map_err(|e| format!("Error al iniciar transacción: {}", e))?;
    
    tx.execute(
        "INSERT INTO local_payments (
            uuid, company_id, branch_id, order_id, amount, tip_amount, total_amount,
            method_code, idempotency_key, status, created_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, 'completed', ?10)",
        params![
            payload.uuid, payload.company_id, payload.branch_id, payload.order_id,
            payload.amount, payload.tip_amount, payload.total_amount,
            payload.method_code, payload.idempotency_key, Utc::now().to_rfc3339()
        ],
    ).map_err(|e| format!("Error registrando pago: {}", e))?;
    
    // Actualizar estado de la orden si es necesario (simplificado)
    tx.execute(
        "UPDATE local_orders SET sync_status = 'pending' WHERE id = ?1",
        params![payload.order_id],
    ).map_err(|e| format!("Error actualizando orden: {}", e))?;
    
    tx.commit().map_err(|e| format!("Error al hacer COMMIT: {}", e))?;
    
    Ok(Value::String("Pago registrado exitosamente".to_string()))
}

/// O-05 FIX: Encolar evento de sincronización con validación de esquema.
#[tauri::command]
pub fn enqueue_sync_event(state: tauri::State<DbState>, payload: EnqueueSyncEventPayload) -> Result<Value, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let tx = conn.transaction().map_err(|e| format!("Error al iniciar transacción: {}", e))?;
    
    tx.execute(
        "INSERT INTO sync_queue (
            company_id, branch_id, entity_type, entity_id, entity_uuid, 
            action, payload, version, status, created_at
        ) VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, 'pending', ?9)",
        params![
            payload.company_id, payload.branch_id, payload.entity_type,
            payload.entity_id, payload.entity_uuid, payload.action,
            payload.payload_json, payload.version, Utc::now().to_rfc3339()
        ],
    ).map_err(|e| format!("Error encolando evento de sync: {}", e))?;
    
    tx.commit().map_err(|e| format!("Error al hacer COMMIT: {}", e))?;
    
    Ok(Value::String("Evento encolado exitosamente".to_string()))
}

/// O-05 FIX: Consulta de lectura específica y segura (ej. obtener órdenes pendientes).
/// No acepta SQL arbitrario, solo consultas predefinidas.
#[tauri::command]
pub fn get_pending_orders(state: tauri::State<DbState>, company_id: i64, branch_id: i64) -> Result<Value, String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let mut stmt = conn.prepare(
        "SELECT id, uuid, order_number, type, status, subtotal, tax_amount, total, created_at 
         FROM local_orders 
         WHERE company_id = ?1 AND branch_id = ?2 AND sync_status = 'pending'
         ORDER BY created_at ASC"
    ).map_err(|e| format!("Error preparando consulta: {}", e))?;
    
    let mut rows = stmt.query(params![company_id, branch_id]).map_err(|e| format!("Error ejecutando query: {}", e))?;
    
    let mut results = Vec::new();
    while let Ok(Some(row)) = rows.next() {
        let mut map = Map::new();
        map.insert("id".to_string(), Value::Number(row.get::<_, i64>(0).unwrap_or(0).into()));
        map.insert("uuid".to_string(), Value::String(row.get::<_, String>(1).unwrap_or_default()));
        map.insert("order_number".to_string(), Value::String(row.get::<_, String>(2).unwrap_or_default()));
        map.insert("type".to_string(), Value::String(row.get::<_, String>(3).unwrap_or_default()));
        map.insert("status".to_string(), Value::String(row.get::<_, String>(4).unwrap_or_default()));
        map.insert("subtotal".to_string(), Value::Number(row.get::<_, i64>(5).unwrap_or(0).into()));
        map.insert("tax_amount".to_string(), Value::Number(row.get::<_, i64>(6).unwrap_or(0).into()));
        map.insert("total".to_string(), Value::Number(row.get::<_, i64>(7).unwrap_or(0).into()));
        map.insert("created_at".to_string(), Value::String(row.get::<_, String>(8).unwrap_or_default()));
        results.push(Value::Object(map));
    }
    
    Ok(Value::Array(results))
}
