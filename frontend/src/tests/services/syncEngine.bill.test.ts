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
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";
import { mockAuthContext } from "../testUtils";

/**
 * Helper: Crea order directamente en DB sin encolar a sync_queue.
 * Esto aísla los tests de processBill (evita que processOrder
 * consuma mocks y modifique el estado de la cola).
 *
 * Incluye idempotency_key (NOT NULL en schema).
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
      `ORD-TEST-${Date.now()}-${Math.random()}`,
      opts.cloud_id ? "synced" : "pending",
      opts.cloud_id || null,
      idempotency_key,
    ]
  );
  return { local_uuid, cloud_id: opts.cloud_id || null };
}

describe("SyncEngine - Bill sync (ADR-020)", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  beforeEach(async () => {
    vi.resetAllMocks();
    mockAuthContext({ companyId: "c1", branchId: "b1" });
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
  });

  it("BillRepository.create encola bill en sync_queue", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
    });

    const bill = await BillRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-001",
      subtotal: 10000,
      grand_total: 10000,
    });

    expect(bill).toBeDefined();
    expect(bill.local_uuid).toBeDefined();

    const queue = await localDb.select<{ entity_type: string; entity_local_uuid: string }>(
      "SELECT entity_type, entity_local_uuid FROM sync_queue WHERE entity_type = 'bill'"
    );

    expect(queue.length).toBe(1);
    expect(queue[0].entity_type).toBe("bill");
    expect(queue[0].entity_local_uuid).toBe(bill.local_uuid);
  });

  it("processBill llama POST /bills con payload correcto", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "cloud-order-uuid",
    });

    const bill = await BillRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-002",
      subtotal: 10000,
      grand_total: 10000,
    });

    // Solo debería haber 1 item en cola (la bill)
    const queue = await SyncQueueRepository.getPending(10);
    expect(queue.length).toBe(1);
    expect(queue[0].entity_type).toBe("bill");

    const mockResponse = { uuid: "cloud-bill-uuid", id: 123 };
    (apiClient.post as any).mockResolvedValueOnce({ data: mockResponse });

    const stats = await syncEngine.processBatch();

    expect(stats.success).toBe(1);
    expect(apiClient.post).toHaveBeenCalledWith(
      "/bills",
      expect.objectContaining({
        order_uuid: "cloud-order-uuid",
        bill_number: "BILL-002",
        subtotal: 10000,
        status: "open",
      }),
      expect.objectContaining({
        headers: expect.objectContaining({ "Idempotency-Key": expect.any(String) }),
      })
    );

    // Verificar que cloud_id se guardó en local_bills
    const updatedBill = await BillRepository.findByLocalUuid(bill.local_uuid);
    expect(updatedBill?.cloud_id).toBe("cloud-bill-uuid");
    expect(updatedBill?.sync_status).toBe("synced");
  });

  it("processBill resuelve order_uuid desde order_local_uuid", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "resolved-order-uuid",
    });

    await BillRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-003",
      subtotal: 5000,
      grand_total: 5000,
    });

    (apiClient.post as any).mockResolvedValueOnce({ data: { uuid: "cloud-bill-3" } });

    await syncEngine.processBatch();

    const calls = (apiClient.post as any).mock.calls;
    const billCall = calls.find((c: any) => c[0] === "/bills");
    expect(billCall).toBeDefined();
    expect(billCall[1].order_uuid).toBe("resolved-order-uuid");
  });

  it("processBill falla si order no tiene cloud_id", async () => {
    // Order SIN cloud_id (no sincronizada)
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      // cloud_id omitido intencionalmente
    });

    await BillRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-004",
      subtotal: 5000,
      grand_total: 5000,
    });

    const stats = await syncEngine.processBatch();

    // La bill debe fallar porque order no tiene cloud_id
    expect(stats.failed).toBeGreaterThan(0);
    expect(apiClient.post).not.toHaveBeenCalledWith("/bills", expect.anything(), expect.anything());
  });

  it("mapea campos frontend→backend correctamente", async () => {
    const order = await createOrderWithoutEnqueue({
      company_id: "c1",
      branch_id: "b1",
      cloud_id: "order-uuid-mapping",
    });

    await BillRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_local_uuid: order.local_uuid,
      bill_number: "BILL-MAP",
      subtotal: 11900,  // IVA incluido
      discount_total: 500,
      tip_amount: 1000,
      grand_total: 11400,  // 11900 - 500
    });

    (apiClient.post as any).mockResolvedValueOnce({ data: { uuid: "cloud-map" } });

    await syncEngine.processBatch();

    const calls = (apiClient.post as any).mock.calls;
    const billCall = calls.find((c: any) => c[0] === "/bills");
    expect(billCall).toBeDefined();

    const payload = billCall[1];
    // Mapeos esperados:
    // tax_total → tax_amount
    expect(payload.tax_amount).toBeDefined();
    // discount_total → discount_amount
    expect(payload.discount_amount).toBe(500);
    // Backend: Bill.total = grand_total (venta sin propina) = 11400
    // Payment.tip_amount = 1000 (separado)
    expect(payload.total).toBe(11400);  // Backend: total = grand_total (solo venta, sin propina)
    expect(payload.tip_amount).toBe(1000);
    expect(payload.subtotal).toBe(11900);
  });
});
