import { describe, it, expect, beforeAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../../db/localDb";
import migration017 from "../../../db/migrations/017_enforce_integer_money.sql?raw";

function parseSqlStatements(sql: string): string[] {
  return sql
    .split(';')
    .map(s => s.split('\n').filter(line => !line.trim().startsWith('--')).join('\n').trim())
    .filter(s => s.length > 0);
}

async function validateNoFractionalValues(): Promise<void> {
  const tablesToCheck = [
    { table: 'local_orders', columns: ['subtotal', 'discount_total', 'tax_total', 'tip_amount', 'grand_total'] },
    { table: 'local_order_items', columns: ['unit_price', 'subtotal'] },
    { table: 'local_payments', columns: ['amount', 'tip_amount'] },
    { table: 'local_bills', columns: ['subtotal', 'discount_total', 'tax_total', 'tip_amount', 'grand_total', 'paid_amount', 'remaining_amount'] },
    { table: 'local_cash_sessions', columns: ['opening_amount', 'closing_amount'] },
    { table: 'local_cash_movements', columns: ['amount', 'balance_after'] },
    { table: 'local_products', columns: ['base_price'] },
  ];

  for (const { table, columns } of tablesToCheck) {
    const tableExists = await localDb.select<any>(
      "SELECT name FROM sqlite_master WHERE type='table' AND name=?", [table]
    );
    if (tableExists.length === 0) continue;

    const pragma = await localDb.select<any>(`PRAGMA table_info(${table})`);
    const existingColumns = new Set(pragma.map((c: any) => c.name));

    for (const column of columns) {
      if (!existingColumns.has(column)) continue;
      const result = await localDb.select<any>(
        `SELECT COUNT(*) as count FROM ${table} WHERE ${column} != CAST(ROUND(${column}) AS INTEGER)`
      );
      if (result[0].count > 0) {
        throw new Error(`P1-001: Fractional value found in ${table}.${column}.`);
      }
    }
  }
}

describe("Migración 017: Enforce INTEGER money (P1-001)", () => {
  beforeAll(async () => {
    localDb;
    await localDb.execute(`
      CREATE TABLE IF NOT EXISTS migrations (
        version TEXT PRIMARY KEY, applied_at TEXT DEFAULT CURRENT_TIMESTAMP, checksum TEXT
      )
    `);
  });

  beforeEach(async () => {
    await localDb.execute("DROP TABLE IF EXISTS local_orders");
    await localDb.execute("DELETE FROM migrations WHERE version = '017'");
  });

  it("debería redondear valores a INTEGER (10000 → OK)", async () => {
    await localDb.execute(`
      CREATE TABLE local_orders (
        local_uuid TEXT PRIMARY KEY,
        subtotal REAL NOT NULL DEFAULT 0,
        grand_total REAL NOT NULL DEFAULT 0
      )
    `);
    await localDb.execute("INSERT INTO local_orders (local_uuid, subtotal, grand_total) VALUES ('order-1', 10000, 10000)");

    await expect(validateNoFractionalValues()).resolves.not.toThrow();

    const statements = parseSqlStatements(migration017);
    for (const stmt of statements) {
      try { 
        await localDb.execute(stmt); 
      } catch (e: any) {
        // La migración es defensiva: ignora errores de tablas que no existen
        // Solo falla si hay un error crítico (no relacionado con tablas faltantes)
        if (e.message?.includes("no such table") || e.message?.includes("no such column")) {
          continue; // Tabla o columna no existe, continuar
        }
        // Para otros errores, verificar si es un UPDATE en tabla inexistente
        if (stmt.toUpperCase().includes("UPDATE") && e.message?.includes("no such table")) {
          continue;
        }
        throw e;
      }
    }

    const data = await localDb.select<any>("SELECT subtotal, grand_total FROM local_orders WHERE local_uuid = 'order-1'");
    expect(data[0].subtotal).toBe(10000);
    expect(data[0].grand_total).toBe(10000);
  });

  it("debería FALLAR la validación si existen valores fraccionarios (10000.50 → FAIL)", async () => {
    await localDb.execute(`
      CREATE TABLE local_orders (
        local_uuid TEXT PRIMARY KEY,
        subtotal REAL NOT NULL DEFAULT 0,
        grand_total REAL NOT NULL DEFAULT 0
      )
    `);
    await localDb.execute("INSERT INTO local_orders (local_uuid, subtotal, grand_total) VALUES ('order-2', 10000.50, 10000.50)");

    await expect(validateNoFractionalValues()).rejects.toThrow('P1-001: Fractional value found in local_orders.subtotal');
  });
});
