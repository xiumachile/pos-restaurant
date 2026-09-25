import { Database } from "@tauri-apps/plugin-sql";
import { localDb } from "./localDb";

/**
 * Mutex simple para serializar escrituras a nivel de aplicación.
 * Garantiza que solo una operación de escritura (transacción o statement único)
 * se ejecute a la vez, previniendo "database is locked" en SQLite/Tauri.
 */
class Mutex {
  private queue: Promise<void> = Promise.resolve();

  async acquire(): Promise<() => void> {
    let release!: () => void;
    const nextPromise = new Promise<void>((resolve) => {
      release = resolve;
    });
    
    const currentQueue = this.queue;
    this.queue = currentQueue.then(() => nextPromise).catch(() => nextPromise);
    
    await currentQueue;
    return release;
  }
}

const writeMutex = new Mutex();

export class LocalWriteCoordinator {
  /**
   * Ejecuta una operación de escritura atómica (multi-statement).
   * La operación DEBE usar la instancia `db` proporcionada, NUNCA `localDb.execute`.
   */
  async run<T>(operation: (db: Database) => Promise<T>): Promise<T> {
    const release = await writeMutex.acquire();
    const db = await localDb.getConnection();
    
    try {
      // BEGIN IMMEDIATE obtiene el bloqueo de escritura inmediatamente.
      // Combinado con PRAGMA busy_timeout = 5000, esperará a otros escritores nativos.
      await db.execute("BEGIN IMMEDIATE");
      
      const result = await operation(db);
      
      await db.execute("COMMIT");
      return result;
    } catch (error: any) {
      console.error("[LocalWriteCoordinator] ❌ Error en transacción:", error?.message || error);
      try {
        await db.execute("ROLLBACK");
      } catch (rollbackErr: any) {
        console.error("[LocalWriteCoordinator] ⚠️ Error al hacer rollback:", rollbackErr?.message || rollbackErr);
      }
      throw error;
    } finally {
      release();
    }
  }

  /**
   * Ejecuta un statement de escritura único (INSERT, UPDATE, DELETE).
   * Útil para operaciones que no requieren atomicidad multi-statement, 
   * pero que aún deben serializarse para evitar bloqueos.
   */
  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    const release = await writeMutex.acquire();
    const db = await localDb.getConnection();
    
    try {
      return await db.execute(query, params as any);
    } finally {
      release();
    }
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
