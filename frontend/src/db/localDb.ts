import { executeQuery, executeTransaction, DbStatement, pingDatabase } from "./nativeDb";
import { localWriteCoordinator } from "./LocalWriteCoordinator";

export class LocalDatabase {
  async initialize(): Promise<void> {
    try {
      await pingDatabase();
      console.log("[LocalDB] ✅ Conexión con SQLite nativo verificada");
    } catch (error) {
      console.error("[LocalDB] ❌ Error conectando con SQLite nativo:", error);
      throw new Error("No se pudo conectar a SQLite");
    }
  }

  async execute(query: string, params?: unknown[]): Promise<any> {
    const statements: DbStatement[] = [{ 
      sql: query, 
      params: (params as any[]) || [] 
    }];
    return await localWriteCoordinator.executeTransaction(statements);
  }

  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    return await executeQuery<T>(query, (params as any[]) || []);
  }

  async selectOne<T = any>(query: string, params?: unknown[]): Promise<T | null> {
    const results = await this.select<T>(query, params);
    return results.length > 0 ? results[0] : null;
  }

  async transaction<T>(fn: (db: any) => Promise<T>): Promise<T> {
    return await localWriteCoordinator.transaction(fn);
  }

  async getConnection(): Promise<LocalDatabase> {
    return this;
  }

  async close(): Promise<void> {}
}

export const localDb = new LocalDatabase();
