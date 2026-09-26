export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

let testDb: any = null;

/**
 * Obtiene la instancia de BD en memoria para pruebas.
 */
function getTestDb() {
  if (!testDb) {
    const Database = require('better-sqlite3');
    testDb = new Database(':memory:');
    testDb.pragma('journal_mode = WAL');
    testDb.pragma('foreign_keys = ON');
  }
  return testDb;
}

/**
 * Detecta si estamos en un entorno de pruebas (Vitest/CI) o fuera de Tauri.
 */
function shouldUseBetterSqlite3(): boolean {
  // 1. Variable de entorno explícita de Vitest
  if (typeof process !== 'undefined' && process.env.VITEST === 'true') {
    return true;
  }
  // 2. Si no estamos en un navegador, o estamos en un navegador pero sin Tauri
  if (typeof window === 'undefined') {
    return true; // Entorno Node.js puro
  }
  if (typeof (window as any).__TAURI__ === 'undefined') {
    return true; // Entorno de navegador simulado (jsdom/happy-dom) sin Tauri
  }
  
  return false; // Estamos en Tauri real
}

/**
 * Ejecuta una transacción atómica.
 */
export async function executeTransaction(
  statements: DbStatement[],
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  if (shouldUseBetterSqlite3()) {
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
            continue;
          }
          throw error;
        }
      }
    });
    transaction(statements);
    return;
  }

  // Entorno Tauri real
  const mod = await import("@tauri-apps/api/core");
  const invoke = mod.invoke;

  for (const stmt of statements) {
    const isSelect = /^\s*SELECT\s/i.test(stmt.sql);
    if (isSelect) {
      await executeQuery(stmt.sql, stmt.params);
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
          continue;
        }
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
  if (shouldUseBetterSqlite3()) {
    const db = getTestDb();
    return db.prepare(sql).all(...params) as T[];
  }

  const mod = await import("@tauri-apps/api/core");
  const invoke = mod.invoke;
  const result = await invoke("execute_query", { sql, params });
  return (result as T[]) || [];
}

export function resetTestDb() {
  if (testDb) {
    testDb.close();
    testDb = null;
  }
}
