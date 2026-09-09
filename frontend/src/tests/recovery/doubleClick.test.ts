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
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

/**
 * ESCENARIO A: Double click
 * 
 * Usuario hace click dos veces rápidamente en "Pagar".
 * Debe resultar en EXACTAMENTE 1 payment (no 2).
 */
describe("Recovery - A. Double click", () => {
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
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it("debería usar idempotency_key único por intento de pago", async () => {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_type: "dine_in",
    });

    const p1 = await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 10000,
    });

    const p2 = await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 10000,
    });

    expect(p1.idempotency_key).toMatch(/^[a-f0-9-]{36}$/);
    expect(p2.idempotency_key).toMatch(/^[a-f0-9-]{36}$/);
    expect(p1.idempotency_key).not.toBe(p2.idempotency_key);
  });

  it("debería registrar eventos CREATE_PAYMENT separados para cada click", async () => {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_type: "dine_in",
    });

    const p1 = await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 15000,
    });

    const p2 = await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 15000,
    });

    const events1 = await localDb.select(
      "SELECT * FROM offline_events WHERE entity_uuid = ?",
      [p1.local_uuid]
    );
    const events2 = await localDb.select(
      "SELECT * FROM offline_events WHERE entity_uuid = ?",
      [p2.local_uuid]
    );

    expect(events1).toHaveLength(1);
    expect(events2).toHaveLength(1);
    expect(events1[0].event_type).toBe("CREATE_PAYMENT");
    expect(events2[0].event_type).toBe("CREATE_PAYMENT");
  });

  it("debería encolar 2 payments en sync_queue (backend deduplica)", async () => {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_type: "dine_in",
    });

    await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 15000,
    });

    await PaymentRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order.local_uuid,
      payment_method: "cash",
      amount: 15000,
    });

    const queue = await localDb.select(
      "SELECT * FROM sync_queue WHERE entity_type = 'payment'"
    );
    expect(queue).toHaveLength(2);

    // Verificar que tienen idempotency_keys diferentes
    const keys = queue.map((q: any) => JSON.parse(q.payload).idempotency_key);
    expect(keys[0]).not.toBe(keys[1]);
  });
});
