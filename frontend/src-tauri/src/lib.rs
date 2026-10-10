mod database;
mod commands;

use database::{DbState, execute_query, execute_transaction};
use commands::{
    create_local_order, create_order_item, 
            create_order_with_sync,
    enqueue_sync_operation, update_order_status
};
use rusqlite::Connection;
use std::sync::Mutex;
use tauri::Manager;
use tokio::io::AsyncWriteExt;
use tokio::net::TcpStream;
use tokio::time::{timeout, Duration};

#[tauri::command]
async fn print_raw(ip: String, port: u16, data: String) -> Result<usize, String> {
    let bytes = base64_decode(&data).map_err(|e| format!("Base64 inválido: {}", e))?;
    let addr = format!("{}:{}", ip, port);

    let connect_future = TcpStream::connect(&addr);
    let mut stream = timeout(Duration::from_secs(5), connect_future)
        .await
        .map_err(|_| format!("Timeout conectando a {}", addr))?
        .map_err(|e| format!("Error conectando a {}: {}", addr, e))?;

    stream.write_all(&bytes).await.map_err(|e| format!("Error escribiendo bytes: {}", e))?;
    stream.flush().await.map_err(|e| format!("Error haciendo flush: {}", e))?;

    println!("[Tauri:print_raw] ✅ {} bytes enviados a {}", bytes.len(), addr);
    Ok(bytes.len())
}

#[tauri::command]
fn list_network_printers() -> Vec<String> {
    vec![]
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
            print_raw, 
            list_network_printers,
            // ═══════════════════════════════════════════════════════════
            // COMANDOS DE DOMINIO (Hallazgo 08 - Seguridad)
            // ═══════════════════════════════════════════════════════════
            create_local_order,
            create_order_with_sync,
            create_order_item,
            enqueue_sync_operation,
            update_order_status,
            // execute_query se mantiene SOLO para SELECTs complejos
            execute_query
            // execute_transaction ELIMINADO (era inseguro)
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
