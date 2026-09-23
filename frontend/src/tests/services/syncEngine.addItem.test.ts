import { describe, it, expect, beforeAll, beforeEach, vi } from "vitest";

// 1. Mocks de Tauri y API (deben ir antes de las importaciones)
vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("../../services/syncApi", () => ({
  syncApi: {
    addOrderItem: vi.fn().mockResolvedValue({ uuid: "cloud-item-1" }),
    updateOrder: vi.fn().mockResolvedValue({ uuid: "cloud-order-1" }),
    removeOrderItem: vi.fn().mockResolvedValue({}),
    createOrder: vi.fn().mockResolvedValue({ uuid: "cloud-order-123" }),
  },
}));

// 2. Importaciones reales
import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { syncApi } from "../../services/syncApi";
import { mockAuthContext } from "../testUtils";

describe("SyncEngine - order/add_item fix (P0-001)", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    vi.resetAllMocks();
    mockAuthContext({ companyId: "c1", branchId: "b1" });
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
  });

  it("debería rutear add_item a POST /orders/{uuid}/items (NO a PUT)", async () => {
    // 1. Crear orden (esto encola automáticamente un evento 'create')
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_type: "dine_in",
    });
    
    // 2. Simular que la orden YA fue sincronizada previamente (tiene cloud_id)
    await localDb.execute(
      "UPDATE local_orders SET cloud_id = ?, sync_status = 'synced' WHERE local_uuid = ?",
      ["cloud-order-123", order.local_uuid]
    );

    // 3. Transformar el evento encolado de 'create' a 'update' con el payload de add_item
    //    NOTA: sync_queue NO tiene columna idempotency_key, solo payload JSON
    await localDb.execute(
      `UPDATE sync_queue 
       SET action = 'update', 
           payload = ?
       WHERE entity_local_uuid = ? AND action = 'create'`,
      [
        JSON.stringify({
          action: "add_item",
          item: {
            local_uuid: "item-local-1",
            product_id: "product-uuid-1",
            quantity: 2,
            unit_price: 5000,
            notes: "Sin cebolla",
          },
          idempotency_key: "idem-key-1",
        }),
        order.local_uuid,
      ]
    );

    // 4. Ejecutar el batch de sincronización
    const stats = await syncEngine.processBatch();

    // 5. Verificar que se procesó 1 evento exitosamente
    expect(stats.processed).toBe(1);
    expect(stats.success).toBe(1);

    // 6. Verificar que se llamó a addOrderItem con los argumentos correctos
    expect(syncApi.addOrderItem).toHaveBeenCalledTimes(1);
    expect(syncApi.addOrderItem).toHaveBeenCalledWith(
      "cloud-order-123",
      expect.objectContaining({
        product_uuid: "product-uuid-1",
        quantity: 2,
        unit_price: 5000,
        notes: "Sin cebolla",
        idempotency_key: "item-local-1", // CRÍTICO: Validación de idempotencia
      })
    );

    // 7. Verificar que NO se llamó a updateOrder (el bug original)
    expect(syncApi.updateOrder).not.toHaveBeenCalled();
  });

  it("debería rutear update normal a PUT /orders/{uuid}", async () => {
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_type: "dine_in",
    });
    
    await localDb.execute(
      "UPDATE local_orders SET cloud_id = ?, sync_status = 'synced' WHERE local_uuid = ?",
      ["cloud-order-123", order.local_uuid]
    );

    await localDb.execute(
      `UPDATE sync_queue 
       SET action = 'update', 
           payload = ?
       WHERE entity_local_uuid = ? AND action = 'create'`,
      [
        JSON.stringify({
          status: "confirmed",
          notes: "Mesa 5",
          idempotency_key: "idem-key-2",
        }),
        order.local_uuid,
      ]
    );

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(1);
    expect(stats.success).toBe(1);

    expect(syncApi.updateOrder).toHaveBeenCalledTimes(1);
    expect(syncApi.addOrderItem).not.toHaveBeenCalled();
  });

  it("debería rutear remove_item a DELETE /orders/{uuid}/items/{itemUuid}", async () => {
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_type: "dine_in",
    });
    
    await localDb.execute(
      "UPDATE local_orders SET cloud_id = ?, sync_status = 'synced' WHERE local_uuid = ?",
      ["cloud-order-123", order.local_uuid]
    );

    await localDb.execute(
      `UPDATE sync_queue 
       SET action = 'update', 
           payload = ?
       WHERE entity_local_uuid = ? AND action = 'create'`,
      [
        JSON.stringify({
          action: "remove_item",
          item_uuid: "cloud-item-to-remove",
          idempotency_key: "idem-key-3",
        }),
        order.local_uuid,
      ]
    );

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(1);
    expect(stats.success).toBe(1);

    expect(syncApi.removeOrderItem).toHaveBeenCalledWith(
      "cloud-order-123",
      "cloud-item-to-remove"
    );
    expect(syncApi.updateOrder).not.toHaveBeenCalled();
    expect(syncApi.addOrderItem).not.toHaveBeenCalled();
  });
});
