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
import { CashSessionRepository } from "../../db/repositories/CashSessionRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

/**
 * ESCENARIO E: Dos terminales simultáneos
 * 
 * Terminal A y Terminal B (misma branch) crean órdenes y pagos
 * al mismo tiempo. Cada uno tiene su propia cash_session.
 * 
 * Mecanismo de defensa:
 * - Cada terminal tiene terminal_id único
 * - CashSessionRepository filtra por company+branch+terminal+user
 * - Cada payment tiene idempotency_key único
 * - Backend aísla sesiones por terminal
 */
describe("Recovery - E. Dos terminales simultáneos", () => {
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
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");
    await localDb.execute("DELETE FROM sync_state");
    
    vi.clearAllMocks();
    vi.resetAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.resetAllMocks();
  });

  it("debería permitir dos sesiones de caja diferentes en la misma branch", async () => {
    const sessionA = await CashSessionRepository.create({
      company_id: "company-e1",
      branch_id: "branch-e1",
      terminal_id: "terminal-A",
      user_id: "user-A",
      user_name: "Cajero A",
      opening_amount: 50000,
    });

    const sessionB = await CashSessionRepository.create({
      company_id: "company-e1",
      branch_id: "branch-e1",
      terminal_id: "terminal-B",
      user_id: "user-B",
      user_name: "Cajero B",
      opening_amount: 30000,
    });

    // Cada terminal ve solo su propia sesión
    const activeA = await CashSessionRepository.findActive(
      "company-e1", "branch-e1", "user-A", "terminal-A"
    );
    const activeB = await CashSessionRepository.findActive(
      "company-e1", "branch-e1", "user-B", "terminal-B"
    );

    expect(activeA?.local_uuid).toBe(sessionA.local_uuid);
    expect(activeA?.opening_amount).toBe(50000);
    
    expect(activeB?.local_uuid).toBe(sessionB.local_uuid);
    expect(activeB?.opening_amount).toBe(30000);

    // Pero terminal A NO ve la sesión de B
    const wrongView = await CashSessionRepository.findActive(
      "company-e1", "branch-e1", "user-A", "terminal-B"
    );
    expect(wrongView).toBeNull();
  });

  it("debería permitir órdenes simultáneas desde dos terminales", async () => {
    // Terminal A crea una orden
    const orderA = await OrderRepository.create({
      company_id: "company-e2",
      branch_id: "branch-e2",
      order_type: "dine_in",
    });

    // Terminal B crea una orden simultáneamente
    const orderB = await OrderRepository.create({
      company_id: "company-e2",
      branch_id: "branch-e2",
      order_type: "dine_in",
    });

    // Ambas órdenes existen
    expect(orderA.local_uuid).not.toBe(orderB.local_uuid);
    
    const restoredA = await OrderRepository.findByLocalUuid(orderA.local_uuid);
    const restoredB = await OrderRepository.findByLocalUuid(orderB.local_uuid);
    
    expect(restoredA).toBeDefined();
    expect(restoredB).toBeDefined();

    // Ambas en cola de sync
    const pending = await SyncQueueRepository.getPending(10);
    const orderItems = pending.filter(i => i.entity_type === "order");
    expect(orderItems.length).toBeGreaterThanOrEqual(2);

    // Cada una tiene idempotency_key único
    const keys = orderItems.map(i => JSON.parse(i.payload).idempotency_key);
    expect(keys[0]).not.toBe(keys[1]);
  });

  it("debería permitir pagos simultáneos desde dos terminales", async () => {
    const orderA = await OrderRepository.create({
      company_id: "company-e3",
      branch_id: "branch-e3",
      order_type: "dine_in",
    });

    const orderB = await OrderRepository.create({
      company_id: "company-e3",
      branch_id: "branch-e3",
      order_type: "dine_in",
    });

    // Terminal A paga en efectivo
    const paymentA = await PaymentRepository.create({
      company_id: "company-e3",
      branch_id: "branch-e3",
      order_local_uuid: orderA.local_uuid,
      payment_method: "cash",
      amount: 25000,
    });

    // Terminal B paga con tarjeta simultáneamente
    const paymentB = await PaymentRepository.create({
      company_id: "company-e3",
      branch_id: "branch-e3",
      order_local_uuid: orderB.local_uuid,
      payment_method: "card",
      amount: 35000,
    });

    // Cada payment tiene idempotency_key único
    expect(paymentA.idempotency_key).not.toBe(paymentB.idempotency_key);

    // Ambos en cola
    const pending = await SyncQueueRepository.getPending(10);
    const paymentItems = pending.filter(i => i.entity_type === "payment");
    expect(paymentItems.length).toBeGreaterThanOrEqual(2);
  });

  it("debería sincronizar ambas terminales sin conflictos", async () => {
    // Crear 2 orders simultáneas
    const orderA = await OrderRepository.create({
      company_id: "company-e4",
      branch_id: "branch-e4",
      order_type: "dine_in",
    });

    const orderB = await OrderRepository.create({
      company_id: "company-e4",
      branch_id: "branch-e4",
      order_type: "dine_in",
    });

    // Configurar respuestas del backend
    (apiClient.post as any)
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-e4-A" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-order-e4-B" } } });

    await syncEngine.processBatch();

    // Ambas sincronizadas exitosamente
    const syncedA = await OrderRepository.findByLocalUuid(orderA.local_uuid);
    const syncedB = await OrderRepository.findByLocalUuid(orderB.local_uuid);

    expect(syncedA?.sync_status).toBe("synced");
    expect(syncedB?.sync_status).toBe("synced");
    expect(syncedA?.cloud_id).toBeTruthy();
    expect(syncedB?.cloud_id).toBeTruthy();
    expect(syncedA?.cloud_id).not.toBe(syncedB?.cloud_id);
  });
});
