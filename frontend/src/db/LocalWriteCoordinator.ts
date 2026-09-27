import { executeTransaction, executeQuery, DbStatement } from "./nativeDb";

export class LocalWriteCoordinator {
  // Mutex para serializar operaciones de escritura y evitar race conditions
  private mutex: Promise<void> = Promise.resolve();

  /**
   * Ejecuta una operación que puede contener múltiples escrituras y lecturas.
   * Las operaciones se serializan mediante un mutex para garantizar que 
   * las validaciones (select) vean el estado más reciente confirmado.
   */
  async run<T>(operation: (db: any) => Promise<T>): Promise<T> {
    let release: () => void;
    const newMutex = new Promise<void>(resolve => {
      release = resolve;
    });
    
    // Encadenar con el mutex actual
    const currentMutex = this.mutex;
    this.mutex = newMutex;

    try {
      // 1. Esperar a que termine cualquier operación anterior
      await currentMutex;

      const pendingStatements: DbStatement[] = [];
      
      const flush = async () => {
        if (pendingStatements.length > 0) {
          await executeTransaction(pendingStatements);
          pendingStatements.length = 0; // Limpiar el array
        }
      };

      const txDb = {
        execute: (sql: string, params: any[] = []) => {
          pendingStatements.push({ sql, params });
        },
        select: async (sql: string, params: any[] = []) => {
          // ¡CRUCIAL! Asegurar que las escrituras previas de ESTA transacción 
          // se hayan aplicado antes de leer.
          await flush();
          return await executeQuery(sql, params);
        }
      };

      try {
        const result = await operation(txDb);
        // Ejecutar cualquier escritura restante al finalizar el callback
        await flush();
        return result;
      } catch (error: any) {
        console.error("[LocalWriteCoordinator] ❌ Error en operación:", error?.message || error);
        throw error; // Relanzar para que el llamador pueda manejar el fallo
      }
    } finally {
      // 2. Liberar el mutex para que la siguiente operación en cola pueda ejecutarse
      release!();
    }
  }

  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    return await executeQuery<T>(query, params as any[]);
  }

  async transaction<T>(fn: (db: any) => Promise<T>): Promise<T> {
    return await this.run(fn);
  }

  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    return await executeTransaction([{ 
      sql: query, 
      params: (params as any[]) || [] 
    }]);
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
