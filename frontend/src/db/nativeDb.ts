import { invoke } from "@tauri-apps/api/core";
import { localWriteCoordinator } from "./LocalWriteCoordinator";

export interface DbStatement {
  sql: string;
  params?: (string | number | boolean | null)[];
}

export function isTestEnvironment(): boolean {
  return typeof process !== 'undefined' && process.env.NODE_ENV === 'test';
}

let testDb: any = null;
export function getTestDb(): any { return testDb; }
export function setTestDb(db: any) { testDb = db; }

/**
 * Verifica la conexión con la base de datos nativa de Rust.
 */
export async function pingDatabase(): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (db) db.prepare("SELECT 1").get();
    return true;
  }
  return await invoke<boolean>("ping_database");
}

/**
 * Aplica una migración de esquema de forma segura a través de Rust.
 */
export async function applyMigration(version: string, sqlScript: string): Promise<boolean> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (db) {
      db.exec(sqlScript);
      db.exec(`INSERT OR IGNORE INTO migrations (version) VALUES ('${version}')`);
    }
    return true;
  }
  return await invoke<boolean>("apply_migration", { version, sqlScript });
}

/**
 * Ejecuta una consulta de lectura (SELECT).
 * NOTA: Si el comando 'execute_query' fue eliminado por seguridad, 
 * las lecturas deben usar comandos de dominio específicos o este comando 
 * debe ser reintroducido en Rust con validación estricta (solo SELECT).
 * Por ahora, intentamos invocar 'execute_read_query' o fallback.
 */
export async function executeQuery<T = any>(
  sql: string,
  params: (string | number | boolean | null)[] = []
): Promise<T[]> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    return db ? (db.prepare(sql).all(...params) as T[]) : [];
  }

  try {
    // Intentar usar un comando de lectura seguro si existe
    const result = await invoke<any>("execute_read_query", { sql, params });
    return (result as T[]) || [];
  } catch (e: any) {
    // Fallback si el comando no existe (ej. en versiones antiguas del backend)
    console.warn("[nativeDb] Comando de lectura no encontrado. Verificar implementación en Rust.");
    throw e;
  }
}

/**
 * Ejecuta una transacción de escritura delegando al coordinador.
 */
export async function executeTransaction(statements: DbStatement[]): Promise<any> {
  if (isTestEnvironment()) {
    const db = getTestDb();
    if (!db) return [];
    const results: any[] = [];
    db.transaction(() => {
      for (const stmt of statements) {
        results.push(db.prepare(stmt.sql).run(...(stmt.params || [])));
      }
    })();
    return results;
  }
  
  // Usar el método correcto del coordinador
  return await localWriteCoordinator.executeTransaction(statements);
}

export function resetTestDb() {
  if (testDb) {
    testDb.close();
    testDb = null;
  }
}
