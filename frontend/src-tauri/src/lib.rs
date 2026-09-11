use tokio::io::AsyncWriteExt;
use tokio::net::TcpStream;
use tokio::time::{timeout, Duration};

/// Envía bytes ESC/POS crudos a una impresora térmica por red (TCP).
///
/// Uso desde el frontend:
///   await invoke('print_raw', { ip: '192.168.1.100', port: 9100, data: base64Bytes })
///
/// Parámetros:
/// - ip: Dirección IP de la impresora
/// - port: Puerto TCP (usualmente 9100 para impresoras térmicas)
/// - data: Bytes ESC/POS en base64
///
/// Retorna:
/// - Ok: Número de bytes enviados
/// - Err: Mensaje de error
#[tauri::command]
async fn print_raw(ip: String, port: u16, data: String) -> Result<usize, String> {
    // Decodificar base64
    let bytes = base64_decode(&data).map_err(|e| format!("Base64 inválido: {}", e))?;

    // Dirección destino
    let addr = format!("{}:{}", ip, port);

    // Conectar con timeout de 5s
    let connect_future = TcpStream::connect(&addr);
    let mut stream = timeout(Duration::from_secs(5), connect_future)
        .await
        .map_err(|_| format!("Timeout conectando a {}", addr))?
        .map_err(|e| format!("Error conectando a {}: {}", addr, e))?;

    // Enviar todos los bytes
    stream
        .write_all(&bytes)
        .await
        .map_err(|e| format!("Error escribiendo bytes: {}", e))?;

    // Flush para asegurar que todos los bytes se enviaron
    stream
        .flush()
        .await
        .map_err(|e| format!("Error haciendo flush: {}", e))?;

    println!("[Tauri:print_raw] ✅ {} bytes enviados a {}", bytes.len(), addr);

    Ok(bytes.len())
}

/// Lista las impresoras de red configuradas (placeholder, para UI futura).
#[tauri::command]
fn list_network_printers() -> Vec<String> {
    // Placeholder: en el futuro podría escanear red o leer config
    vec![]
}

/// Helper: decodifica base64 estándar
fn base64_decode(input: &str) -> Result<Vec<u8>, String> {
    use base64::Engine;
    base64::engine::general_purpose::STANDARD
        .decode(input)
        .map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_sql::Builder::new().build())
        .plugin(tauri_plugin_store::Builder::new().build())
        .invoke_handler(tauri::generate_handler![print_raw, list_network_printers])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
