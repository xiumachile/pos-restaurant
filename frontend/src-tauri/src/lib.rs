use serde::{Deserialize, Serialize};
use std::net::{TcpStream, SocketAddr};
use std::time::Duration;

// ============================================
// HALLAZGO 12: Configuración de SQLite para resiliencia offline
// ============================================
// Estas configuraciones deben aplicarse al inicializar la base de datos SQLite local
// para garantizar consistencia y rendimiento en modo offline.

/// Configuración de SQLite para modo offline robusto.
/// Debe ejecutarse al inicializar la conexión a la base de datos local.
pub fn configure_sqlite_for_offline(conn: &rusqlite::Connection) -> Result<(), rusqlite::Error> {
    // WAL (Write-Ahead Logging) para mejor concurrencia y rendimiento
    conn.execute_batch("PRAGMA journal_mode = WAL;")?;
    
    // Timeout de busy para evitar bloqueos en operaciones concurrentes
    conn.execute_batch("PRAGMA busy_timeout = 5000;")?;
    
    // Foreign keys habilitadas para integridad referencial
    conn.execute_batch("PRAGMA foreign_keys = ON;")?;
    
    // Synchronous en NORMAL para balance entre seguridad y rendimiento
    conn.execute_batch("PRAGMA synchronous = NORMAL;")?;
    
    Ok(())
}

// ============================================
// HALLAZGO C-03: Impresión térmica con confirmación de entrega
// ============================================

#[derive(Serialize, Deserialize, Debug)]
pub struct PrintJob {
    pub uuid: String,
    pub order_id: Option<i64>,
    pub printer_name: String,
    pub content: String,
    pub attempts: i32,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct PrinterInfo {
    pub ip: String,
    pub port: u16,
    pub name_es: String,
    pub name_zh: String,
    pub is_reachable: bool,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct PrintResult {
    pub success: bool,
    pub message_es: String,
    pub message_zh: String,
}

/// HALLAZGO C-03: Verificación real de conectividad a impresoras de red (puerto 9100)
/// Devuelve nombres bilingües para la interfaz de cocina.
#[tauri::command]
pub fn list_network_printers(ip_range: String, port: u16) -> Result<Vec<PrinterInfo>, String> {
    let mut printers = Vec::new();
    let ips: Vec<&str> = ip_range.split(',').collect();
    
    for ip in ips {
        let addr = format!("{}:{}", ip.trim(), port);
        let is_reachable = TcpStream::connect_timeout(
            &addr.parse::<SocketAddr>().map_err(|e| e.to_string())?,
            Duration::from_secs(2),
        ).is_ok();
        
        printers.push(PrinterInfo {
            ip: ip.trim().to_string(),
            port,
            name_es: format!("Impresora de Red ({})", ip.trim()),
            name_zh: format!("网络打印机 ({})", ip.trim()),
            is_reachable,
        });
    }
    
    Ok(printers)
}

/// HALLAZGO C-03: Impresión con verificación real de escritura y flush.
/// Devuelve resultados bilingües para que el frontend muestre el estado correcto.
#[tauri::command]
pub fn print_raw(ip: String, port: u16, data: String) -> Result<PrintResult, String> {
    let addr = format!("{}:{}", ip, port);
    
    // 1. Intentar conectar con timeout
    let mut stream = match TcpStream::connect_timeout(
        &addr.parse::<SocketAddr>().map_err(|e| e.to_string())?,
        Duration::from_secs(3),
    ) {
        Ok(s) => s,
        Err(_) => {
            return Ok(PrintResult {
                success: false,
                message_es: "No se pudo conectar a la impresora. Verifique la red o el estado del dispositivo.".to_string(),
                message_zh: "无法连接到打印机。请检查网络或设备状态。".to_string(),
            });
        }
    };
    
    // 2. Establecer timeouts de lectura/escritura para detectar bloqueos
    if let Err(_) = stream.set_write_timeout(Some(Duration::from_secs(5))) {
        return Ok(PrintResult {
            success: false,
            message_es: "Error de configuración de red.".to_string(),
            message_zh: "网络配置错误。".to_string(),
        });
    }
    
    // 3. Escribir datos
    if let Err(_) = stream.write_all(data.as_bytes()) {
        return Ok(PrintResult {
            success: false,
            message_es: "Error al enviar datos a la impresora.".to_string(),
            message_zh: "向打印机发送数据时出错。".to_string(),
        });
    }
    
    // 4. Flush para garantizar que los datos se envíen al buffer de la impresora
    if let Err(_) = stream.flush() {
        return Ok(PrintResult {
            success: false,
            message_es: "Error al finalizar el envío de datos.".to_string(),
            message_zh: "完成数据发送时出错。".to_string(),
        });
    }
    
    // Éxito: Datos entregados al buffer de red de la impresora.
    Ok(PrintResult {
        success: true,
        message_es: "Datos enviados correctamente a la impresora.".to_string(),
        message_zh: "数据已成功发送到打印机。".to_string(),
    })
}
