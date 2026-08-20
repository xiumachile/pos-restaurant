/**
 * Mock de @tauri-apps/plugin-sql para tests unitarios.
 */
type Row = Record<string, any>;

class MockDatabase {
  private tables: Map<string, Row[]> = new Map();

  static async load(_conn: string): Promise<MockDatabase> {
    return new MockDatabase();
  }

  /**
   * Parsea VALUES mezclando ? y literales.
   */
  private parseValues(valuesStr: string, params: any[]): any[] {
    const result: any[] = [];
    let paramIdx = 0;
    let current = "";
    let inString = false;
    let stringChar = "";
    let parenDepth = 0;

    const flush = () => {
      const val = current.trim();
      if (!val) return;
      
      if (val === "?") {
        result.push(params[paramIdx++]);
      } else if (val === "CURRENT_TIMESTAMP" || val.startsWith("datetime(")) {
        result.push(new Date().toISOString());
      } else if ((val.startsWith("'") && val.endsWith("'")) || 
                 (val.startsWith('"') && val.endsWith('"'))) {
        result.push(val.slice(1, -1));
      } else if (/^-?\d+(\.\d+)?$/.test(val)) {
        result.push(parseFloat(val));
      } else if (val.toUpperCase() === "NULL") {
        result.push(null);
      } else {
        result.push(val);
      }
      current = "";
    };

    for (let i = 0; i < valuesStr.length; i++) {
      const char = valuesStr[i];

      if (inString) {
        current += char;
        if (char === stringChar && valuesStr[i - 1] !== "\\") {
          inString = false;
          flush();
        }
        continue;
      }

      if (char === "'" || char === '"') {
        inString = true;
        stringChar = char;
        current += char;
        continue;
      }

      if (char === "(") {
        parenDepth++;
        current += char;
        continue;
      }

      if (char === ")") {
        parenDepth--;
        current += char;
        continue;
      }

      if (char === "," && parenDepth === 0) {
        flush();
        continue;
      }

      current += char;
    }

    flush();
    return result;
  }

  async execute(query: string, params?: any[]): Promise<number> {
    const q = query.trim().toLowerCase();
    const safeParams = params || [];

    if (q.startsWith("create table")) {
      const match = query.match(/create table\s+(?:if not exists\s+)?(\w+)/i);
      if (match && !this.tables.has(match[1])) {
        this.tables.set(match[1], []);
      }
      return 0;
    }

    if (q.startsWith("insert")) {
      const match = query.match(
        /insert\s+(?:or\s+replace\s+)?into\s+(\w+)\s*\(([^)]+)\)\s*values\s*\(([\s\S]+)\)/i
      );
      if (match) {
        const tableName = match[1];
        const cols = match[2].split(",").map((c) => c.trim());
        const values = this.parseValues(match[3], safeParams);

        if (!this.tables.has(tableName)) this.tables.set(tableName, []);
        const rows = this.tables.get(tableName)!;

        if (q.includes("or replace")) {
          const idx = rows.findIndex((r) => r[cols[0]] === values[0]);
          if (idx >= 0) rows.splice(idx, 1);
        }

        const row: Row = {};
        cols.forEach((col, i) => { row[col] = values[i]; });
        rows.push(row);
        return 1;
      }
      return 0;
    }

    if (q.startsWith("update")) {
      const match = query.match(
        /update\s+(\w+)\s+set\s+([\s\S]+?)(?:\s+where\s+([\s\S]+))?\s*$/i
      );
      if (match) {
        const tableName = match[1];
        const setClause = match[2];
        const whereClause = match[3];
        const rows = this.tables.get(tableName) || [];

        // Parsear SET respetando paréntesis (para funciones como datetime())
        const assignments: Array<{ col: string; value: any }> = [];
        let paramIdx = 0;
        let current = "";
        let parenDepth = 0;

        for (let i = 0; i <= setClause.length; i++) {
          const char = setClause[i] || ",";
          
          if (char === "(") parenDepth++;
          if (char === ")") parenDepth--;
          
          if (char === "," && parenDepth === 0) {
            const assignment = current.trim();
            if (assignment) {
              const eqIdx = assignment.indexOf("=");
              if (eqIdx > 0) {
                const col = assignment.substring(0, eqIdx).trim();
                const val = assignment.substring(eqIdx + 1).trim();

                let resolvedVal: any;
                if (val === "?") {
                  resolvedVal = safeParams[paramIdx++];
                } else if (val === "CURRENT_TIMESTAMP" || val.startsWith("datetime(")) {
                  resolvedVal = new Date().toISOString();
                } else if ((val.startsWith("'") && val.endsWith("'")) || 
                           (val.startsWith('"') && val.endsWith('"'))) {
                  resolvedVal = val.slice(1, -1);
                } else if (/^-?\d+(\.\d+)?$/.test(val)) {
                  resolvedVal = parseFloat(val);
                } else if (val.toUpperCase() === "NULL") {
                  resolvedVal = null;
                } else {
                  resolvedVal = val;
                }

                assignments.push({ col, value: resolvedVal });
              }
            }
            current = "";
          } else {
            current += char;
          }
        }

        let affected = 0;
        for (const row of rows) {
          if (!whereClause || this.matchesWhere(row, whereClause, safeParams.slice(paramIdx))) {
            for (const { col, value } of assignments) {
              row[col] = value;
            }
            affected++;
          }
        }
        return affected;
      }
      return 0;
    }

