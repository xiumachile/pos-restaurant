import { executeQuery, executeTransaction, DbStatement } from "./nativeDb";

/**
 * Fachada de base de datos local que delega todas las operaciones
 * al backend nativo de Rust, eliminando por completo la dependencia
 * de @tauri-apps/plugin-sql en el frontend.
 */
export class LocalDatabase {
  /**
   * Verifica que la conexión nativa de Rust esté activa.
   * (Rust ya abre y configura la BD con PRAGMAs en el setup).
   */
  async initialize(): Promise<void> {
    try {
      // Hacemos un ping simple a la BD nativa
      await executeQuery("SELECT 1 as ping");
      console.log("[LocalDB] ✅ Conexión con SQLite nativo verificada");
    } catch (error) {
      console.error("[LocalDB] ❌ Error conectando con SQLite nativo:", error);
      throw new Error("No se pudo conectar a SQLite");
    }
  }

  /**
   * Ejecuta una consulta de escritura (INSERT, UPDATE, DELETE) o un statement único.
   * Lo envuelve en una transacción atómica de 1 solo statement para garantizar seguridad.
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
}

export const localDb = new LocalDatabase();
