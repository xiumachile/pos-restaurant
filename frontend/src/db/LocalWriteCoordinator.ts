import { executeTransaction, executeQuery, DbStatement } from "./nativeDb";

export class LocalWriteCoordinator {
  /**
   * Ejecuta una operación que puede contener múltiples escrituras y lecturas.
   * Las escrituras se acumulan para enviarse en lotes atómicos a Rust.
   * Si se realiza una lectura (select), se hace flush de las escrituras pendientes
   * para asegurar que la lectura vea los datos más recientes (consistencia).
   */
  async run<T>(operation: (db: any) => Promise<T>): Promise<T> {
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
        // ¡CRUCIAL! Asegurar que las escrituras previas se hayan aplicado 
        // antes de leer, para mantener la consistencia dentro del callback.
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
      throw error; // Relanzar para que el llamador pueda manejar el rollback/fallo
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
