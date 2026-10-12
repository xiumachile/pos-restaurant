import { invoke } from "@tauri-apps/api/core";

export interface DbStatement {
  sql: string;
  params?: (string | number | boolean | null)[];
}

export function isTestEnvironment(): boolean {
  return typeof process !== 'undefined' && (process.env.NODE_ENV === 'test' || process.env.VITEST === 'true');
}

let testDb: any = null;
export function getTestDb(): any { return testDb; }
export function setTestDb(db: any) { testDb = db; }

/**
 * Inicializa la base de datos de pruebas con better-sqlite3.
 */
export async function initTestDb(): Promise<any> {
  if (!isTestEnvironment()) return null;
  
  const { default: BetterSqlite3 } = await import('better-sqlite3');
  const path = await import('path');
  const os = await import('os');
  const { randomUUID } = await import('crypto');

  const filePath = path.join(os.tmpdir(), `pos-test-${randomUUID()}.db`);
  const db = new BetterSqlite3(filePath);
  
  // Guardar la ruta para poder limpiar el archivo al cerrar
  (db as any).__testFilePath = filePath;
  setTestDb(db);
  
  return db;
}

export function resetTestDb() {
  const db = getTestDb();
  if (db) {
    db.close();
    const filePath = (db as any).__testFilePath;
    if (filePath) {
      try {
        const fs = require('fs');
        fs.unlinkSync(filePath);
      } catch { /* noop */ }
    }
    setTestDb(null);
  }
}

/**
 * Ejecuta un script de migración tolerando errores de columnas duplicadas,
 * replicando exactamente el comportamiento de run_migration_script en Rust.
 */
function executeMigrationScript(db: any, sqlScript: string) {
  const statements = sqlScript
    .replace(/--.*$/gm, '') // eliminar comentarios de una línea
    .replace(/\/\*[\s\S]*?\*\//g, '') // eliminar comentarios de bloque
    .split(';')
    .map((s: string) => s.trim())
    .filter((s: string) => s.length > 0);

  for (const stmt of statements) {
    if (stmt.toUpperCase().startsWith('PRAGMA')) continue;
    try {
      db.exec(stmt);
    } catch (e: any) {
      const msg = e.message.toLowerCase();
      // Ignorar errores que Rust también ignora (idempotencia de esquema)
      if (msg.includes('duplicate column name') || msg.includes('already exists')) {
        continue;
      }
      throw e;
    }
  }
}

export async function pingDatabase(): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) throw new Error("Test DB not initialized. Call initTestDb() first.");
    db.prepare("SELECT 1").get();
    return true;
  }
  return await invoke<boolean>("ping_database");
}

export async function applyMigration(version: string, sqlScript: string): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) throw new Error("Test DB not initialized.");
    executeMigrationScript(db, sqlScript);
    db.exec(`INSERT OR IGNORE INTO migrations (version) VALUES ('${version}')`);
    return true;
  }
  return await invoke<boolean>("apply_migration", { version, sqlScript });
}

export async function executeReadQuery<T = any>(sql: string, params: (string | number | boolean | null)[] = []): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) throw new Error("Test DB not initialized.");
    return db.prepare(sql).all(...params) as T[];
  }
  return await invoke<any>("execute_read_query", { sql, params });
}

export async function executeWriteStatements(statements: DbStatement[]): Promise<any> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) throw new Error("Test DB not initialized.");
    
    const results: any[] = [];
    db.transaction(() => {
      for (const stmt of statements) {
        results.push(db.prepare(stmt.sql).run(...(stmt.params || [])));
      }
    })();
    return results;
  }
  return await invoke<any>("execute_write_statements", { statements });
}

export async function claimPendingSyncItems<T = any>(limit: number): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) return [];
    const now = new Date().toISOString();
    return db.prepare(`
      UPDATE sync_queue
      SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP
      WHERE id IN (
        SELECT id FROM sync_queue
        WHERE sync_status = 'pending'
          AND (next_retry_at IS NULL OR datetime(next_retry_at) <= datetime('now'))
        ORDER BY created_at ASC
        LIMIT ?
      )
      RETURNING *
    `).all(limit) as T[];
  }
  return (await invoke<T[]>("claim_pending_sync_items", { limit })) || [];
}
