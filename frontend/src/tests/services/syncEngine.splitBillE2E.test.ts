import { describe, it, expect, beforeAll, beforeEach, vi } from "vitest";
import { v4 as uuidv4 } from "uuid";

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
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";
import { mockAuthContext } from "../testUtils";

/**
 * Helper: Crea order directamente en DB sin encolar a sync_queue.
 * Esto aísla los tests (evita que processOrder consuma mocks).
 */
async function createOrderWithoutEnqueue(opts: {
  company_id: string;
  branch_id: string;
  cloud_id?: string;
}): Promise<{ local_uuid: string; cloud_id: string | null }> {
  const local_uuid = uuidv4();
  const idempotency_key = uuidv4();
  await localDb.execute(
    `INSERT INTO local_orders (
      local_uuid, company_id, branch_id, order_number, order_type, status,
      subtotal, grand_total, sync_status, cloud_id, idempotency_key
    ) VALUES (?, ?, ?, ?, 'dine_in', 'draft', 0, 0, ?, ?, ?)`,
    [
      local_uuid,
      opts.company_id,
      opts.branch_id,
      `ORD-SPLIT-${Date.now()}-${Math.random()}`,
      opts.cloud_id ? "synced" : "pending",
      opts.cloud_id || null,
      idempotency_key,
    ]
  );
  return { local_uuid, cloud_id: opts.cloud_id || null };
}

/**
 * Helper: Crea bill directamente en DB sin encolar a sync_queue.
 */
async function createBillWithoutEnqueue(opts: {
  company_id: string;
  branch_id: string;
  order_local_uuid: string;
  bill_number: string;
  subtotal: number;
  grand_total: number;
  cloud_id?: string;
}): Promise<{ local_uuid: string; cloud_id: string | null }> {
  const local_uuid = uuidv4();
  const idempotency_key = uuidv4();
  const now = new Date().toISOString();

  await localDb.execute(
    `INSERT INTO local_bills (
      local_uuid, company_id, branch_id, order_local_uuid, bill_number,
      subtotal, grand_total, amount_due, paid_amount, remaining_amount, status,
      sync_status, cloud_id, idempotency_key, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'open', ?, ?, ?, ?)`,
    [
      local_uuid,
      opts.company_id,
      opts.branch_id,
      opts.order_local_uuid,
      opts.bill_number,
      opts.subtotal,
      opts.grand_total,
      opts.grand_total,
      opts.grand_total,
      opts.cloud_id ? "synced" : "pending",
      opts.cloud_id || null,
      idempotency_key,
      now,
    ]
  );
  return { local_uuid, cloud_id: opts.cloud_id || null };
}

/**
 * Helper: Seed payment methods en DB (necesario para resolvePaymentMethodUuid).
 *
 * Schema de local_payment_methods (migración 001):
 *   uuid TEXT PRIMARY KEY,
 *   code TEXT NOT NULL,
 *   type TEXT NOT NULL,
 *   is_active INTEGER DEFAULT 1,
 *   last_updated TEXT DEFAULT CURRENT_TIMESTAMP
 *
 * PaymentRepository.resolvePaymentMethodUuid() hace:
 *   SELECT uuid FROM local_payment_methods WHERE code = ? AND is_active = 1
 */
async function seedPaymentMethods(): Promise<void> {
  const methods = [
    { code: "CASH", type: "cash" },
    { code: "CARD", type: "card" },
    { code: "TRANSFER", type: "transfer" },
    { code: "GIFT_CARD", type: "gift_card" },
  ];

  for (const method of methods) {
    await localDb.execute(
      `INSERT OR IGNORE INTO local_payment_methods 
       (uuid, code, type, is_active) 
       VALUES (?, ?, ?, 1)`,
      [uuidv4(), method.code, method.type]
    );
  }
}

