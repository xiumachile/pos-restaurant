import { describe, it, expect, beforeAll, afterAll, vi } from "vitest";

// ============================================================
// MOCK INLINE de @tauri-apps/plugin-sql
// Se importa dinámicamente dentro del factory para evitar
// el error "Cannot access 'MockDatabase' before initialization"
// ============================================================
vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

// Importar DESPUÉS de registrar el mock
import { localDb } from "../../db/localDb";

// ============================================================
// TESTS
// ============================================================

describe("LocalDatabase (unit tests con mock)", () => {
  beforeAll(async () => {
    await localDb.getConnection();
  });

  afterAll(async () => {
    await localDb.close();
  });

  it("debería conectar a la base de datos", async () => {
    const result = await localDb.select<{ ok: number }>("SELECT 1 as ok");
    expect(result).toHaveLength(1);
    expect(result[0].ok).toBe(1);
  });

  it("debería reportar WAL mode habilitado", async () => {
    const result = await localDb.select<{ journal_mode: string }>(
      "PRAGMA journal_mode"
    );
    expect(result[0].journal_mode).toBe("wal");
  });

  it("debería permitir insertar y seleccionar datos", async () => {
    await localDb.execute(
      "CREATE TABLE IF NOT EXISTS test_items (local_uuid TEXT PRIMARY KEY, name TEXT, value REAL)"
    );

    const testUuid = "test-" + crypto.randomUUID();

    await localDb.execute(
      "INSERT INTO test_items (local_uuid, name, value) VALUES (?, ?, ?)",
      [testUuid, "Test Item", 42.5]
    );

    const result = await localDb.select<any>(
      "SELECT * FROM test_items WHERE local_uuid = ?",
      [testUuid]
    );

    expect(result).toHaveLength(1);
    expect(result[0].name).toBe("Test Item");
    expect(result[0].value).toBe(42.5);

    await localDb.execute("DELETE FROM test_items WHERE local_uuid = ?", [
      testUuid,
    ]);
  });

  it("debería soportar transacciones (commit)", async () => {
    await localDb.execute(
      "CREATE TABLE IF NOT EXISTS tx_test (local_uuid TEXT PRIMARY KEY)"
    );

    const testUuid = "tx-" + crypto.randomUUID();

    await localDb.transaction(async (db) => {
      await db.execute("INSERT INTO tx_test (local_uuid) VALUES (?)", [
        testUuid,
      ]);
    });

    const result = await localDb.select<any>("SELECT * FROM tx_test");
    expect(result.some((r) => r.local_uuid === testUuid)).toBe(true);
  });
});

describe("Esquema de migración 001", () => {
  it("debería tener la estructura SQL válida", async () => {
    const migration = (
      await import("../../db/migrations/001_initial.sql?raw")
    ).default;

    expect(migration).toContain("CREATE TABLE");
    expect(migration).toContain("local_orders");
    expect(migration).toContain("local_order_items");
    expect(migration).toContain("local_payments");
    expect(migration).toContain("local_cash_sessions");
    expect(migration).toContain("sync_queue");
    expect(migration).toContain("sync_state");
    expect(migration).toContain("local_uuid TEXT PRIMARY KEY");
    expect(migration).toContain("idempotency_key");
    expect(migration).toContain("sync_status");
  });
});
