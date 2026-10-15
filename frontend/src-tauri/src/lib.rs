mod database;
pub mod orders;

use database::DbState;
use rusqlite::params;
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

    stream
        .write_all(&bytes)
        .await
        .map_err(|e| format!("Error de escritura: {}", e))?;

    // Opcional: flush para asegurar que los datos salen del buffer del SO
    stream
        .flush()
        .await
        .map_err(|e| format!("Error de flush: {}", e))?;

    // Nota: La mayoría de impresoras térmicas en modo raw no envían un ACK de aplicación
    // a menos que se envíe un comando de estado específico. El éxito aquí garantiza que
    // los datos fueron entregados a la pila de red de la impresora.
    println!(
        "[Tauri:print_raw] ✅ {} bytes enviados a {}",
        bytes.len(),
        addr
    );
    Ok(bytes.len())
}

fn base64_decode(input: &str) -> Result<Vec<u8>, String> {
    use base64::Engine;
    base64::engine::general_purpose::STANDARD
        .decode(input)
        .map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            let app_data_dir = app
                .path()
                .app_data_dir()
                .expect("Failed to get app data dir");
            std::fs::create_dir_all(&app_data_dir).expect("Failed to create app data dir");
            let db_path = app_data_dir.join("pos_local.db");

            println!("[Tauri Setup] 🗄️  Abriendo base de datos en: {:?}", db_path);

            let conn = Connection::open(&db_path).expect("Failed to open database");
            conn.execute_batch(
                "PRAGMA journal_mode = WAL;
                 PRAGMA busy_timeout = 5000;
                 PRAGMA synchronous = NORMAL;
                 PRAGMA foreign_keys = ON;",
            )
            .expect("Failed to set PRAGMAs");

            println!("[Tauri Setup] ✅ SQLite configurado con WAL y busy_timeout=5000");

            app.manage(DbState(Mutex::new(conn)));
            Ok(())
        })
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_store::Builder::new().build())
        .invoke_handler(tauri::generate_handler![
            execute_write_statements,
            claim_pending_sync_items,
            execute_read_query,
            ping_database,
            apply_migration,
            print_raw,
            check_printer_status,
            // COMANDOS DE DOMINIO SEGUROS (O-05 FIX)
            orders::create_local_order,
            orders::register_local_payment,
            orders::enqueue_sync_event,
            orders::get_pending_orders,
            orders::transition_order_status // AUD-01 FIX: Comando de transición atómica
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

/// P0/O-05 FIX: Comando seguro para verificar que la base de datos está inicializada.
#[tauri::command]
fn ping_database(state: tauri::State<crate::database::DbState>) -> Result<bool, String> {
    let conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    // Una consulta simple y segura para verificar la conexión
    let _: i64 = conn
        .query_row("SELECT 1", [], |r| r.get(0))
        .map_err(|e| format!("Error de ping: {e}"))?;
    Ok(true)
}

/// P0/O-05 FIX: Comando seguro para aplicar migraciones de esquema.
/// Aunque recibe SQL, está diseñado exclusivamente para ser invocado por el
/// sistema de migraciones del frontend con scripts estáticos y auditados.
/// P1-04 FIX: El frontend solo envía la versión. Rust carga el SQL de forma segura
/// desde los archivos de migración en tiempo de compilación.

/// Devuelve el script SQL confiable para una versión dada, cargado en tiempo de compilación.
/// P1-04 FIX: El frontend solo envía la versión, Rust valida y carga el script de forma segura.
fn get_trusted_migration(version: &str) -> Option<&'static str> {
    match version {
        // Agregamos la migración 000 que crea la tabla de control de migraciones
        // AUD-04 FIX: Esquema canónico unificado con checksum para paridad con tests
        "000_create_migrations_table" => Some(
            "CREATE TABLE IF NOT EXISTS migrations (
                version TEXT PRIMARY KEY,
                checksum TEXT,
                applied_at TEXT DEFAULT CURRENT_TIMESTAMP
            );"
        ),
        "001" => Some(include_str!("../../src/db/migrations/001_initial.sql")),
        "002" => Some(include_str!("../../src/db/migrations/002_table_local_mutations.sql")),
        "003" => Some(include_str!("../../src/db/migrations/003_add_backend_id_to_categories.sql")),
        "004" => Some(include_str!("../../src/db/migrations/004_create_local_bills.sql")),
        "005" => Some(include_str!("../../src/db/migrations/005_create_local_cash_movements.sql")),
        "006" => Some(include_str!("../../src/db/migrations/006_add_company_to_local_cash_sessions.sql")),
        "007" => Some(include_str!("../../src/db/migrations/007_create_offline_events.sql")),
        "008" => Some(include_str!("../../src/db/migrations/008_create_local_print_jobs.sql")),
        "009" => Some(include_str!("../../src/db/migrations/009_create_printer_configs.sql")),
        "011" => Some(include_str!("../../src/db/migrations/011_add_chilean_columns.sql")),
        "012" => Some(include_str!("../../src/db/migrations/012_add_tenant_to_local_tables.sql")),
        "013" => Some(include_str!("../../src/db/migrations/013_add_tenant_to_table_mutations.sql")),
        "014" => Some(include_str!("../../src/db/migrations/014_backfill_and_validate_tenant.sql")),
        "015" => Some(include_str!("../../src/db/migrations/015_add_bill_link_to_payments.sql")),
        "016" => Some(include_str!("../../src/db/migrations/016_fix_orphan_local_tables.sql")),
        "017" => Some(include_str!("../../src/db/migrations/017_enforce_integer_money.sql")),
        "019" => Some(include_str!("../../src/db/migrations/019_fix_table_status_pending.sql")),
        "020" => Some(include_str!("../../src/db/migrations/020_clean_table_mutations.sql")),
        "021" => Some(include_str!("../../src/db/migrations/021_enforce_table_mutation_schema.sql")),
        "022" => Some(include_str!("../../src/db/migrations/022_add_migrations_checksum.sql")), // AUD-04 FIX: Reparar bases antiguas
        _ => None, // Cualquier otra versión será rechazada por seguridad
    }
}
#[tauri::command]
fn apply_migration(
    state: tauri::State<crate::database::DbState>,
    version: String,
) -> Result<bool, String> {
    // 1. Obtener el script confiable incorporado en el binario
    let sql_script = get_trusted_migration(&version)
        .ok_or_else(|| format!("Migración no reconocida o no permitida: {}", version))?;

    // 2. Validación de defensa en profundidad: rechazar patrones peligrosos
    // P1-04 FIX: No se valida el contenido del script porque proviene de
    // get_trusted_migration (include_str!), que es una whitelist compilada en el binario.
    // El frontend solo puede solicitar una versión de esta lista, imposibilitando la inyección.

    let mut conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;

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
    run_migration_script(&tx, &version, &sql_script)?;

    // Registrar como aplicada
    tx.execute(
        "INSERT OR IGNORE INTO migrations (version, applied_at) VALUES (?1, CURRENT_TIMESTAMP)",
        params![version],
    )
    .map_err(|e| format!("Error registrando migración {version}: {e}"))?;

    tx.commit()
        .map_err(|e| format!("Error en COMMIT de migración: {e}"))?;

    Ok(true)
}
#[tauri::command]
fn execute_read_query(
    state: tauri::State<crate::database::DbState>,
    sql: String,
    params: Vec<serde_json::Value>,
) -> Result<serde_json::Value, String> {
    validate_read_sql(&sql)?;

    let conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    let mut stmt = conn
        .prepare(&sql)
        .map_err(|e| format!("Error preparando consulta: {e}"))?;
    if !stmt.readonly() {
        return Err("La consulta no es de solo lectura".to_string());
    }

    // Obtener nombres de columnas UNA sola vez
    let column_names: Vec<String> = stmt.column_names().into_iter().map(String::from).collect();
    let column_count = column_names.len();

    let rusqlite_params: Vec<Box<dyn rusqlite::types::ToSql>> = params
        .iter()
        .map(|v| {
            let boxed: Box<dyn rusqlite::types::ToSql> = match v {
                serde_json::Value::Null => Box::new(rusqlite::types::Null),
                serde_json::Value::Number(n) => {
                    if let Some(i) = n.as_i64() {
                        Box::new(i)
                    } else if let Some(f) = n.as_f64() {
                        Box::new(f)
                    } else {
                        Box::new(rusqlite::types::Null)
                    }
                }
                serde_json::Value::String(s) => Box::new(s.clone()),
                serde_json::Value::Bool(b) => Box::new(if *b { 1i64 } else { 0i64 }),
                _ => Box::new(rusqlite::types::Null),
            };
            boxed
        })
        .collect();

    let param_refs: Vec<&dyn rusqlite::types::ToSql> =
        rusqlite_params.iter().map(|p| p.as_ref()).collect();

    let mut rows = stmt
        .query(param_refs.as_slice())
        .map_err(|e| format!("Error ejecutando consulta: {e}"))?;

    let mut results: Vec<serde_json::Map<String, serde_json::Value>> = Vec::new();
    while let Some(row) = rows
        .next()
        .map_err(|e| format!("Error leyendo fila: {e}"))?
    {
        let mut map = serde_json::Map::new();
        for i in 0..column_count {
            let col_name = column_names[i].to_string();
            let val: rusqlite::types::Value = row.get(i).unwrap_or(rusqlite::types::Value::Null);
            let json_val = match val {
                rusqlite::types::Value::Null => serde_json::Value::Null,
                rusqlite::types::Value::Integer(i) => {
                    serde_json::Value::Number(serde_json::Number::from(i))
                }
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

    Ok(serde_json::Value::Array(
        results.into_iter().map(serde_json::Value::Object).collect(),
    ))
}

#[derive(Debug, serde::Deserialize)]
pub struct WriteStatement {
    sql: String,
    params: Option<Vec<serde_json::Value>>,
}

fn json_to_sql_values(params: &[serde_json::Value]) -> Vec<rusqlite::types::Value> {
    params
        .iter()
        .map(|v| match v {
            serde_json::Value::Null => rusqlite::types::Value::Null,
            serde_json::Value::Number(n) => {
                if let Some(i) = n.as_i64() {
                    rusqlite::types::Value::Integer(i)
                } else if let Some(f) = n.as_f64() {
                    rusqlite::types::Value::Real(f)
                } else {
                    rusqlite::types::Value::Null
                }
            }
            serde_json::Value::String(s) => rusqlite::types::Value::Text(s.clone()),
            serde_json::Value::Bool(b) => rusqlite::types::Value::Integer(*b as i64),
            _ => rusqlite::types::Value::Null,
        })
        .collect()
}

fn validate_write_sql(sql: &str) -> Result<(), String> {
    let lower = sql.trim_start().to_lowercase();

    // 1. Verificar operación permitida
    if !lower.starts_with("insert into")
        && !lower.starts_with("update ")
        && !lower.starts_with("delete from")
    {
        return Err("Operación no permitida. Solo INSERT INTO, UPDATE o DELETE FROM".into());
    }

    // 2. Extraer nombre de la tabla de forma segura (usando strip_prefix para evitar problemas de lifetime)
    let prefix_stripped = if lower.starts_with("insert into") {
        lower.strip_prefix("insert into").unwrap_or("")
    } else if lower.starts_with("update ") {
        lower.strip_prefix("update ").unwrap_or("")
    } else if lower.starts_with("delete from") {
        lower.strip_prefix("delete from").unwrap_or("")
    } else {
        ""
    };

    let table_name = prefix_stripped
        .split_whitespace()
        .next()
        .unwrap_or("")
        .trim_matches(|c: char| c == '`' || c == '"' || c == '[' || c == ']');

    // 3. P1 FIX: Whitelist estricta de tablas permitidas para escritura genérica
    let allowed_tables = [
        "local_orders",
        "local_order_items",
        "local_payments",
        "local_bills",
        "sync_queue",
        "local_tables",
        "table_local_mutations",
        "local_cash_sessions",
        "local_cash_movements",
        "migrations",
        "local_print_jobs",
        "printer_configs",
        "offline_events",
        "idempotency_keys",
    ];

    if !allowed_tables.contains(&table_name) {
        return Err(format!(
            "Acceso denegado: Escritura en tabla no permitida o desconocida '{}'",
            table_name
        ));
    }

    // 4. Bloquear comandos peligrosos a nivel de sistema
    for banned in [
        "attach",
        "detach",
        "load_extension",
        "vacuum",
        "pragma",
        "sqlite_master",
    ] {
        if lower.contains(banned) {
            return Err(format!(
                "Operación no permitida: contiene palabra clave prohibida '{}'",
                banned
            ));
        }
    }
    Ok(())
}

/// Escrituras en UNA transacción atómica (todo o nada).
/// Devuelve { success, count (sentencias), rowsAffected (filas realmente modificadas) }.
#[tauri::command]
fn execute_write_statements(
    state: tauri::State<crate::database::DbState>,
    statements: Vec<WriteStatement>,
) -> Result<serde_json::Value, String> {
    if statements.len() > 500 {
        return Err("Demasiadas sentencias".into());
    }
    for st in &statements {
        validate_write_sql(&st.sql)?;
    }
    let mut conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    let tx = conn
        .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción: {e}"))?;
    let mut total_changes: usize = 0;
    for st in &statements {
        let values = json_to_sql_values(st.params.as_deref().unwrap_or(&[]));
        let n = tx
            .execute(&st.sql, rusqlite::params_from_iter(values.iter()))
            .map_err(|e| format!("Error ejecutando '{}': {}", st.sql, e))?;
        total_changes += n;
    }
    tx.commit().map_err(|e| format!("Error en COMMIT: {e}"))?;
    Ok(serde_json::json!({
        "success": true,
        "count": statements.len(),
        "rowsAffected": total_changes
    }))
}
/// Solo SELECT / WITH. Compara palabras completas: `created_at` o `updated_at` NO se bloquean.
fn validate_read_sql(sql: &str) -> Result<(), String> {
    let lower = sql.trim_start().to_lowercase();
    let first = lower
        .split(|c: char| !c.is_ascii_alphabetic())
        .next()
        .unwrap_or("");
    if first != "select" && first != "with" {
        return Err("Comando no permitido: solo se permiten consultas SELECT".to_string());
    }
    for word in lower.split(|c: char| !(c.is_ascii_alphanumeric() || c == '_')) {
        if matches!(
            word,
            "attach"
                | "detach"
                | "load_extension"
                | "pragma"
                | "insert"
                | "update"
                | "delete"
                | "drop"
                | "alter"
                | "create"
                | "vacuum"
        ) {
            return Err(format!("Comando no permitido: palabra clave '{}'", word));
        }
    }
    Ok(())
}

#[cfg(test)]
mod read_sql_tests {
    use super::validate_read_sql;

    #[test]
    fn allows_legit_selects() {
        assert!(validate_read_sql("SELECT * FROM sync_queue ORDER BY created_at").is_ok());
        assert!(validate_read_sql("select updated_at from local_orders").is_ok());
        assert!(validate_read_sql("SELECT name FROM pragma_table_info('local_orders')").is_ok());
        assert!(validate_read_sql("WITH x AS (SELECT 1) SELECT * FROM x").is_ok());
    }

    #[test]
    fn blocks_writes_and_dangerous() {
        assert!(validate_read_sql("DELETE FROM local_orders").is_err());
        assert!(validate_read_sql("SELECT 1; DROP TABLE local_orders").is_err());
        assert!(validate_read_sql("ATTACH DATABASE 'x' AS y").is_err());
        assert!(validate_read_sql("PRAGMA table_info(local_orders)").is_err());
    }
}

/// Divide un script SQL en sentencias. Descarta comentarios (-- y /* */) y respeta strings.
/// Equivale a parseSqlStatements (TypeScript). Válido porque las migraciones no usan triggers.
fn split_sql_statements(sql: &str) -> Vec<String> {
    let chars: Vec<char> = sql.chars().collect();
    let mut out: Vec<String> = Vec::new();
    let mut cur = String::new();
    let mut in_str: Option<char> = None;
    let mut i = 0;
    while i < chars.len() {
        let c = chars[i];
        let next = chars.get(i + 1).copied();
        if in_str.is_none() && c == '-' && next == Some('-') {
            while i < chars.len() && chars[i] != '\n' {
                i += 1;
            }
            continue;
        }
        if in_str.is_none() && c == '/' && next == Some('*') {
            i += 2;
            while i + 1 < chars.len() && !(chars[i] == '*' && chars[i + 1] == '/') {
                i += 1;
            }
            i += 2;
            continue;
        }
        if let Some(q) = in_str {
            cur.push(c);
            if c == q {
                in_str = None;
            }
            i += 1;
            continue;
        }
        if c == '\'' || c == '"' {
            in_str = Some(c);
            cur.push(c);
            i += 1;
            continue;
        }
        if c == ';' {
            let t = cur.trim();
            if !t.is_empty() {
                out.push(t.to_string());
            }
            cur.clear();
            i += 1;
            continue;
        }
        cur.push(c);
        i += 1;
    }
    let t = cur.trim();
    if !t.is_empty() {
        out.push(t.to_string());
    }
    out
}

/// Ejecuta una migración sentencia por sentencia. Salta PRAGMA y tolera
/// "duplicate column name" / "already exists" (comportamiento del runner anterior).
fn run_migration_script(
    tx: &rusqlite::Transaction,
    version: &str,
    sql: &str,
) -> Result<(), String> {
    // P1-04 FIX: No se valida el contenido porque el script ya es de confianza absoluta
    // (proviene de include_str!). La seguridad radica en la whitelist de versiones.
    for (idx, stmt) in split_sql_statements(sql).iter().enumerate() {
        let n = idx + 1;
        if stmt.trim_start().to_uppercase().starts_with("PRAGMA") {
            continue;
        }
        if let Err(e) = tx.execute_batch(stmt) {
            let msg = e.to_string();
            if msg.contains("duplicate column name") || msg.contains("already exists") {
                eprintln!("[Migrations] {version} sentencia {n} ignorada (no crítico): {msg}");
                continue;
            }
            return Err(format!(
                "Error ejecutando migración {version} (sentencia {n}): {msg}"
            ));
        }
    }
    Ok(())
}

#[cfg(test)]
mod migration_runner_tests {
    use super::*;

    #[test]
    fn splits_ignoring_comments_and_semicolons_in_strings() {
        let v = split_sql_statements(
            "-- c\nCREATE TABLE a (x TEXT DEFAULT 'a;b'); /* x; */ INSERT INTO a VALUES ('1');",
        );
        assert_eq!(v.len(), 2);
        assert!(v[0].contains("'a;b'"));
    }

    #[test]
    fn tolerates_duplicate_column_but_fails_on_real_errors() {
        let mut conn = rusqlite::Connection::open_in_memory().unwrap();
        conn.execute_batch("CREATE TABLE t (id INTEGER, guest_count INTEGER);")
            .unwrap();
        let tx = conn.transaction().unwrap();
        run_migration_script(
            &tx,
            "t1",
            "ALTER TABLE t ADD COLUMN guest_count INTEGER; ALTER TABLE t ADD COLUMN extra TEXT;",
        )
        .unwrap();
        tx.commit().unwrap();
        let n: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM pragma_table_info('t') WHERE name='extra'",
                [],
                |r| r.get(0),
            )
            .unwrap();
        assert_eq!(n, 1);

        let tx = conn.transaction().unwrap();
        assert!(run_migration_script(&tx, "t2", "INSERT INTO no_existe VALUES (1);").is_err());
    }
}

fn claim_row_to_json(row: &rusqlite::Row, cols: &[String]) -> Result<serde_json::Value, String> {
    let mut map = serde_json::Map::new();
    for (i, name) in cols.iter().enumerate() {
        let v = match row.get_ref(i).map_err(|e| e.to_string())? {
            rusqlite::types::ValueRef::Null => serde_json::Value::Null,
            rusqlite::types::ValueRef::Integer(n) => serde_json::Value::from(n),
            rusqlite::types::ValueRef::Real(f) => serde_json::Number::from_f64(f)
                .map(serde_json::Value::Number)
                .unwrap_or(serde_json::Value::Null),
            rusqlite::types::ValueRef::Text(t) => {
                serde_json::Value::String(String::from_utf8_lossy(t).into_owned())
            }
            rusqlite::types::ValueRef::Blob(_) => serde_json::Value::Null,
        };
        map.insert(name.clone(), v);
    }
    Ok(serde_json::Value::Object(map))
}

/// Reclama atómicamente hasta `limit` eventos pendientes de sync_queue (pending -> syncing)
/// y los devuelve. Reemplaza el `UPDATE ... RETURNING` que iba por el canal de lectura.
pub fn claim_pending_tx(
    conn: &mut rusqlite::Connection,
    limit: i64,
) -> Result<serde_json::Value, String> {
    let limit = limit.clamp(1, 100);
    let tx = conn
        .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
        .map_err(|e| format!("Error iniciando transacción: {e}"))?;
    let items = {
        let mut stmt = tx
            .prepare(
                "UPDATE sync_queue
                 SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP
                 WHERE id IN (
                   SELECT id FROM sync_queue
                   WHERE sync_status = 'pending'
                     AND (next_retry_at IS NULL OR datetime(next_retry_at) <= datetime('now'))
                   ORDER BY created_at ASC
                   LIMIT ?1
                 )
                 RETURNING *",
            )
            .map_err(|e| format!("Error preparando claim: {e}"))?;
        let cols: Vec<String> = stmt.column_names().iter().map(|c| c.to_string()).collect();
        let mut rows = stmt
            .query([limit])
            .map_err(|e| format!("Error en claim: {e}"))?;
        let mut out = Vec::new();
        while let Some(row) = rows.next().map_err(|e| e.to_string())? {
            out.push(claim_row_to_json(row, &cols)?);
        }
        out
    };
    tx.commit().map_err(|e| format!("Error en COMMIT: {e}"))?;
    Ok(serde_json::Value::Array(items))
}

#[tauri::command]
fn claim_pending_sync_items(
    state: tauri::State<crate::database::DbState>,
    limit: i64,
) -> Result<serde_json::Value, String> {
    let mut conn = state
        .0
        .lock()
        .map_err(|e| format!("Mutex envenenado: {e}"))?;
    claim_pending_tx(&mut conn, limit)
}

#[cfg(test)]
mod claim_tests {
    use super::*;

    fn db() -> rusqlite::Connection {
        let c = rusqlite::Connection::open_in_memory().unwrap();
        c.execute_batch(
            "CREATE TABLE sync_queue (
               id TEXT PRIMARY KEY, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
               entity_type TEXT NOT NULL, entity_local_uuid TEXT NOT NULL, entity_cloud_id TEXT,
               action TEXT NOT NULL, payload TEXT NOT NULL, sync_status TEXT DEFAULT 'pending',
               attempts INTEGER DEFAULT 0, max_attempts INTEGER DEFAULT 5, last_error TEXT,
               next_retry_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
               updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
             INSERT INTO sync_queue (id,company_id,branch_id,entity_type,entity_local_uuid,action,payload,created_at)
               VALUES ('a','1','1','order','a','create','{}','2026-01-01 00:00:01'),
                      ('b','1','1','order','b','create','{}','2026-01-01 00:00:02'),
                      ('c','1','1','order','c','create','{}','2026-01-01 00:00:03');
             INSERT INTO sync_queue (id,company_id,branch_id,entity_type,entity_local_uuid,action,payload,next_retry_at)
               VALUES ('d','1','1','order','d','create','{}','2999-01-01T00:00:00.000Z');",
        )
        .unwrap();
        c
    }

    #[test]
    fn claims_in_order_respects_limit_and_retry_time() {
        let mut c = db();
        let r = claim_pending_tx(&mut c, 2).unwrap();
        let ids: Vec<&str> = r
            .as_array()
            .unwrap()
            .iter()
            .map(|x| x["id"].as_str().unwrap())
            .collect();
        assert_eq!(ids, vec!["a", "b"]);
        assert!(r[0]["sync_status"] == "syncing");

        // segundo claim: solo queda 'c' (d tiene reintento futuro, a y b ya están syncing)
        let r2 = claim_pending_tx(&mut c, 10).unwrap();
        assert_eq!(r2.as_array().unwrap().len(), 1);
        assert_eq!(r2[0]["id"], "c");
        assert_eq!(
            claim_pending_tx(&mut c, 10)
                .unwrap()
                .as_array()
                .unwrap()
                .len(),
            0
        );
    }
}

#[cfg(test)]
mod migration_integration_tests {
    use super::*;
    use rusqlite::Connection;

    #[test]
    fn test_022_migration_on_legacy_schema() {
        let conn = Connection::open_in_memory().unwrap();
        
        // 1. Crear esquema legacy (sin columna checksum)
        conn.execute(
            "CREATE TABLE migrations (version TEXT PRIMARY KEY, applied_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            [],
        ).unwrap();
        
        conn.execute(
            "INSERT INTO migrations (version) VALUES ('000_create_migrations_table')",
            [],
        ).unwrap();

        // 2. Obtener el SQL de migración directamente de la lista blanca de Rust
        let migration_sql = crate::get_trusted_migration("022").expect("La migración 022 debe estar en la lista blanca de Rust");
        
        // 3. Simular la función interna de apply_migration que maneja idempotencia y errores
        // (Aplicamos el SQL y manejamos 'duplicate column name' como lo hace la función real)
        let result = conn.execute_batch(&migration_sql);
        
        // La primera ejecución debe ser exitosa o ignorar el error de columna duplicada si ya existiera
        if let Err(e) = result {
            assert!(e.to_string().to_lowercase().contains("duplicate column name") || e.to_string().to_lowercase().contains("already exists"), 
                "Error inesperado en la primera migración: {}", e);
        }

        // 4. Idempotencia: ejecutar una segunda vez debe ser seguro
        let result2 = conn.execute_batch(&migration_sql);
        if let Err(e) = result2 {
            assert!(e.to_string().to_lowercase().contains("duplicate column name") || e.to_string().to_lowercase().contains("already exists"), 
                "Error inesperado en la segunda migración (idempotencia fallida): {}", e);
        }

        // 5. Verificar que la columna checksum existe y los datos legacy se conservan
        let mut stmt = conn.prepare("PRAGMA table_info(migrations)").unwrap();
        let columns: Vec<String> = stmt.query_map([], |row| row.get::<_, String>(1)).unwrap().map(|r| r.unwrap()).collect();
        
        assert!(columns.contains(&"checksum".to_string()), "La columna checksum debe existir después de aplicar la migración 022");
        
        let version: String = conn.query_row("SELECT version FROM migrations WHERE version = '000_create_migrations_table'", [], |row| row.get(0)).unwrap();
        assert_eq!(version, "000_create_migrations_table", "Los registros de migración legacy deben conservarse intactos");
        
        // 6. Simular el registro de la versión (acción que realiza el wrapper apply_migration)
        conn.execute("INSERT OR IGNORE INTO migrations (version) VALUES ('022')", []).unwrap();
        let applied: String = conn.query_row("SELECT version FROM migrations WHERE version = '022'", [], |row| row.get(0)).unwrap();
        assert_eq!(applied, "022", "La migración 022 debe quedar registrada en la tabla migrations");
    }
}
