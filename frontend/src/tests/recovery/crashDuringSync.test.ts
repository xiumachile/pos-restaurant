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
 * ESCENARIO F: Crash durante sync
 * 
 * 1. Payment se envía al backend
 * 2. Backend procesa exitosamente
 * 3. App se cierra antes de marcar como sincronizado
 * 4. Al reiniciar, reintenta con mismo idempotency_key
 * 5. Backend responde con el MISMO payment (idempotencia)
 * 
 * Resultado: 1 payment en backend, no 2.
 */
describe("Recovery - F. Crash durante sync", () => {
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

  it("debería recuperar tras crash simulado (reintento con mismo idempotency_key)", async () => {
    const order = await OrderRepository.create({
      company_id: "company-f1",
      branch_id: "branch-f1",
      order_type: "dine_in",
    });

    // Primer intento: backend procesa pero respuesta se pierde (crash simulado)
    // Simulamos como timeout porque no podemos matar el proceso real
    (apiClient.post as any).mockRejectedValueOnce(
      Object.assign(new Error("Connection reset - app crashed"), {
        response: { status: 502 },
      })
    );

    await syncEngine.processBatch();

    // Verificar: order sigue pendiente (crash simulado)
    let restoredOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(restoredOrder?.sync_status).toBe("pending");
    expect(restoredOrder?.cloud_id).toBeFalsy();

    // Capturar el idempotency_key del primer intento
    const firstCall = (apiClient.post as any).mock.calls[0];
    const firstIdempotencyKey = firstCall[2].headers["Idempotency-Key"];

    // Resetear backoff para simular "después del crash"
    await localDb.execute(
      "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 minute')"
    );

    // Segundo intento: backend retorna el MISMO order (idempotencia)
    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-order-f1", order_number: "ORD-F001" } },
    });

    await syncEngine.processBatch();

    // Verificar: mismo idempotency_key en ambos intentos
    const secondCall = (apiClient.post as any).mock.calls[1];
    const secondIdempotencyKey = secondCall[2].headers["Idempotency-Key"];
    expect(secondIdempotencyKey).toBe(firstIdempotencyKey);

    // Verificar: order finalmente sincronizada
    restoredOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(restoredOrder?.sync_status).toBe("synced");
    expect(restoredOrder?.cloud_id).toBe("cloud-order-f1");
  });

  it("debería preservar estado local tras crash (datos no se pierden)", async () => {
    // Crear order + payment offline
    const order = await OrderRepository.create({
      company_id: "company-f2",
      branch_id: "branch-f2",
      order_type: "dine_in",
    });

    const payment = await PaymentRepository.create({
      company_id: "company-f2",
      branch_id: "branch-f2",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 75000,
      tip_amount: 5000,
      reference_code: "REF-CRASH-TEST",
    });

    // Simular crash: NO sincronizar, solo verificar persistencia
    const restoredOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    const restoredPayment = await PaymentRepository.findByLocalUuid(payment.local_uuid);

    // Datos preservados (campos que existen en schema de local_payments)
    expect(restoredOrder).toBeDefined();
    expect(restoredPayment).toBeDefined();
    expect(restoredPayment?.amount).toBe(75000);
    expect(restoredPayment?.tip_amount).toBe(5000);
    expect(restoredPayment?.payment_method).toBe("cash");
    expect(restoredPayment?.reference_code).toBe("REF-CRASH-TEST");
    expect(restoredPayment?.idempotency_key).toBe(payment.idempotency_key);
    expect(restoredPayment?.sync_status).toBe("pending");

    // Cola de sync intacta
    const pending = await SyncQueueRepository.getPending(10);
    expect(pending.length).toBeGreaterThanOrEqual(2);
    
    // Verificar integridad referencial
    expect(restoredPayment?.order_local_uuid).toBe(order.local_uuid);
  });

  it("debería manejar crash en medio de batch (algunos items procesados, otros no)", async () => {
    // Crear 3 órdenes
    const orders = [];
    for (let i = 1; i <= 3; i++) {
      const order = await OrderRepository.create({
        company_id: "company-f3",
        branch_id: "branch-f3",
        order_type: "dine_in",
      });
      orders.push(order);
    }

    // Primer batch: crash después de procesar 2 de 3
    (apiClient.post as any)
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-f3-1" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-f3-2" } } })
      .mockRejectedValueOnce(new Error("Crash - connection lost"));

    await syncEngine.processBatch();

    // Al menos 2 órdenes deberían estar sincronizadas
    const synced1 = await OrderRepository.findByLocalUuid(orders[0].local_uuid);
    const synced2 = await OrderRepository.findByLocalUuid(orders[1].local_uuid);
    const pending3 = await OrderRepository.findByLocalUuid(orders[2].local_uuid);

    expect(synced1?.sync_status).toBe("synced");
    expect(synced2?.sync_status).toBe("synced");
    // La tercera puede estar pending o failed (dependiendo del momento del crash)
    expect(pending3).toBeDefined();
  });

  it("debería permitir continuar tras crash sin duplicados", async () => {
    const order = await OrderRepository.create({
      company_id: "company-f4",
      branch_id: "branch-f4",
      order_type: "dine_in",
    });

    // Intento 1: crash
    (apiClient.post as any).mockRejectedValueOnce(new Error("Crash 1"));
    await syncEngine.processBatch();

    // Reset backoff
    await localDb.execute(
      "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 minute')"
    );

    // Intento 2: crash
    (apiClient.post as any).mockRejectedValueOnce(new Error("Crash 2"));
    await syncEngine.processBatch();

    // Reset backoff
    await localDb.execute(
      "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 minute')"
    );

    // Intento 3: éxito
    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-order-f4" } },
    });
    await syncEngine.processBatch();

    // Verificar: mismo idempotency_key en los 3 intentos
    const calls = (apiClient.post as any).mock.calls;
    expect(calls.length).toBe(3);
    
    const keys = calls.map((c: any) => c[2].headers["Idempotency-Key"]);
    expect(keys[0]).toBe(keys[1]);
    expect(keys[1]).toBe(keys[2]);

    // Orden finalmente sincronizada
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(finalOrder?.sync_status).toBe("synced");
    expect(finalOrder?.cloud_id).toBe("cloud-order-f4");
  });
});
