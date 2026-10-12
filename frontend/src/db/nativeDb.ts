import { invoke } from "@tauri-apps/api/core";
import { Database } from "@tauri-apps/plugin-sql"; // Será interceptado por vi.mock en tests

export interface DbStatement {
  sql: string;
  params?: (string | number | boolean | null)[];
}

export function isTestEnvironment(): boolean {
  return typeof process !== 'undefined' && process.env.NODE_ENV === 'test';
}

let testDb: any = null;

async function getDb() {
  if (isTestEnvironment()) {
    if (!testDb) {
      testDb = await Database.load("sqlite:test.db");
    }
    return testDb;
  }
  return null;
}

export async function pingDatabase(): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = await getDb();
    await db.execute("SELECT 1");
    return true;
  }
  return await invoke<boolean>("ping_database");
}

export async function applyMigration(version: string, sqlScript: string): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = await getDb();
    await db.exec(sqlScript);
    await db.exec(`INSERT OR IGNORE INTO migrations (version) VALUES ('${version}')`);
    return true;
  }
  return await invoke<boolean>("apply_migration", { version, sqlScript });
}

export async function executeReadQuery<T = any>(sql: string, params: (string | number | boolean | null)[] = []): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = await getDb();
    return await db.select<T>(sql, params);
  }
  return await invoke<any>("execute_read_query", { sql, params });
}

export async function executeWriteStatements(statements: DbStatement[]): Promise<any> {
  if (isTestEnvironment()) {
    const db = await getDb();
    const results: any[] = [];
    // Transacción real en better-sqlite3 para garantizar atomicidad en tests
    db.transaction(() => {
      for (const stmt of statements) {
        results.push(db.execute(stmt.sql, stmt.params || []));
      }
    })();
    return results;
  }
  return await invoke<any>("execute_write_statements", { statements });
}

export function resetTestDb() {
  if (testDb) {
    testDb.close();
    testDb = null;
  }
}

export async function claimPendingSyncItems<T = any>(limit: number): Promise<T[]> {
  if (isTestEnvironment()) {
    // Fallback para tests que usan claimPending directamente
    const db = await getDb();
    const now = new Date().toISOString();
    const items = await db.select<T>(`
      UPDATE sync_queue
      SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP
      WHERE id IN (
        SELECT id FROM sync_queue
        WHERE sync_status = 'pending'
          AND (next_retry_at IS NULL OR next_retry_at <= ?)
        ORDER BY created_at ASC
        LIMIT ?
      )
      RETURNING *
    `, [now, limit]);
    return items || [];
  }
  return (await invoke<T[]>("claim_pending_sync_items", { limit })) || [];
}
