export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

let testDb: any = null;

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
 * Detección infalible de entorno de pruebas (Vitest, Jest, CI, Node.js).
 */
function isTestEnvironment(): boolean {
  // 1. Entorno Node.js puro (GitHub Actions CI, scripts)
  if (typeof window === 'undefined') {
    return true;
  }
  // 2. Variables de entorno estándar de testing
  if (typeof process !== 'undefined') {
    if (process.env.VITEST === 'true' || process.env.NODE_ENV === 'test' || process.env.JEST_WORKER_ID) {
      return true;
    }
  }
  // 3. Globales inyectados por runners de pruebas (incluso en jsdom/happy-dom)
  if (typeof (globalThis as any).vi !== 'undefined' || typeof (globalThis as any).jest !== 'undefined') {
    return true;
  }
  
  return false; // Estamos en Tauri real (navegador con __TAURI__)
}

export async function executeTransaction(
  statements: DbStatement[],
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  if (isTestEnvironment()) {
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
    if (/^\s*SELECT\s/i.test(stmt.sql)) {
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

export async function executeQuery<T = any>(
  sql: string,
  params: (string | number | boolean | null)[] = []
): Promise<T[]> {
  if (isTestEnvironment()) {
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
