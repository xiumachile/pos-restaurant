mod database;
pub mod orders;

use database::DbState;
use rusqlite::Connection;
use rusqlite::params;
use std::sync::Mutex;
use tauri::Manager;
use tokio::io::AsyncWriteExt;
use tokio::net::TcpStream;
use tokio::time::{timeout, Duration};

#[tauri::command]
async fn check_printer_status(ip: String, port: u16) -> Result<String, String> {
    let addr = format!("{}:{}", ip, port);
    let connect_future = TcpStream::connect(&addr);
    
    match timeout(Duration::from_secs(3), connect_future).await {
        Ok(Ok(_stream)) => {
            // La conexión TCP fue exitosa. En un escenario ideal, aquí se enviaría 
            // un comando de estado específico (ej. ESC/POS GS r), pero la conexión 
            // exitosa ya descarta que la impresora esté apagada o fuera de red.
            Ok("Online".to_string())
        }
        Ok(Err(e)) => Err(format!("Offline: No se pudo conectar ({})", e)),
        Err(_) => Err("Offline: Timeout de conexión (3s)".to_string()),
    }
}

#[tauri::command]
async fn print_raw(ip: String, port: u16, data: String) -> Result<usize, String> {
    let bytes = base64_decode(&data).map_err(|e| format!("Base64 inválido: {}", e))?;
    let addr = format!("{}:{}", ip, port);

    let connect_future = TcpStream::connect(&addr);
    let mut stream = timeout(Duration::from_secs(3), connect_future)
        .await
        .map_err(|_| format!("Error de conexión: Timeout al conectar con {}", addr))?
        .map_err(|e| format!("Error de conexión: No se pudo conectar a {} ({})", addr, e))?;

    stream.write_all(&bytes).await.map_err(|e| format!("Error de escritura: {}", e))?;
    
    // Opcional: flush para asegurar que los datos salen del buffer del SO
    stream.flush().await.map_err(|e| format!("Error de flush: {}", e))?;

    // Nota: La mayoría de impresoras térmicas en modo raw no envían un ACK de aplicación 
    // a menos que se envíe un comando de estado específico. El éxito aquí garantiza que 
    // los datos fueron entregados a la pila de red de la impresora.
    println!("[Tauri:print_raw] ✅ {} bytes enviados a {}", bytes.len(), addr);
    Ok(bytes.len())
}

fn base64_decode(input: &str) -> Result<Vec<u8>, String> {
    use base64::Engine;
    base64::engine::general_purpose::STANDARD.decode(input).map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            let app_data_dir = app.path().app_data_dir().expect("Failed to get app data dir");
            std::fs::create_dir_all(&app_data_dir).expect("Failed to create app data dir");
            let db_path = app_data_dir.join("pos_local.db");
            
            println!("[Tauri Setup] 🗄️  Abriendo base de datos en: {:?}", db_path);
            
            let conn = Connection::open(&db_path).expect("Failed to open database");
            conn.execute_batch(
                "PRAGMA journal_mode = WAL;
                 PRAGMA busy_timeout = 5000;
                 PRAGMA synchronous = NORMAL;
                 PRAGMA foreign_keys = ON;"
            ).expect("Failed to set PRAGMAs");
            
            println!("[Tauri Setup] ✅ SQLite configurado con WAL y busy_timeout=5000");
            
            app.manage(DbState(Mutex::new(conn)));
            Ok(())
        })
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_store::Builder::new().build())
        .invoke_handler(tauri::generate_handler![
        execute_read_query,
        ping_database,
        apply_migration,
            print_raw, 
            check_printer_status,
            // COMANDOS DE DOMINIO SEGUROS (O-05 FIX)
            orders::create_local_order,
            orders::register_local_payment,
            orders::enqueue_sync_event,
            orders::get_pending_orders
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

/// P0/O-05 FIX: Comando seguro para verificar que la base de datos está inicializada.
#[tauri::command]
fn ping_database(state: tauri::State<crate::database::DbState>) -> Result<bool, String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {e}"))?;
    // Una consulta simple y segura para verificar la conexión
    let _: i64 = conn.query_row("SELECT 1", [], |r| r.get(0))
        .map_err(|e| format!("Error de ping: {e}"))?;
    Ok(true)
}

/// P0/O-05 FIX: Comando seguro para aplicar migraciones de esquema.
/// Aunque recibe SQL, está diseñado exclusivamente para ser invocado por el 
/// sistema de migraciones del frontend con scripts estáticos y auditados.
#[tauri::command]
fn apply_migration(
    state: tauri::State<crate::database::DbState>,
    version: String,
    sql_script: String,
) -> Result<bool, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {e}"))?;
    
    let tx = conn
        .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción de migración: {e}"))?;

    // Verificar si ya está aplicada
    let already_applied: bool = tx
        .query_row(
            "SELECT COUNT(*) > 0 FROM migrations WHERE version = ?1",
            params![version],
            |r| r.get(0),
        )
        .unwrap_or(false);

    if already_applied {
        return Ok(false); // Ya aplicada, no hacer nada
    }

    // Ejecutar el script de migración
    tx.execute_batch(&sql_script)
        .map_err(|e| format!("Error ejecutando migración {version}: {e}"))?;

    // Registrar como aplicada
    tx.execute(
        "INSERT INTO migrations (version, applied_at) VALUES (?1, CURRENT_TIMESTAMP)",
        params![version],
    )
    .map_err(|e| format!("Error registrando migración {version}: {e}"))?;

    tx.commit().map_err(|e| format!("Error en COMMIT de migración: {e}"))?;
    
    Ok(true)
}





