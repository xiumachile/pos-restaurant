mod database;
mod orders;

use database::DbState;
use rusqlite::Connection;
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
