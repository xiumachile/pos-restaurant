/**
 * Reemplazo de @tauri-apps/plugin-sql para tests, respaldado por SQLite REAL
 * (better-sqlite3, el mismo motor C de SQLite que usa Tauri en producción).
 *
 * Por qué: el mock anterior era un intérprete de SQL escrito a mano en JS.
 * No reproducía comportamientos reales de SQLite como:
 *   - Semántica POSICIONAL de "INSERT INTO x SELECT * FROM y" cuando cambia
 *     el orden de columnas entre la tabla origen y la tabla destino.
 *   - Constraints reales (NOT NULL, UNIQUE, duplicate column name).
 *   - Errores de sintaxis SQL genuinos (una misma columna repetida en un
 *     SET, un ALTER ADD COLUMN sobre una columna que ya existe, etc).
 * Como resultado, migraciones con bugs de SQL podían pasar los tests en
 * verde y fallar recién al correr contra SQLite real vía `tauri dev`.
 *
 * Este adaptador expone la misma superficie que consume localDb.ts
 * (Database.load / db.execute / db.select / db.close), pero delega toda
 * la ejecución real a SQLite, así que cualquier bug de SQL que reviente en
 * producción también revienta aquí, en el test.
 *
 * Cada llamada a Database.load(...) crea una base de datos nueva e
 * independiente respaldada por un archivo temporal en disco (no ":memory:",
 * ver nota en el constructor), para que cada archivo de test arranque con
 * estado limpio, igual que hacía el mock anterior.
 */
import BetterSqlite3 from "better-sqlite3";
import fs from "fs";
import os from "os";
import path from "path";
import { randomUUID } from "crypto";

interface QueryResult {
  rowsAffected: number;
  lastInsertId?: number;
}

class RealSqliteDatabase {
  private db: BetterSqlite3.Database;
  private filePath: string;

  private constructor() {
    // Archivo real en disco (no ":memory:"): PRAGMA journal_mode=WAL no
    // aplica a bases en memoria (SQLite cae silenciosamente a "memory"), y
    // tu app en producción sí abre un archivo real con WAL. Un archivo
    // temporal por instancia mantiene el comportamiento fiel sin ensuciar
    // el repo ni requerir limpieza manual entre corridas.
    this.filePath = path.join(os.tmpdir(), `pos-test-${randomUUID()}.db`);
    this.db = new BetterSqlite3(this.filePath);
  }

  static async load(_connectionString: string): Promise<RealSqliteDatabase> {
    return new RealSqliteDatabase();
  }

  /**
   * Normaliza params: better-sqlite3 no acepta `undefined` como bind
   * parameter (solo null), a diferencia del plugin de Tauri que sí lo tolera.
   */
  private normalizeParams(params?: unknown[]): unknown[] {
    if (!params) return [];
    return params.map((p) => (p === undefined ? null : p));
  }

  async execute(query: string, params?: unknown[]): Promise<QueryResult> {
    const trimmed = query.trim();

    // PRAGMA y control de transacciones no se comportan bien con
    // prepare().run() en better-sqlite3 — se ejecutan directo con exec/pragma.
    if (/^PRAGMA/i.test(trimmed)) {
      const pragmaBody = trimmed.replace(/^PRAGMA\s+/i, "").replace(/;$/, "");
      this.db.pragma(pragmaBody);
      return { rowsAffected: 0 };
    }

    if (/^(BEGIN|COMMIT|ROLLBACK)/i.test(trimmed)) {
      this.db.exec(trimmed);
      return { rowsAffected: 0 };
    }

    const stmt = this.db.prepare(query);
    const info = stmt.run(...this.normalizeParams(params));
    return {
      rowsAffected: info.changes,
      lastInsertId: Number(info.lastInsertRowid),
    };
  }

  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    const stmt = this.db.prepare(query);
    return stmt.all(...this.normalizeParams(params)) as T[];
  }

  async close(): Promise<void> {
    this.db.close();
    // Limpieza best-effort del archivo temporal; no crítico si falla.
    try {
      fs.unlinkSync(this.filePath);
    } catch {
      /* noop */
    }
  }
}

export default {
  load: RealSqliteDatabase.load.bind(RealSqliteDatabase),
};
