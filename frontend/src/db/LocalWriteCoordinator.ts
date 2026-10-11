import { executeTransaction, executeQuery, DbStatement } from "./nativeDb";

export class LocalWriteCoordinator {
  private mutex: Promise<void> = Promise.resolve();

  async run<T>(operation: (db: any) => Promise<T>): Promise<T> {
    let release: () => void;
    const newMutex = new Promise<void>(resolve => {
      release = resolve;
    });
    
    const currentMutex = this.mutex;
    this.mutex = newMutex;

    try {
      await currentMutex;
      const pendingStatements: DbStatement[] = [];
      
      const flush = async () => {
        if (pendingStatements.length > 0) {
          await executeTransaction(pendingStatements);
          pendingStatements.length = 0;
        }
      };

      const txDb = {
        execute: (sql: string, params: any[] = []) => {
          pendingStatements.push({ sql, params });
        },
        select: async (sql: string, params: any[] = []) => {
          await flush();
          return await executeQuery(sql, params);
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

  async executeTransaction(statements: DbStatement[]): Promise<any> {
    return await this.run(async (txDb) => {
      const results: any[] = [];
      for (const stmt of statements) {
        txDb.execute(stmt.sql, stmt.params || []);
        results.push({ success: true });
      }
      return results;
    });
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
