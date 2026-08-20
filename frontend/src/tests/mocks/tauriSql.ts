/**
 * Mock de @tauri-apps/plugin-sql para tests unitarios.
 * Simula una base de datos SQLite en memoria usando un Map.
 */
type Row = Record<string, any>;

class MockDatabase {
  private tables: Map<string, Row[]> = new Map();

  static async load(_conn: string): Promise<MockDatabase> {
    return new MockDatabase();
  }

  async execute(query: string, params?: any[]): Promise<number> {
    const q = query.trim().toLowerCase();

    // CREATE TABLE
    if (q.startsWith("create table")) {
      const match = query.match(/create table\s+(?:if not exists\s+)?(\w+)/i);
      if (match) {
        const tableName = match[1];
        if (!this.tables.has(tableName)) {
          this.tables.set(tableName, []);
        }
      }
      return 0;
    }

    // INSERT
    if (q.startsWith("insert")) {
      const match = query.match(/insert\s+(?:or\s+replace\s+)?into\s+(\w+)/i);
      if (match && params) {
        const tableName = match[1];
        if (!this.tables.has(tableName)) {
          this.tables.set(tableName, []);
        }
        const rows = this.tables.get(tableName)!;
        const colMatch = query.match(/\(([^)]+)\)\s*values/i);
        if (colMatch) {
          const cols = colMatch[1].split(",").map((c) => c.trim());
          const row: Row = {};
          cols.forEach((col, i) => {
            row[col] = params[i];
          });
          rows.push(row);
        }
      }
      return 1;
    }

    // DELETE
    if (q.startsWith("delete")) {
      const match = query.match(/delete from\s+(\w+)/i);
      if (match) {
        const tableName = match[1];
        const rows = this.tables.get(tableName) || [];
        if (params && params[0]) {
          const before = rows.length;
          const after = rows.filter((r) => !Object.values(r).includes(params[0]));
          this.tables.set(tableName, after);
          return before - after.length;
        }
      }
      return 0;
    }

    return 0;
  }

  async select<T = any>(query: string, params?: any[]): Promise<T[]> {
    const q = query.trim().toLowerCase();

    if (q.includes("select 1")) {
      return [{ ok: 1 }] as T[];
    }

    if (q.includes("pragma journal_mode")) {
      return [{ journal_mode: "wal" }] as T[];
    }

    if (q.includes("sqlite_master")) {
      const names = Array.from(this.tables.keys()).map((name) => ({ name }));
      return names as T[];
    }

    const match = query.match(/from\s+(\w+)/i);
    if (match) {
      const tableName = match[1];
      let rows = this.tables.get(tableName) || [];

      if (params && params[0]) {
        rows = rows.filter((r) =>
          Object.values(r).some((v) => v === params[0])
        );
      }

      return rows as T[];
    }

    return [];
  }

  async close(): Promise<void> {
    // no-op
  }
}

export default {
  load: MockDatabase.load.bind(MockDatabase),
};
