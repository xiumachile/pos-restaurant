use rusqlite::params_from_iter;
use rusqlite::types::{ToSql, ToSqlOutput, Value as SqlValue, ValueRef};
use serde::Deserialize;
use serde_json::{Map as JsonMap, Value as JsonValue};

use crate::database::DbState;

struct JsonParam<'a>(&'a JsonValue);

impl<'a> ToSql for JsonParam<'a> {
    fn to_sql(&self) -> rusqlite::Result<ToSqlOutput<'_>> {
        Ok(ToSqlOutput::Owned(match self.0 {
            JsonValue::Null => SqlValue::Null,
            JsonValue::Bool(b) => SqlValue::Integer(*b as i64),
            JsonValue::Number(n) => {
                if let Some(i) = n.as_i64() {
                    SqlValue::Integer(i)
                } else if let Some(f) = n.as_f64() {
                    SqlValue::Real(f)
                } else {
                    return Err(rusqlite::Error::ToSqlConversionFailure("Número no soportado".into()));
                }
            }
            JsonValue::String(s) => SqlValue::Text(s.clone()),
            _ => return Err(rusqlite::Error::ToSqlConversionFailure("Tipo no soportado".into())),
        }))
    }
}

#[derive(Debug, Deserialize)]
pub struct DbStatement {
    pub sql: String,
    pub params: Vec<JsonValue>,
}

/// Bloquea operaciones peligrosas. rusqlite ya rechaza múltiples statements en prepare().
fn validate_sql(sql: &str) -> Result<(), String> {
    let lower = sql.to_lowercase();
    for banned in ["attach ", "detach ", "load_extension", "vacuum into"] {
        if lower.contains(banned) {
            return Err(format!("Operación no permitida: {}", banned.trim()));
        }
    }
    Ok(())
}

#[tauri::command]
pub fn execute_query(
    state: tauri::State<DbState>,
    sql: String,
    params: Vec<JsonValue>,
) -> Result<JsonValue, String> {
    validate_sql(&sql)?;
    let conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    let mut stmt = conn.prepare(&sql).map_err(|e| format!("Error preparando SQL: {}", e))?;
    let cols: Vec<String> = stmt.column_names().into_iter().map(|s| s.to_string()).collect();
    let wrapped: Vec<JsonParam> = params.iter().map(JsonParam).collect();
    let mut rows = stmt
        .query(params_from_iter(wrapped))
        .map_err(|e| format!("Error en query: {}", e))?;

    let mut out = Vec::new();
    while let Some(row) = rows.next().map_err(|e| e.to_string())? {
        let mut map = JsonMap::new();
        for (i, name) in cols.iter().enumerate() {
            let v = match row.get_ref(i).map_err(|e| e.to_string())? {
                ValueRef::Null => JsonValue::Null,
                ValueRef::Integer(n) => JsonValue::from(n),
                ValueRef::Real(f) => serde_json::Number::from_f64(f)
                    .map(JsonValue::Number)
                    .unwrap_or(JsonValue::Null),
                ValueRef::Text(t) => JsonValue::String(String::from_utf8_lossy(t).into_owned()),
                ValueRef::Blob(_) => JsonValue::Null,
            };
            map.insert(name.clone(), v);
        }
        out.push(JsonValue::Object(map));
    }
    Ok(JsonValue::Array(out))
}

#[tauri::command]
pub fn execute_transaction(
    state: tauri::State<DbState>,
    statements: Vec<DbStatement>,
) -> Result<JsonValue, String> {
    for s in &statements {
        validate_sql(&s.sql)?;
    }
    let mut conn = state.0.lock().map_err(|e| format!("Mutex envenenado: {}", e))?;
    let tx = conn.transaction().map_err(|e| format!("Error iniciando transacción: {}", e))?;
    for s in statements {
        let wrapped: Vec<JsonParam> = s.params.iter().map(JsonParam).collect();
        // Se usa prepare+execute para soportar tanto DDL como DML
        let mut stmt = tx
            .prepare(&s.sql)
            .map_err(|e| format!("Error preparando '{}': {}", s.sql, e))?;
        stmt.execute(params_from_iter(wrapped))
            .map_err(|e| format!("Error ejecutando '{}': {}", s.sql, e))?;
    }
    tx.commit().map_err(|e| format!("Error en COMMIT: {}", e))?;
    Ok(JsonValue::String("ok".into()))
}
