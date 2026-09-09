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
import { EventStore } from "../../db/repositories/EventStore";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

/**
 * ESCENARIO C: Kill/Restart App
 * 
 * Usuario crea pago offline, cierra la app sin sincronizar,
 * la reinicia. El pago debe persistir en SQLite y sincronizarse
 * en el siguiente arranque.
 * 
 * Mecanismo de defensa:
 * - SQLite persiste en disco (WAL mode)
 * - SyncQueue mantiene items pendientes entre reinicios
 * - Event Store mantiene auditoría inmutable
 */
describe("Recovery - C. Kill/Restart App", () => {
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

  it("debería persistir order pendiente después de 'reiniciar la app'", async () => {
    // FASE 1: Crear order offline
    const order = await OrderRepository.create({
      company_id: "company-c1",
      branch_id: "branch-c1",
      order_type: "dine_in",
    });

    // Simular que la app se cierra sin sincronizar (no llamar processBatch)
    // "Reiniciar" la app: simplemente volver a leer los datos
    const restoredOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    
    expect(restoredOrder).toBeDefined();
    expect(restoredOrder?.local_uuid).toBe(order.local_uuid);
    expect(restoredOrder?.sync_status).toBe("pending");
    expect(restoredOrder?.cloud_id).toBeFalsy();
  });

  it("debería persistir payment pendiente después de 'reiniciar la app'", async () => {
    const order = await OrderRepository.create({
      company_id: "company-c2",
      branch_id: "branch-c2",
      order_type: "dine_in",
    });

    // Crear payment offline
    const payment = await PaymentRepository.create({
      company_id: "company-c2",
      branch_id: "branch-c2",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 25000,
      tip_amount: 2000,
      notes: "Pago antes del cierre de app",
    });

    // "Reiniciar" la app: volver a leer
    const restoredPayment = await PaymentRepository.findByLocalUuid(payment.local_uuid);
    
    expect(restoredPayment).toBeDefined();
    expect(restoredPayment?.local_uuid).toBe(payment.local_uuid);
    expect(restoredPayment?.amount).toBe(25000);
    expect(restoredPayment?.tip_amount).toBe(2000);
    expect(restoredPayment?.sync_status).toBe("pending");
    expect(restoredPayment?.idempotency_key).toBe(payment.idempotency_key); // Mismo idempotency_key
  });

  it("debería persistir SyncQueue entre 'reinicios' y sincronizar después", async () => {
    const order = await OrderRepository.create({
      company_id: "company-c3",
      branch_id: "branch-c3",
      order_type: "dine_in",
    });

    await PaymentRepository.create({
      company_id: "company-c3",
      branch_id: "branch-c3",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 18000,
    });

    // Verificar que items están en cola
    const pendingBefore = await SyncQueueRepository.getPending(10);
    expect(pendingBefore.length).toBeGreaterThanOrEqual(2); // order + payment

    // "Reiniciar" la app: leer cola desde SQLite
    const pendingAfter = await SyncQueueRepository.getPending(10);
    expect(pendingAfter.length).toBe(pendingBefore.length);
    
    // Los idempotency_keys deben ser los mismos
    const keysBefore = pendingBefore.map(i => JSON.parse(i.payload).idempotency_key).sort();
    const keysAfter = pendingAfter.map(i => JSON.parse(i.payload).idempotency_key).sort();
    expect(keysAfter).toEqual(keysBefore);

    // Sincronizar después del "reinicio"
    (apiClient.post as any)
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-c3" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-payment-c3" } } });

    const stats = await syncEngine.processBatch();
    expect(stats.success).toBeGreaterThanOrEqual(1);
  });

  it("debería persistir eventos de EventStore entre 'reinicios'", async () => {
    const order = await OrderRepository.create({
      company_id: "company-c4",
      branch_id: "branch-c4",
      order_type: "dine_in",
    });

    const payment = await PaymentRepository.create({
      company_id: "company-c4",
      branch_id: "branch-c4",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 30000,
    });

    // Verificar trail de eventos después del "reinicio"
    const events = await EventStore.findByEntity("payment", payment.local_uuid);
    expect(events).toHaveLength(1);
    expect(events[0].event_type).toBe("CREATE_PAYMENT");

    const payload = JSON.parse(events[0].payload);
    expect(payload.amount).toBe(30000);
    expect(payload.payment_method).toBe("cash");
  });

  it("debería mantener integridad referencial después del 'reinicio'", async () => {
    const order = await OrderRepository.create({
      company_id: "company-c5",
      branch_id: "branch-c5",
      order_type: "dine_in",
    });

    const p1 = await PaymentRepository.create({
      company_id: "company-c5",
      branch_id: "branch-c5",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 10000,
    });

    const p2 = await PaymentRepository.create({
      company_id: "company-c5",
      branch_id: "branch-c5",
      order_local_uuid: order.local_uuid,
      payment_method: "card",
      amount: 5000,
    });

    // "Reiniciar" y verificar integridad
    const restoredOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    const restoredPayments = await PaymentRepository.findByOrderLocalUuid(order.local_uuid);

    expect(restoredOrder).toBeDefined();
    expect(restoredPayments).toHaveLength(2);
    expect(restoredPayments.map(p => p.local_uuid).sort())
      .toEqual([p1.local_uuid, p2.local_uuid].sort());
    
    // Total pagado correcto
    const totalPaid = restoredPayments.reduce((sum, p) => sum + p.amount, 0);
    expect(totalPaid).toBe(15000);
  });
});
