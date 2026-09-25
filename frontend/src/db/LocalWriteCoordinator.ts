import Database from "@tauri-apps/plugin-sql";
import { localDb } from "./localDb";
import { writeMutex } from "./writeMutex";

export class LocalWriteCoordinator {
  /**
   * Ejecuta una transacción atómica.
   * Adquiere el mutex global, inicia BEGIN IMMEDIATE, ejecuta la operación con la conexión 'db',
   * y hace COMMIT. Si falla, hace ROLLBACK.
   */
  async run<T>(operation: (db: Database) => Promise<T>): Promise<T> {
    const release = await writeMutex.lock();
    const db = await localDb.getConnection();
    
    try {
      await db.execute("BEGIN IMMEDIATE;");
      const result = await operation(db);
      await db.execute("COMMIT;");
      return result;
    } catch (error: any) {
      console.error("[LocalWriteCoordinator] ❌ Error en transacción:", error?.message || error);
      try {
        await db.execute("ROLLBACK;");
      } catch (rollbackErr: any) {
        console.error("[LocalWriteCoordinator] ⚠️ Error al hacer rollback:", rollbackErr?.message || rollbackErr);
      }
      throw error;
    } finally {
      release();
    }
  }

  /**
   * Ejecuta un statement de escritura único, adquiriendo el Mutex global.
   */
  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    const release = await writeMutex.lock();
    const db = await localDb.getConnection();
    try {
      return await db.execute(query, params as any);
    } finally {
      release();
    }
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
