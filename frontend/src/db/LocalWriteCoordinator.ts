import { executeReadQuery, executeWriteStatements, DbStatement } from "./nativeDb";

export class LocalWriteCoordinator {
  private mutex: Promise<void> = Promise.resolve();

  async run<T>(operation: (db: any) => Promise<T>): Promise<T> {
    let release: () => void;
    const newMutex = new Promise<void>(resolve => { release = resolve; });
    
    const currentMutex = this.mutex;
    this.mutex = newMutex;

    try {
      await currentMutex;
      const pendingStatements: DbStatement[] = [];
      
      const flush = async () => {
        if (pendingStatements.length > 0) {
          await executeWriteStatements(pendingStatements); // <-- LLAMADA DIRECTA, SIN RECURSIÓN
          pendingStatements.length = 0;
        }
      };

      const txDb = {
        execute: (sql: string, params: any[] = []) => {
          pendingStatements.push({ sql, params });
        },
        select: async (sql: string, params: any[] = []) => {
          await flush();
          return await executeReadQuery(sql, params);
        }
      };

      try {
        const result = await operation(txDb);
        await flush();
        return result;
      } catch (error: any) {
        console.error("[LocalWriteCoordinator] ❌ Error en operación:", error?.message || error);
        throw error;
      }
    } finally {
      release!();
    }
  }

  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    return await executeReadQuery<T>(query, params as any[]);
  }

  async transaction<T>(fn: (db: any) => Promise<T>): Promise<T> {
    return await this.run(fn);
  }

  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    return await executeWriteStatements([{ sql: query, params: (params as any[]) || [] }]);
  }

  async executeTransaction(statements: DbStatement[]): Promise<any> {
    return await executeWriteStatements(statements);
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
