import { describe, it, expect, beforeAll, beforeEach, vi } from "vitest";

// 1. Mock de Tauri SQL
vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../../mocks/tauriSql");
  return { default: mod.default };
});

// 2. Importaciones reales
import { localDb } from "../../../db/localDb";
import migration017 from "../../../db/migrations/017_enforce_integer_money.sql?raw";

function parseSqlStatements(sql: string): string[] {
  return sql
    .split(';')
    .map(s => {
      // Eliminar líneas de comentario ANTES de verificar si el statement está vacío
      return s.split('\n')
        .filter(line => !line.trim().startsWith('--'))
        .join('\n')
        .trim();
    })
    .filter(s => s.length > 0);
}

// Función de validación de fraccionarios (se ejecuta ANTES de la migración)
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
      "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
      [table]
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
        throw new Error(`P1-001: Fractional value found in ${table}.${column}. Fix data before migrating to INTEGER.`);
      }
    }
  }
}

describe("Migración 017: Enforce INTEGER money (P1-001)", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await localDb.execute(`
      CREATE TABLE IF NOT EXISTS migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
        checksum TEXT
      )
    `);
  });

  beforeEach(async () => {
    await localDb.execute("DROP TABLE IF EXISTS local_orders");
    await localDb.execute("DROP TABLE IF EXISTS local_orders_int");
    await localDb.execute("DELETE FROM migrations WHERE version = '017'");
  });

  it("debería convertir exitosamente a INTEGER y PRAGMA debe mostrar INTEGER (10000 → OK)", async () => {
    // 1. Crear la tabla EXACTAMENTE como la espera la migración, pero con REAL
    await localDb.execute(`
      CREATE TABLE local_orders (
        local_uuid TEXT PRIMARY KEY,
        cloud_id TEXT,
        company_id TEXT NOT NULL,
        branch_id TEXT NOT NULL,
        terminal_id TEXT,
        table_id TEXT,
        order_number TEXT NOT NULL,
        order_type TEXT DEFAULT 'dine_in',
        status TEXT DEFAULT 'draft',
        subtotal REAL NOT NULL DEFAULT 0,
        discount_total REAL DEFAULT 0,
        tax_total REAL DEFAULT 0,
        tip_amount REAL DEFAULT 0,
        grand_total REAL NOT NULL DEFAULT 0,
        guest_count INTEGER DEFAULT 1,
        waiter_id TEXT,
        waiter_name TEXT,
        notes TEXT,
        idempotency_key TEXT UNIQUE NOT NULL,
        sync_status TEXT DEFAULT 'pending',
        sync_error TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);
    
    await localDb.execute(`
      INSERT INTO local_orders (local_uuid, company_id, branch_id, order_number, idempotency_key, subtotal, grand_total) 
      VALUES ('order-1', 'c1', 'b1', '001', 'key-1', 10000, 10000)
    `);

    // 2. Validar que no hay fraccionarios (debe pasar)
    await expect(validateNoFractionalValues()).resolves.not.toThrow();

    // 3. Ejecutar migración 017
    const statements = parseSqlStatements(migration017);
    
    for (const stmt of statements) {
      try {
        await localDb.execute(stmt);
      } catch (err: any) {
        // Solo ignorar errores de tablas que NO creamos en este test simplificado
        const isOtherTable = stmt.includes('local_order_items') || stmt.includes('local_payments') || 
          stmt.includes('local_bills') || stmt.includes('local_cash_sessions') || 
          stmt.includes('local_cash_movements') || stmt.includes('local_products');
        
        if (isOtherTable && (err.message?.includes("no such table") || err.message?.includes("no such column"))) {
          continue; // Tabla no existe en este test, saltar
        }
        
        throw err; // Error real, lanzar
      }
    }

    // 4. Verificar que la tabla existe y los valores se mantuvieron
    const data = await localDb.select<any>("SELECT subtotal, grand_total FROM local_orders WHERE local_uuid = 'order-1'");
    expect(data[0].subtotal).toBe(10000);
    expect(data[0].grand_total).toBe(10000);

    // 5. Verificar PRAGMA table_info para confirmar que el tipo es INTEGER
    const pragma = await localDb.select<any>("PRAGMA table_info(local_orders)");
    const subtotalCol = pragma.find((c: any) => c.name === 'subtotal');
    const grandTotalCol = pragma.find((c: any) => c.name === 'grand_total');
    
    expect(subtotalCol.type.toUpperCase()).toBe('INTEGER');
    expect(grandTotalCol.type.toUpperCase()).toBe('INTEGER');
  });

  it("debería FALLAR la validación si existen valores fraccionarios (10000.50 → FAIL)", async () => {
    await localDb.execute(`
      CREATE TABLE local_orders (
        local_uuid TEXT PRIMARY KEY,
        company_id TEXT NOT NULL,
        branch_id TEXT NOT NULL,
        order_number TEXT NOT NULL,
        subtotal REAL NOT NULL DEFAULT 0,
        discount_total REAL DEFAULT 0,
        tax_total REAL DEFAULT 0,
        tip_amount REAL DEFAULT 0,
        grand_total REAL NOT NULL DEFAULT 0,
        idempotency_key TEXT UNIQUE NOT NULL
      )
    `);
    
    await localDb.execute(`
      INSERT INTO local_orders (local_uuid, company_id, branch_id, order_number, idempotency_key, subtotal, grand_total) 
      VALUES ('order-2', 'c1', 'b1', '002', 'key-2', 10000.50, 10000.50)
    `);

    await expect(validateNoFractionalValues()).rejects.toThrow('P1-001: Fractional value found in local_orders.subtotal');
  });
});
