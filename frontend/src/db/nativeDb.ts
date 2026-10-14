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
 * Inicializa perezosamente la DB de pruebas. Garantiza que siempre exista
 * cuando se necesita, sin depender del orden de ejecución de los hooks.
 */
export async function initTestDb(): Promise<any> {
  if (!isTestEnvironment()) return null;
  if (testDb) return testDb;

  const { default: BetterSqlite3 } = await import('better-sqlite3');
  const path = await import('path');
  const os = await import('os');
  const { randomUUID } = await import('crypto');

  const filePath = path.join(os.tmpdir(), `pos-test-${randomUUID()}.db`);
  testDb = new BetterSqlite3(filePath);
  (testDb as any).__testFilePath = filePath;
  console.log("[nativeDb] ✅ Test DB inicializada en:", filePath);
  return testDb;
}

export function resetTestDb() {
  if (testDb) {
    testDb.close();
    const filePath = (testDb as any).__testFilePath;
    if (filePath) {
      try {
        const fs = require('fs');
        fs.unlinkSync(filePath);
      } catch { /* noop */ }
    }
    testDb = null;
  }
}

export async function pingDatabase(): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = await initTestDb();
    if (!db) throw new Error("Test DB no se pudo inicializar");
    db.prepare("SELECT 1").get();
    return true;
  }
  return await invoke<boolean>("ping_database");
}

export async function applyMigration(version: string, sqlScript: string): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = await initTestDb();
    if (!db) throw new Error("Test DB no se pudo inicializar");

    const statements = sqlScript
      .replace(/--.*$/gm, '')
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .split(';')
      .map((s: string) => s.trim())
      .filter((s: string) => s.length > 0);

    for (const stmt of statements) {
      if (stmt.toUpperCase().startsWith('PRAGMA')) continue;
      try {
        db.exec(stmt);
      } catch (e: any) {
        const msg = e.message.toLowerCase();
        if (msg.includes('duplicate column name') || msg.includes('already exists')) {
          continue; // Idempotencia de esquema, igual que en Rust
        }
        throw e;
      }
    }
    db.exec(`INSERT OR IGNORE INTO migrations (version) VALUES ('${version}')`);
    return true;
  }

  // AUD-04 FIX: En producción, Rust carga el script de forma segura desde include_str!.
  // No enviamos sqlScript para cumplir estrictamente con el contrato de seguridad.
  return await invoke<boolean>("apply_migration", { version });
}

export async function executeReadQuery<T = any>(sql: string, params: (string | number | boolean | null)[] = []): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = await initTestDb();
    if (!db) throw new Error("Test DB no se pudo inicializar");
    return db.prepare(sql).all(...params) as T[];
  }
  return await invoke<any>("execute_read_query", { sql, params });
}

/**
 * Forma del resultado de escritura. En producción la devuelve Rust
 * (execute_write_statements); en tests se construye aquí con la misma forma.
 */
export interface WriteResult {
  success: boolean;
  count: number;
  rowsAffected: number;
}

export async function executeWriteStatements(statements: DbStatement[]): Promise<any> {
  if (isTestEnvironment()) {
    const db = await initTestDb();
    if (!db) throw new Error("Test DB no se pudo inicializar");

    const results: any[] = [];
    db.transaction(() => {
      for (const stmt of statements) {
        results.push(db.prepare(stmt.sql).run(...(stmt.params || [])));
      }
    })();

    const meta: WriteResult = {
      success: true,
      count: statements.length,
      rowsAffected: results.reduce((sum, r) => sum + (r?.changes ?? 0), 0),
    };
    // Se conserva el array original (results[0].changes, etc.) y se le agrega la
    // misma forma que devuelve Rust, para no romper código de tests existente.
    return Object.assign(results, meta);
  }
  return await invoke<any>("execute_write_statements", { statements });
}

export async function claimPendingSyncItems<T = any>(limit: number): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = await initTestDb();
    if (!db) return [];
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
