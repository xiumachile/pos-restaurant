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
    this.filePath = path.join(os.tmpdir(), `pos-test-${randomUUID()}.db`);
    this.db = new BetterSqlite3(this.filePath);
  }

  static async load(_connectionString: string): Promise<RealSqliteDatabase> {
    return new RealSqliteDatabase();
  }

  private normalizeParams(params?: unknown[]): unknown[] {
    if (!params) return [];
    return params.map((p) => (p === undefined ? null : p));
  }

  async execute(query: string, params?: unknown[]): Promise<QueryResult> {
    const trimmed = query.trim();
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

  async exec(query: string): Promise<void> {
    this.db.exec(query);
  }

  async close(): Promise<void> {
    this.db.close();
    try {
      fs.unlinkSync(this.filePath);
    } catch {
      /* noop */
    }
  }
}

// Exportar tanto como default como nombrado para compatibilidad
export const Database = {
  load: RealSqliteDatabase.load.bind(RealSqliteDatabase),
};
export default Database;