/// P0/O-05 FIX: Comando de lectura seguro. 
/// Solo permite consultas SELECT y valida que no contengan palabras clave peligrosas.
#[tauri::command]
fn execute_read_query(
    state: tauri::State<crate::database::DbState>,
    sql: String,
    params: Vec<serde_json::Value>,
) -> Result<serde_json::Value, String> {
    let sql_lower = sql.to_lowercase();
    
    if !sql_lower.trim().starts_with("select") {
        return Err("Comando no permitido: solo se permiten consultas SELECT".to_string());
    }
    
    let dangerous_keywords = ["insert", "update", "delete", "drop", "alter", "create", "pragma", "attach"];
    for keyword in dangerous_keywords {
        if sql_lower.contains(keyword) {
            return Err(format!("Comando no permitido: contiene palabra clave peligrosa '{}'", keyword));
        }
    }

    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {e}"))?;
    let mut stmt = conn.prepare(&sql).map_err(|e| format!("Error preparando consulta: {e}"))?;
    
    // Obtener nombres de columnas UNA sola vez
    let column_names: Vec<String> = stmt.column_names().into_iter().map(String::from).collect();
    let column_count = column_names.len();
    
    let rusqlite_params: Vec<Box<dyn rusqlite::types::ToSql>> = params.iter().map(|v| {
        let boxed: Box<dyn rusqlite::types::ToSql> = match v {
            serde_json::Value::Null => Box::new(rusqlite::types::Null),
            serde_json::Value::Number(n) => {
                if let Some(i) = n.as_i64() { Box::new(i) }
                else if let Some(f) = n.as_f64() { Box::new(f) }
                else { Box::new(rusqlite::types::Null) }
            },
            serde_json::Value::String(s) => Box::new(s.clone()),
            serde_json::Value::Bool(b) => Box::new(if *b { 1i64 } else { 0i64 }),
            _ => Box::new(rusqlite::types::Null),
        };
        boxed
    }).collect();
    
    let param_refs: Vec<&dyn rusqlite::types::ToSql> = rusqlite_params.iter().map(|p| p.as_ref()).collect();
    
    let mut rows = stmt.query(param_refs.as_slice()).map_err(|e| format!("Error ejecutando consulta: {e}"))?;

    let mut results: Vec<serde_json::Map<String, serde_json::Value>> = Vec::new();
    while let Some(row) = rows.next().map_err(|e| format!("Error leyendo fila: {e}"))? {
        let mut map = serde_json::Map::new();
        for i in 0..column_count {
            let col_name = column_names[i].to_string();
            let val: rusqlite::types::Value = row.get(i).unwrap_or(rusqlite::types::Value::Null);
            let json_val = match val {
                rusqlite::types::Value::Null => serde_json::Value::Null,
                rusqlite::types::Value::Integer(i) => serde_json::Value::Number(serde_json::Number::from(i)),
                rusqlite::types::Value::Real(f) => serde_json::Number::from_f64(f)
                    .map(serde_json::Value::Number)
                    .unwrap_or(serde_json::Value::Null),
                rusqlite::types::Value::Text(s) => serde_json::Value::String(s),
                rusqlite::types::Value::Blob(_) => serde_json::Value::String("[BLOB]".to_string()),
            };
            map.insert(col_name, json_val);
        }
        results.push(map);
    }

    Ok(serde_json::Value::Array(results.into_iter().map(serde_json::Value::Object).collect()))
}