describe("SyncEngine - Split Bill E2E (ADR-020)", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    vi.resetAllMocks();
    mockAuthContext({ companyId: "c1", branchId: "b1" });
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_payment_methods");

    // Seed payment methods (necesario para resolvePaymentMethodUuid)
    await seedPaymentMethods();
  });

  it("E2E: split bill offline → sync → backend con bill_uuid correcto", async () => {
    // ============================================
    // SETUP: Order + 2 bills + 2 payments (split bill)
    // ============================================

    // Order ya sincronizado (tiene cloud_id)
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "cloud-order-123",
    });

    // Bill A ya sincronizada (tiene cloud_id)
    const billA = await createBillWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "SPLIT-A",
      subtotal: 10000,
      grand_total: 10000,
      cloud_id: "cloud-bill-A",
    });

    // Bill B ya sincronizada (tiene cloud_id)
    const billB = await createBillWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "SPLIT-B",
      subtotal: 10000,
      grand_total: 10000,
      cloud_id: "cloud-bill-B",
    });

    // Payment A vinculado a Bill A (bill_local_uuid)
    const paymentA = await PaymentRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_local_uuid: billA.local_uuid,
      payment_method: "cash",
      amount: 10000,
      tip_amount: 0,
    });

    // Payment B vinculado a Bill B (bill_local_uuid)
    const paymentB = await PaymentRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_local_uuid: billB.local_uuid,
      payment_method: "card",
      amount: 10000,
      tip_amount: 0,
    });

    // ============================================
    // EXECUTE: Sync todos los payments
    // ============================================

    // Verificar que hay 2 payments en cola
    const queue = await SyncQueueRepository.getPending(10);
    expect(queue.length).toBe(2);
    expect(queue.every((q) => q.entity_type === "payment")).toBe(true);

    // Mock de respuestas del backend
    (apiClient.post as any)
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-payment-A" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-payment-B" } } });

    const stats = await syncEngine.processBatch();

    // ============================================
    // ASSERT: Verificar que backend recibió bill_uuid correcto
    // ============================================

    expect(stats.success).toBe(2);
    expect(stats.failed).toBe(0);

    // Verificar llamadas a API
    const calls = (apiClient.post as any).mock.calls;
    const paymentCalls = calls.filter((c: any) => c[0] === "/billing/payments");
    expect(paymentCalls.length).toBe(2);

    // Primer payment: debe tener bill_uuid = cloud-bill-A
    const payment1Payload = paymentCalls[0][1];
    expect(payment1Payload.order_uuid).toBe("cloud-order-123");
    expect(payment1Payload.bill_uuid).toBe("cloud-bill-A");
    expect(payment1Payload.amount).toBe(10000);

    // Segundo payment: debe tener bill_uuid = cloud-bill-B
    const payment2Payload = paymentCalls[1][1];
    expect(payment2Payload.order_uuid).toBe("cloud-order-123");
    expect(payment2Payload.bill_uuid).toBe("cloud-bill-B");
    expect(payment2Payload.amount).toBe(10000);

    // Verificar que payments quedaron marcados como synced
    const paymentAUpdated = await PaymentRepository.findByLocalUuid(paymentA.local_uuid);
    expect(paymentAUpdated?.sync_status).toBe("synced");
    expect(paymentAUpdated?.cloud_id).toBe("cloud-payment-A");

    const paymentBUpdated = await PaymentRepository.findByLocalUuid(paymentB.local_uuid);
    expect(paymentBUpdated?.sync_status).toBe("synced");
    expect(paymentBUpdated?.cloud_id).toBe("cloud-payment-B");
  });

  it("processPayment falla si bill_local_uuid no tiene cloud_id", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "cloud-order-fail",
    });

    // Bill SIN cloud_id (no sincronizada)
    const billWithoutCloud = await createBillWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-NO-CLOUD",
      subtotal: 5000,
      grand_total: 5000,
      // cloud_id omitido intencionalmente
    });

    // Payment vinculado a bill sin cloud_id
    await PaymentRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_local_uuid: billWithoutCloud.local_uuid,
      payment_method: "cash",
      amount: 5000,
    });

    const stats = await syncEngine.processBatch();

    // Payment debe fallar porque bill no tiene cloud_id
    expect(stats.failed).toBeGreaterThan(0);
    expect(apiClient.post).not.toHaveBeenCalledWith(
      "/billing/payments",
      expect.anything(),
      expect.anything()
    );
  });

  it("processPayment funciona sin bill_local_uuid (pago directo a order)", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "cloud-order-direct",
    });

    // Payment SIN bill_local_uuid (pago directo a order)
    await PaymentRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      // bill_local_uuid omitido
      payment_method: "cash",
      amount: 15000,
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-payment-direct" } },
    });

    const stats = await syncEngine.processBatch();

    expect(stats.success).toBe(1);

    const calls = (apiClient.post as any).mock.calls;
    const paymentCall = calls.find((c: any) => c[0] === "/billing/payments");
    expect(paymentCall).toBeDefined();

    const payload = paymentCall[1];
    expect(payload.order_uuid).toBe("cloud-order-direct");
    expect(payload.bill_uuid).toBeNull();
    expect(payload.amount).toBe(15000);
  });
});
