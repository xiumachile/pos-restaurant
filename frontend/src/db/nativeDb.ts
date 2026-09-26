export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

// Variable para mantener la conexión en memoria durante las pruebas
let testDb: any = null;

/**
 * Obtiene una instancia de base de datos en memoria para pruebas.
 */
function getTestDb() {
  if (!testDb) {
    try {
      // Dynamic import para evitar errores en Tauri real
      const Database = require('better-sqlite3');
      // Base de datos en memoria para pruebas rápidas y aisladas
      testDb = new Database(':memory:');
      
      // Habilitar foreign keys en la BD de prueba
      testDb.pragma('journal_mode = WAL');
      testDb.pragma('foreign_keys = ON');
    } catch (error) {
      throw new Error("better-sqlite3 no está instalado. Ejecuta: npm install --save-dev better-sqlite3");
    }
  }
  return testDb;
}

/**
 * Detecta si estamos en un entorno Tauri real.
 */
function isTauriEnvironment(): boolean {
  return typeof window !== 'undefined' && 
         typeof (window as any).__TAURI__ !== 'undefined';
}

/**
 * Ejecuta una transacción atómica.
 * En Tauri: usa el comando nativo de Rust.
 * En Tests/CI: usa better-sqlite3 en memoria.
 */
export async function executeTransaction(
  statements: DbStatement[],
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  if (!isTauriEnvironment()) {
    // Entorno de prueba: ejecutar con better-sqlite3
    const db = getTestDb();
    const transaction = db.transaction((stmts: DbStatement[]) => {
      for (const stmt of stmts) {
        try {
          if (/^\s*SELECT\s/i.test(stmt.sql)) {
            db.prepare(stmt.sql).all(...(stmt.params || []));
          } else {
            db.prepare(stmt.sql).run(...(stmt.params || []));
          }
        } catch (error: any) {
          if (options?.ignoreDuplicateErrors && (
            error.message.includes("duplicate column") ||
            error.message.includes("already exists") ||
            error.message.includes("UNIQUE constraint")
          )) {
            continue; // Ignorar y continuar
          }
          throw error;
        }
      }
    });
    transaction(statements);
    return;
  }

  // Entorno Tauri real: usar invoke
  const mod = await import("@tauri-apps/api/core");
  const invoke = mod.invoke;

  for (const stmt of statements) {
    const isSelect = /^\s*SELECT\s/i.test(stmt.sql);

    if (isSelect) {
      try {
        await executeQuery(stmt.sql, stmt.params);
      } catch (error: any) {
        console.error("[NativeDB] ❌ Error en SELECT:", error);
        throw error;
      }
    } else {
      try {
        await invoke("execute_transaction", { statements: [stmt] });
      } catch (error: any) {
        const msg = error.message || error.toString();

        if (options?.ignoreDuplicateErrors && (
          msg.includes("duplicate column") ||
          msg.includes("already exists") ||
          msg.includes("UNIQUE constraint")
        )) {
          console.warn("[NativeDB] ⚠️ Duplicado ignorado (migración idempotente)");
          continue;
        }

        console.error("[NativeDB] ❌ Error en transacción:", error);
        throw new Error(msg);
      }
    }
  }
}

/**
 * Ejecuta una consulta de lectura (SELECT).
 */
export async function executeQuery<T = any>(
  sql: string,
  params: (string | number | boolean | null)[] = []
): Promise<T[]> {
  if (!isTauriEnvironment()) {
    // Entorno de prueba
    const db = getTestDb();
    return db.prepare(sql).all(...params) as T[];
  }

  // Entorno Tauri real
  const mod = await import("@tauri-apps/api/core");
  const invoke = mod.invoke;
  
  try {
    const result = await invoke("execute_query", { sql, params });
    return (result as T[]) || [];
  } catch (error: any) {
    console.error("[NativeDB] ❌ Error en query:", error);
    throw new Error(error.message || "Error en query nativa");
  }
}

/**
 * Limpia la base de datos de prueba (útil para afterEach en tests)
 */
export function resetTestDb() {
  if (testDb) {
    testDb.close();
    testDb = null;
  }
}
