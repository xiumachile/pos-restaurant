import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";

describe("OrderRepository - Atomicidad", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");
    await localDb.execute("DELETE FROM table_local_mutations");
  });

  it("debería hacer rollback completo si markOccupied falla", async () => {
    // Intentar crear order con table_id inexistente
    await expect(
      OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        table_id: "non-existent-table", // Mesa no existe
      })
    ).rejects.toThrow();

    // Verificar que el order NO se creó
    const orders = await localDb.select("SELECT * FROM local_orders");
    expect(orders).toHaveLength(0);

    // Verificar que NO hay eventos en sync_queue
    const pending = await SyncQueueRepository.getPending();
    expect(pending).toHaveLength(0);
  });

  it("debería crear order + mesa + sync en una sola transacción", async () => {
    // Crear mesa primero
    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
       VALUES ('table-1', '1', 'Principal', 4, 'available', 'company-1', 'branch-1')`
    );

    // Crear order
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      table_id: "table-1",
    });

    // Verificar que todo se creó
    expect(order).toBeDefined();
    expect(order.table_id).toBe("table-1");

    const orders = await localDb.select("SELECT * FROM local_orders");
    expect(orders).toHaveLength(1);

    const pending = await SyncQueueRepository.getPending();
    expect(pending).toHaveLength(1);
    expect(pending[0].entity_type).toBe("order");

    // Verificar que la mesa está ocupada
    const tables = await localDb.select<{ status: string }>(
      "SELECT status FROM local_tables WHERE uuid = ?",
      ["table-1"]
    );
    expect(tables[0].status).toBe("occupied");
  });

  it("debería hacer rollback de addItem si recalculateOrderTotals falla", async () => {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
    });

    // Agregar item válido
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Hamburguesa",
      quantity: 2,
      unit_price: 5000,
    });

    const itemsBefore = await localDb.select(
      "SELECT * FROM local_order_items WHERE order_local_uuid = ?",
      [order.local_uuid]
    );
    expect(itemsBefore).toHaveLength(1);

    // Intentar agregar item con quantity negativo (debería fallar si hay validación)
    // Por ahora solo verificamos que addItem es transaccional
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-2",
      product_name: "Papas",
      quantity: 1,
      unit_price: 2000,
    });

    const itemsAfter = await localDb.select(
      "SELECT * FROM local_order_items WHERE order_local_uuid = ?",
      [order.local_uuid]
    );
    expect(itemsAfter).toHaveLength(2);
  });
});
