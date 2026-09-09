import { describe, it, expect, beforeAll, beforeEach, vi, afterEach } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("../../services/apiClient", () => ({
  apiClient: {
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    get: vi.fn(),
  },
}));

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

/**
 * ESCENARIO D: Offline → Online (reconnection)
 */
describe("Recovery - D. Offline → Online (reconnection)", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM offline_events");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM sync_state");
    
    vi.clearAllMocks();
    vi.resetAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.resetAllMocks();
  });

  it("debería sincronizar múltiples orders offline tras reconexión", async () => {
    const order1 = await OrderRepository.create({
      company_id: "company-d1",
      branch_id: "branch-d1",
      order_type: "dine_in",
    });

    const order2 = await OrderRepository.create({
      company_id: "company-d1",
      branch_id: "branch-d1",
      order_type: "dine_in",
    });

    const order3 = await OrderRepository.create({
      company_id: "company-d1",
      branch_id: "branch-d1",
      order_type: "dine_in",
    });

    const pending = await SyncQueueRepository.getPending(10);
    expect(pending.length).toBeGreaterThanOrEqual(3);

    (apiClient.post as any).mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-d1-1" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-d1-2" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-d1-3" } } });

    const stats = await syncEngine.processBatch();
    expect(stats.success).toBeGreaterThanOrEqual(3);

    for (const o of [order1, order2, order3]) {
      const synced = await OrderRepository.findByLocalUuid(o.local_uuid);
      expect(synced?.sync_status).toBe("synced");
      expect(synced?.cloud_id).toBeTruthy();
    }
  });

  it("debería sincronizar order y mantener payment en cola para siguiente batch", async () => {
    const order = await OrderRepository.create({
      company_id: "company-d2",
      branch_id: "branch-d2",
      order_type: "dine_in",
    });

    const payment = await PaymentRepository.create({
      company_id: "company-d2",
      branch_id: "branch-d2",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 45000,
    });

    // Batch 1: solo order se sincroniza (payment falla porque order no tiene cloud_id aún)
    (apiClient.post as any).mockImplementation((url: string) => {
      if (url === "/orders") {
        return Promise.resolve({ data: { data: { uuid: "cloud-order-d2" } } });
      }
      // Payment falla la primera vez
      return Promise.reject(new Error("Order not synced yet"));
    });

    await syncEngine.processBatch();

    // Order debe estar sincronizada
    const syncedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(syncedOrder?.sync_status).toBe("synced");
    expect(syncedOrder?.cloud_id).toBe("cloud-order-d2");

    // Payment debe estar en cola (aunque con backoff o failed)
    const pending = await localDb.select(
      "SELECT * FROM sync_queue WHERE entity_type = 'payment' AND entity_local_uuid = ?",
      [payment.local_uuid]
    );
    expect(pending.length).toBe(1);
    expect(pending[0].attempts).toBeGreaterThanOrEqual(1);
  });

  it("debería mantener integridad de datos después de múltiples operaciones offline", async () => {
    // Crear 3 orders con payments cada una
    const operations = [];
    for (let i = 1; i <= 3; i++) {
      const order = await OrderRepository.create({
        company_id: "company-d3",
        branch_id: "branch-d3",
        order_type: "dine_in",
      });
      
      const payment = await PaymentRepository.create({
        company_id: "company-d3",
        branch_id: "branch-d3",
        order_local_uuid: order.local_uuid,
        payment_method: i % 2 === 0 ? "cash" : "card",
        amount: 10000 * i,
      });
      
      operations.push({ order, payment });
    }

    // Verificar: 6 items en cola (3 orders + 3 payments)
    const pending = await SyncQueueRepository.getPending(10);
    expect(pending.length).toBeGreaterThanOrEqual(6);

    // Verificar: integridad referencial (cada payment apunta a su order)
    for (const op of operations) {
      const payment = await PaymentRepository.findByLocalUuid(op.payment.local_uuid);
      expect(payment?.order_local_uuid).toBe(op.order.local_uuid);
    }

    // Sincronizar solo orders
    (apiClient.post as any).mockImplementation((url: string) => {
      if (url === "/orders") {
        return Promise.resolve({ data: { data: { uuid: `cloud-order-${Date.now()}` } } });
      }
      return Promise.reject(new Error("Not ready"));
    });

    const stats = await syncEngine.processBatch();
    expect(stats.success).toBeGreaterThanOrEqual(3); // Al menos las 3 orders

    // Verificar: todas las orders sincronizadas
    for (const op of operations) {
      const order = await OrderRepository.findByLocalUuid(op.order.local_uuid);
      expect(order?.sync_status).toBe("synced");
      expect(order?.cloud_id).toBeTruthy();
    }
  });

  it("debería mantener idempotency_keys consistentes entre offline y online", async () => {
    const order = await OrderRepository.create({
      company_id: "company-d4",
      branch_id: "branch-d4",
      order_type: "dine_in",
    });

    const pendingBefore = await SyncQueueRepository.getPending(10);
    const orderItem = pendingBefore.find(i => i.entity_local_uuid === order.local_uuid);
    const offlineIdempotencyKey = JSON.parse(orderItem!.payload).idempotency_key;

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-order-d4" } },
    });

    await syncEngine.processBatch();

    const call = (apiClient.post as any).mock.calls[0];
    const onlineIdempotencyKey = call[2].headers["Idempotency-Key"];
    
    expect(onlineIdempotencyKey).toBe(offlineIdempotencyKey);
  });
});
