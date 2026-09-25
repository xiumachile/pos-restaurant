use rusqlite::{params_from_iter, Connection, types::ToSql};
use serde::Deserialize;
use serde_json::{Value, Map};
use std::sync::Mutex;

/// Estado global de la base de datos, protegido por un Mutex.
pub struct DbState(pub Mutex<Connection>);

/// Representa una única sentencia SQL con sus parámetros.
#[derive(Debug, Deserialize)]
pub struct DbStatement {
    pub sql: String,
    pub params: Vec<Value>,
}

/// Wrapper para convertir serde_json::Value a tipos que rusqlite entienda.
struct JsonValueWrapper<'a>(&'a Value);

impl<'a> ToSql for JsonValueWrapper<'a> {
    fn to_sql(&self) -> rusqlite::Result<rusqlite::types::ToSqlOutput<'_>> {
        match self.0 {
            Value::Null => Ok(rusqlite::types::ToSqlOutput::Owned(rusqlite::types::Value::Null)),
            Value::Bool(b) => Ok(rusqlite::types::ToSqlOutput::Owned(rusqlite::types::Value::Integer(if *b { 1 } else { 0 }))),
            Value::Number(n) => {
                if let Some(i) = n.as_i64() {
                    Ok(rusqlite::types::ToSqlOutput::Owned(rusqlite::types::Value::Integer(i)))
                } else if let Some(f) = n.as_f64() {
                    Ok(rusqlite::types::ToSqlOutput::Owned(rusqlite::types::Value::Real(f)))
                } else {
                    Err(rusqlite::Error::ToSqlConversionFailure("Número no soportado".into()))
                }
            }
            Value::String(s) => Ok(rusqlite::types::ToSqlOutput::Owned(rusqlite::types::Value::Text(s.clone()))),
            _ => Err(rusqlite::Error::ToSqlConversionFailure("Tipo de dato no soportado para SQLite".into())),
        }
    }
}

/// Ejecuta una transacción atómica con múltiples statements en UNA SOLA llamada IPC.
#[tauri::command]
pub fn execute_transaction(state: tauri::State<DbState>, statements: Vec<DbStatement>) -> Result<Value, String> {
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    // Iniciar transacción inmediata (obtiene el bloqueo de escritura al instante)
    let tx = conn.transaction().map_err(|e| format!("Error al iniciar transacción: {}", e))?;
    
    // Ejecutar cada statement
    for stmt_data in statements {
        let mut stmt = tx.prepare(&stmt_data.sql).map_err(|e| format!("Error preparando SQL '{}': {}", stmt_data.sql, e))?;
        
        let params: Vec<JsonValueWrapper> = stmt_data.params.iter().map(JsonValueWrapper).collect();
        
        stmt.execute(params_from_iter(params)).map_err(|e| format!("Error ejecutando SQL '{}': {}", stmt_data.sql, e))?;
    }
    
    // Confirmar la transacción
    tx.commit().map_err(|e| format!("Error al hacer COMMIT: {}", e))?;
    
    Ok(Value::String("Transacción exitosa".to_string()))
}

/// Ejecuta una consulta de lectura (SELECT) de forma segura.
#[tauri::command]
pub fn execute_query(state: tauri::State<DbState>, sql: String, params: Vec<Value>) -> Result<Value, String> {
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    
    let mut stmt = conn.prepare(&sql).map_err(|e| format!("Error preparando SQL: {}", e))?;
    
    // 1. OBTENER NOMBRES DE COLUMNAS ANTES DE LLAMAR A query()
    let column_names: Vec<String> = stmt.column_names().into_iter().map(|s| s.to_string()).collect();
    
    let param_wrappers: Vec<JsonValueWrapper> = params.iter().map(JsonValueWrapper).collect();
    
    // 2. AHORA SÍ EJECUTAR LA CONSULTA
    let mut rows = stmt.query(params_from_iter(param_wrappers)).map_err(|e| format!("Error ejecutando query: {}", e))?;
    
    let mut results = Vec::new();
    
    // 3. ITERAR SOBRE LOS RESULTADOS
    while let Ok(Some(row)) = rows.next() {
        let mut map = Map::new();
        for (i, column_name) in column_names.iter().enumerate() {
            // Intentar obtener como texto, luego número, luego null (simplificación segura para POS)
            let value: Value = row.get::<_, String>(i).map(Value::String).unwrap_or_else(|_| {
                row.get::<_, i64>(i).map(|v| Value::Number(v.into())).unwrap_or_else(|_| {
                    row.get::<_, f64>(i).map(|v| Value::Number(serde_json::Number::from_f64(v).unwrap_or(serde_json::Number::from(0)))).unwrap_or(Value::Null)
                })
            });
            map.insert(column_name.clone(), value);
        }
        results.push(Value::Object(map));
    }
    
    Ok(Value::Array(results))
}
