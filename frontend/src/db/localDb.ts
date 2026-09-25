import { executeQuery, executeTransaction, DbStatement } from "./nativeDb";
import { localWriteCoordinator } from "./LocalWriteCoordinator";

/**
 * Fachada de base de datos local que delega todas las operaciones
 * al backend nativo de Rust, eliminando por completo la dependencia
 * de @tauri-apps/plugin-sql en el frontend.
 */
export class LocalDatabase {
  /**
   * Verifica que la conexión nativa de Rust esté activa.
   */
  async initialize(): Promise<void> {
    try {
      await executeQuery("SELECT 1 as ping");
      console.log("[LocalDB] ✅ Conexión con SQLite nativo verificada");
    } catch (error) {
      console.error("[LocalDB] ❌ Error conectando con SQLite nativo:", error);
      throw new Error("No se pudo conectar a SQLite");
    }
  }

  /**
   * Ejecuta una consulta de escritura (INSERT, UPDATE, DELETE).
   */
  async execute(query: string, params?: unknown[]): Promise<any> {
    const statements: DbStatement[] = [{ 
      sql: query, 
      params: (params as any[]) || [] 
    }];
    return await executeTransaction(statements);
  }

  /**
   * Ejecuta una consulta de lectura (SELECT).
   */
  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    return await executeQuery<T>(query, (params as any[]) || []);
  }

  /**
   * Ejecuta una consulta de lectura que devuelve una sola fila.
   */
  async selectOne<T = any>(query: string, params?: unknown[]): Promise<T | null> {
    const results = await this.select<T>(query, params);
    return results.length > 0 ? results[0] : null;
  }

  /**
   * Ejecuta una transacción atómica delegando al LocalWriteCoordinator.
   */
  async transaction<T>(fn: (db: any) => Promise<T>): Promise<T> {
    return await localWriteCoordinator.transaction(fn);
  }

  /**
   * Método de compatibilidad para tests. Devuelve esta misma instancia.
   */
  async getConnection(): Promise<LocalDatabase> {
    return this;
  }

  /**
   * Método de compatibilidad para tests. No-op en arquitectura nativa.
   */
  async close(): Promise<void> {
    // La conexión es gestionada por el estado global de Rust
  }
}

export const localDb = new LocalDatabase();
