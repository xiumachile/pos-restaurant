/**
 * P0-1: Test de protección contra split bill ambiguo.
 *
 * Verifica que cuando un order tiene múltiples bills (split bill)
 * y no se especifica billLocalUuid, el servicio lanza error explícito
 * en lugar de pagar la primera bill arbitrariamente.
 */
import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { offlinePaymentService } from "../../services/offlinePaymentService";

describe("P0-1: Split Bill Ambiguity Protection", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_orders");
  });

  /**
   * Crea un order servido con monto específico (incluye IVA).
   */
  async function createServedOrder() {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_type: "dine_in",
    });
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Producto Split",
      quantity: 1,
      unit_price: 20000,
    });
    await OrderRepository.updateStatus(order.local_uuid, "served");
    return await OrderRepository.findByLocalUuid(order.local_uuid);
  }

  it("lanza MULTIPLE_BILLS_FOUND si hay 2+ bills sin billLocalUuid", async () => {
    // Setup: order con 2 bills (split bill scenario)
    const order = await createServedOrder();

    // Crear 2 bills para el mismo order (split bill)
    await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-SPLIT-001",
      subtotal: 10000,
      grand_total: 10000,
    });

    await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-SPLIT-002",
      subtotal: 10000,
      grand_total: 10000,
    });

    // Intentar pagar SIN especificar billLocalUuid (debe fallar)
    await expect(
      offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order!.local_uuid,
        paymentMethod: "cash",
        amount: 10000,
        autoCreateBill: false,
        // billLocalUuid NO especificado → AMBIGUO
      })
    ).rejects.toThrow("MULTIPLE_BILLS_FOUND");
  });

  it("acepta billLocalUuid específico en split bill", async () => {
    // Setup: order con 2 bills
    const order = await createServedOrder();

    const targetBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-TARGET-001",
      subtotal: 10000,
      grand_total: 10000,
    });

    const otherBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-OTHER-001",
      subtotal: 10000,
      grand_total: 10000,
    });

    // Pagar CON billLocalUuid específico (debe funcionar)
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: targetBill.local_uuid, // ← Específico
      paymentMethod: "cash",
      amount: 10000,
      autoCreateBill: false,
    });

    // Verificar que se pagó la bill correcta
    expect(result.bill).not.toBeNull();
    expect(result.bill?.local_uuid).toBe(targetBill.local_uuid);
    expect(result.bill?.paid_amount).toBe(10000);
    expect(result.bill?.remaining_amount).toBe(0);
    expect(result.bill?.status).toBe("paid");

    // Verificar que la OTRA bill no fue tocada
    const otherBillReloaded = await BillRepository.findByLocalUuid(otherBill.local_uuid);
    expect(otherBillReloaded?.paid_amount).toBe(0);
    expect(otherBillReloaded?.remaining_amount).toBe(10000);
    expect(otherBillReloaded?.status).toBe("open");
  });
});
