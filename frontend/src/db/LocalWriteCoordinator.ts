import Database from "@tauri-apps/plugin-sql";
import { localDb } from "./localDb";
import { writeMutex } from "./writeMutex";

export class LocalWriteCoordinator {
  async run<T>(operation: (db: Database) => Promise<T>): Promise<T> {
    const release = await writeMutex.lock();
    const db = await localDb.getConnection();
    
    try {
      // Llamar directamente a db.execute (no a localDb.execute) para evitar deadlock
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

  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    // Usar localDb.execute que ya tiene el mutex
    return await localDb.execute(query, params);
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
