import Database from "@tauri-apps/plugin-sql";

/**
 * Wrapper type-safe sobre el plugin SQL de Tauri.
 * Proporciona acceso a la base de datos SQLite local con WAL mode.
 */
class LocalDatabase {
  private db: Database | null = null;
  private initPromise: Promise<Database> | null = null;

  /**
   * Obtiene la instancia de la base de datos (singleton).
   * La primera vez que se llama, inicializa la DB y aplica WAL mode.
   */
  async getConnection(): Promise<Database> {
    if (this.db) return this.db;

    if (!this.initPromise) {
      this.initPromise = this.initialize();
    }

    return this.initPromise;
  }

  private async initialize(): Promise<Database> {
    console.log("[LocalDB] Inicializando base de datos SQLite...");

    // Cargar/crear la base de datos
    const db = await Database.load("sqlite:pos_local.db");

    // Configurar WAL mode para concurrencia
    await db.execute("PRAGMA journal_mode=WAL;");
    await db.execute("PRAGMA synchronous=NORMAL;");
    await db.execute("PRAGMA foreign_keys=ON;");

    console.log("[LocalDB] SQLite configurado con WAL mode");

    this.db = db;
    return db;
  }

  /**
   * Ejecuta una consulta que modifica datos (INSERT, UPDATE, DELETE).
   * Retorna el número de filas afectadas.
   */
  async execute(query: string, params?: unknown[]): Promise<number> {
    const db = await this.getConnection();
    const result = await db.execute(query, params as any);
    // El plugin retorna QueryResult con rowsAffected
    return (result as any)?.rowsAffected ?? 0;
  }

  /**
   * Ejecuta una consulta SELECT y retorna los resultados.
   */
  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    const db = await this.getConnection();
    const results = await db.select<T>(query, params as any);
    // select retorna T[] directamente
    return (results ?? []) as T[];
  }

  /**
   * Ejecuta una consulta SELECT y retorna el primer resultado.
   */
  async selectOne<T = any>(query: string, params?: unknown[]): Promise<T | null> {
    const results = await this.select<T>(query, params);
    return results.length > 0 ? results[0] : null;
  }

  /**
   * Ejecuta múltiples queries en una transacción.
   * Si alguna falla, hace rollback de todas.
   */
  async transaction<T>(fn: (db: Database) => Promise<T>): Promise<T> {
    const db = await this.getConnection();
    await db.execute("BEGIN TRANSACTION;");
    try {
      const result = await fn(db);
      await db.execute("COMMIT;");
      return result;
    } catch (error) {
      await db.execute("ROLLBACK;");
      throw error;
    }
  }

  /**
   * Cierra la conexión (útil para limpieza).
   */
  async close(): Promise<void> {
    if (this.db) {
      await this.db.close();
      this.db = null;
      this.initPromise = null;
    }
  }
}

// Exportar singleton
export const localDb = new LocalDatabase();