    if (q.startsWith("delete")) {
      const match = query.match(/delete from\s+(\w+)(?:\s+where\s+([\s\S]+))?/i);
      if (match) {
        const tableName = match[1];
        const whereClause = match[2];
        const rows = this.tables.get(tableName) || [];

        if (!whereClause) {
          const count = rows.length;
          this.tables.set(tableName, []);
          return count;
        }

        const before = rows.length;
        const after = rows.filter((r) => !this.matchesWhere(r, whereClause, safeParams));
        this.tables.set(tableName, after);
        return before - after.length;
      }
      return 0;
    }

    return 0;
  }

  async select<T = any>(query: string, params?: any[]): Promise<T[]> {
    const q = query.trim().toLowerCase();
    const safeParams = params || [];

    if (q.includes("pragma journal_mode")) return [{ journal_mode: "wal" }] as T[];
    if (/select\s+1\s+as\s+\w+/i.test(query)) return [{ ok: 1 }] as T[];
    if (q.includes("sqlite_master")) {
      return Array.from(this.tables.keys()).map((name) => ({ name })) as T[];
    }

    const match = query.match(
      /select\s+([\s\S]+?)\s+from\s+(\w+)(?:\s+where\s+([\s\S]+?))?(?:\s+order\s+by\s+([\s\S]+?))?(?:\s+limit\s+(\d+|\?))?\s*$/i
    );
    if (!match) return [];

    const [, selectClause, tableName, whereClause, orderByClause, limitClause] = match;
    let rows = this.tables.get(tableName) || [];

    if (whereClause) {
      rows = rows.filter((r) => this.matchesWhere(r, whereClause, safeParams));
    }

    if (orderByClause) {
      const orderMatch = orderByClause.match(/(\w+)(?:\s+(asc|desc))?/i);
      if (orderMatch) {
        const col = orderMatch[1];
        const dir = (orderMatch[2] || "asc").toLowerCase();
        rows = [...rows].sort((a, b) => {
          if (a[col] < b[col]) return dir === "asc" ? -1 : 1;
          if (a[col] > b[col]) return dir === "asc" ? 1 : -1;
          return 0;
        });
      }
    }

    if (limitClause) {
      const limit = limitClause === "?" 
        ? Number(safeParams[safeParams.length - 1]) 
        : parseInt(limitClause);
      rows = rows.slice(0, limit);
    }

    const hasCount = /count\s*\(\s*\*\s*\)/i.test(selectClause);
    const hasSum = /sum\s*\(/i.test(selectClause);

    if (hasCount || hasSum) {
      const result: Row = {};

      if (hasCount) {
        const aliasMatch = selectClause.match(/count\s*\(\s*\*\s*\)\s+as\s+(\w+)/i);
        result[aliasMatch ? aliasMatch[1] : "count"] = rows.length;
      }

      if (hasSum) {
        const sumMatch = selectClause.match(/(?:coalesce\s*\(\s*)?sum\s*\(\s*(\w+)\s*\)/i);
        if (sumMatch) {
          const sum = rows.reduce((acc, r) => acc + (Number(r[sumMatch[1]]) || 0), 0);
          const aliasMatch = selectClause.match(/as\s+(\w+)/i);
          result[aliasMatch ? aliasMatch[1] : "total"] = sum;
        }
      }

      return [result] as T[];
    }

    return rows as T[];
  }

  private matchesWhere(row: Row, whereClause: string, params: any[]): boolean {
    const conditions = whereClause.split(/\s+AND\s+/i);
    let paramIdx = 0;

    for (const cond of conditions) {
      const trimmed = cond.trim();

      if (/\w+\s+IS\s+NULL/i.test(trimmed)) {
        const col = trimmed.match(/(\w+)\s+IS\s+NULL/i)?.[1];
        if (col && row[col] !== null && row[col] !== undefined) return false;
        continue;
      }

      if (/\w+\s+IS\s+NOT\s+NULL/i.test(trimmed)) {
        const col = trimmed.match(/(\w+)\s+IS\s+NOT\s+NULL/i)?.[1];
        if (col && (row[col] === null || row[col] === undefined)) return false;
        continue;
      }

      if (trimmed.startsWith("(") || trimmed.includes("datetime(")) {
        paramIdx += (trimmed.match(/\?/g) || []).length;
        continue;
      }

      const match = trimmed.match(/(\w+)\s*(=|!=|<=|>=|<|>|LIKE)\s*(.+)/i);
      if (match) {
        const col = match[1];
        const op = match[2].toUpperCase();
        const valStr = match[3].trim();

        let value: any;
        if (valStr === "?") {
          value = params[paramIdx++];
        } else if ((valStr.startsWith("'") && valStr.endsWith("'")) || 
                   (valStr.startsWith('"') && valStr.endsWith('"'))) {
          value = valStr.slice(1, -1);
        } else if (/^-?\d+(\.\d+)?$/.test(valStr)) {
          value = parseFloat(valStr);
        } else if (valStr.toUpperCase() === "NULL") {
          value = null;
        } else {
          value = valStr;
        }

        const rowVal = row[col];
        let matches = false;

        switch (op) {
          case "=": matches = rowVal === value; break;
          case "!=": matches = rowVal !== value; break;
          case "<": matches = rowVal < value; break;
          case ">": matches = rowVal > value; break;
          case "<=": matches = rowVal <= value; break;
          case ">=": matches = rowVal >= value; break;
          case "LIKE": matches = String(rowVal).includes(String(value).replace(/%/g, "")); break;
        }

        if (!matches) return false;
      }
    }

    return true;
  }

  async close(): Promise<void> {}
}

export default {
  load: MockDatabase.load.bind(MockDatabase),
};
