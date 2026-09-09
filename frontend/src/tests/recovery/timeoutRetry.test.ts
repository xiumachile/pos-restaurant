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
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

/**
 * ESCENARIO B: Timeout + retry
 * 
 * Backend procesa pago, respuesta se pierde (timeout).
 * Frontend reintenta con mismo idempotency_key.
 * Resultado: 1 payment en backend (no 2).
 */
describe("Recovery - B. Timeout + retry", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    // Limpieza COMPLETA de todas las tablas relacionadas
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

  it("debería reintentar con el mismo idempotency_key tras timeout", async () => {
    const order = await OrderRepository.create({
      company_id: "company-b1",
      branch_id: "branch-b1",
      order_type: "dine_in",
    });

    // Primer intento: timeout
    (apiClient.post as any).mockRejectedValueOnce(
      Object.assign(new Error("Request timeout"), {
        response: { status: 504, data: { message: "Gateway timeout" } },
      })
    );

    const stats1 = await syncEngine.processBatch();
    expect(stats1.failed).toBe(1);
    expect(stats1.success).toBe(0);

    const firstCall = (apiClient.post as any).mock.calls[0];
    const idempotencyKey = firstCall[2].headers["Idempotency-Key"];
    expect(idempotencyKey).toMatch(/^[a-f0-9-]{36}$/);

    // Resetear backoff manualmente (simular paso del tiempo)
    await localDb.execute(
      "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 minute')"
    );

    vi.clearAllMocks();

    // Segundo intento: éxito con mismo idempotency_key
    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-order-b1", order_number: "ORD-B001" } },
    });

    const stats2 = await syncEngine.processBatch();
    expect(stats2.success).toBe(1);

    const secondCall = (apiClient.post as any).mock.calls[0];
    const retryIdempotencyKey = secondCall[2].headers["Idempotency-Key"];
    expect(retryIdempotencyKey).toBe(idempotencyKey);
  });

  it("debería mantener order pendiente tras timeout (con backoff)", async () => {
    const order = await OrderRepository.create({
      company_id: "company-b2",
      branch_id: "branch-b2",
      order_type: "dine_in",
    });

    // Verificar estado inicial: pendiente
    let localOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(localOrder).toBeDefined();
    expect(localOrder?.sync_status).toBe("pending");

    // Primer intento: timeout
    (apiClient.post as any).mockRejectedValueOnce(new Error("Timeout"));

    await syncEngine.processBatch();

    // Verificar: order sigue pendiente
    localOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(localOrder?.sync_status).toBe("pending");
    expect(localOrder?.sync_status).not.toBe("synced");

    // Verificar: item sigue en sync_queue (aunque en backoff)
    // Usar query directo que NO filtra por next_retry_at
    const queueItems = await localDb.select(
      "SELECT * FROM sync_queue WHERE entity_local_uuid = ? AND sync_status != 'synced'",
      [order.local_uuid]
    );
    expect(queueItems.length).toBeGreaterThanOrEqual(1);

    // Verificar: item tiene attempts=1 y next_retry_at en futuro
    const item = queueItems[0];
    expect(item.attempts).toBe(1);
    expect(item.next_retry_at).toBeDefined();

    // Verificar: primer intento usó idempotency_key válido
    const firstCall = (apiClient.post as any).mock.calls[0];
    const idempotencyKey = firstCall[2].headers["Idempotency-Key"];
    expect(idempotencyKey).toMatch(/^[a-f0-9-]{36}$/);
  });

  it("debería recuperar tras timeout + éxito (resetear backoff)", async () => {
    const order = await OrderRepository.create({
      company_id: "company-b3",
      branch_id: "branch-b3",
      order_type: "dine_in",
    });

    // Verificar estado inicial
    let localOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(localOrder?.sync_status).toBe("pending");

    // Timeout
    (apiClient.post as any).mockRejectedValueOnce(
      Object.assign(new Error("Timeout"), {
        response: { status: 504 },
      })
    );

    await syncEngine.processBatch();

    localOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(localOrder?.sync_status).toBe("pending");

    // Resetear backoff manualmente (simular paso del tiempo)
    await localDb.execute(
      "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 minute')"
    );

    // Éxito en reintento
    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-order-b3" } },
    });

    await syncEngine.processBatch();

    localOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(localOrder?.sync_status).toBe("synced");
    expect(localOrder?.cloud_id).toBe("cloud-order-b3");
  });
});
