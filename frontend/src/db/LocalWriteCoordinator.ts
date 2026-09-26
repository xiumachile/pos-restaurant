import { executeTransaction, executeQuery, DbStatement } from "./nativeDb";

export class LocalWriteCoordinator {
  /**
   * Ejecuta una transacción atómica usando el comando Rust personalizado.
   * Pasa un objeto 'txDb' que acumula las llamadas a execute() para enviarlas en un solo bloque a Rust.
   */
  async run<T>(operation: (db: any) => Promise<T>): Promise<T> {
    const statements: DbStatement[] = [];
    
    const txDb = {
      execute: (sql: string, params: any[] = []) => {
        statements.push({ sql, params });
      },
      select: async (sql: string, params: any[] = []) => {
        return await executeQuery(sql, params);
      }
    };

    try {
      const result = await operation(txDb);
      
      if (statements.length > 0) {
        await executeTransaction(statements);
      }
      
      return result;
    } catch (error: any) {
      console.error("[LocalWriteCoordinator] ❌ Error en transacción:", error?.message || error);
      throw error;
    }
  }

  /**
   * Ejecuta una consulta de lectura simple.
   */
  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    return await executeQuery<T>(query, params as any[]);
  }

  /**
   * Wrapper de compatibilidad para código que usa localDb.transaction().
   */
  async transaction<T>(fn: (db: any) => Promise<T>): Promise<T> {
    return await this.run(fn);
  }

  /**
   * Ejecuta un statement de escritura único de forma atómica.
   */
  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    const statements: DbStatement[] = [{ 
      sql: query, 
      params: (params as any[]) || [] 
    }];
    return await executeTransaction(statements);
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
