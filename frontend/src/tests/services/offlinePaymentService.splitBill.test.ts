/**
 * P0-1: Test de protección contra split bill ambiguo.
 *
 * Verifica que cuando un order tiene múltiples bills (split bill)
 * y no se especifica billLocalUuid, el servicio lanza error explícito
 * en lugar de pagar la primera bill arbitrariamente.
 */
import { describe, it, expect, beforeEach } from "vitest";
import { offlinePaymentService } from "@/services/offlinePaymentService";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { BillRepository } from "@/db/repositories/BillRepository";
import { localDb } from "@/db/localDb";

describe("P0-1: Split Bill Ambiguity Protection", () => {
  beforeEach(async () => {
    // Reset DB before each test
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_orders");
  });

  it("lanza MULTIPLE_BILLS_FOUND si hay 2+ bills sin billLocalUuid", async () => {
    // Setup: crear order con 2 bills (split bill scenario)
    const orderLocalUuid = "order-split-001";
    await OrderRepository.create({
      local_uuid: orderLocalUuid,
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      table_name: "Mesa 1",
      status: "served",
      order_number: "ORD-SPLIT-001",
      subtotal: 20000,
      discount_total: 0,
      tax_total: 3800,
      tip_amount: 0,
      grand_total: 20000,
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    // Crear 2 bills para el mismo order (split bill)
    await BillRepository.create({
      local_uuid: "bill-split-001",
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      order_local_uuid: orderLocalUuid,
      order_cloud_id: null,
      bill_number: "BILL-001",
      subtotal: 10000,
      discount_total: 0,
      tax_total: 1900,
      tip_amount: 0,
      grand_total: 10000,
      paid_amount: 0,
      remaining_amount: 10000,
      status: "open",
      idempotency_key: "idem-bill-001",
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    await BillRepository.create({
      local_uuid: "bill-split-002",
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      order_local_uuid: orderLocalUuid,
      order_cloud_id: null,
      bill_number: "BILL-002",
      subtotal: 10000,
      discount_total: 0,
      tax_total: 1900,
      tip_amount: 0,
      grand_total: 10000,
      paid_amount: 0,
      remaining_amount: 10000,
      status: "open",
      idempotency_key: "idem-bill-002",
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    // Intentar pagar SIN especificar billLocalUuid (debe fallar)
    await expect(
      offlinePaymentService.createPaymentOffline({
        orderLocalUuid,
        paymentMethod: "cash",
        amount: 10000,
        autoCreateBill: false,
        // billLocalUuid NO especificado → AMBIGUO
      })
    ).rejects.toThrow("MULTIPLE_BILLS_FOUND");
  });

  it("acepta billLocalUuid específico en split bill", async () => {
    // Setup: order con 2 bills
    const orderLocalUuid = "order-split-002";
    await OrderRepository.create({
      local_uuid: orderLocalUuid,
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      table_name: "Mesa 2",
      status: "served",
      order_number: "ORD-SPLIT-002",
      subtotal: 20000,
      discount_total: 0,
      tax_total: 3800,
      tip_amount: 0,
      grand_total: 20000,
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    await BillRepository.create({
      local_uuid: "bill-target-001",
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      order_local_uuid: orderLocalUuid,
      order_cloud_id: null,
      bill_number: "BILL-TARGET-001",
      subtotal: 10000,
      discount_total: 0,
      tax_total: 1900,
      tip_amount: 0,
      grand_total: 10000,
      paid_amount: 0,
      remaining_amount: 10000,
      status: "open",
      idempotency_key: "idem-target-001",
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    await BillRepository.create({
      local_uuid: "bill-other-001",
      cloud_id: null,
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      order_local_uuid: orderLocalUuid,
      order_cloud_id: null,
      bill_number: "BILL-OTHER-001",
      subtotal: 10000,
      discount_total: 0,
      tax_total: 1900,
      tip_amount: 0,
      grand_total: 10000,
      paid_amount: 0,
      remaining_amount: 10000,
      status: "open",
      idempotency_key: "idem-other-001",
      sync_status: "pending",
      sync_error: null,
      notes: null,
    });

    // Pagar CON billLocalUuid específico (debe funcionar)
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid,
      billLocalUuid: "bill-target-001", // ← Específico
      paymentMethod: "cash",
      amount: 10000,
      autoCreateBill: false,
    });

    // Verificar que se pagó la bill correcta
    expect(result.bill).not.toBeNull();
    expect(result.bill?.local_uuid).toBe("bill-target-001");
    expect(result.bill?.paid_amount).toBe(10000);
    expect(result.bill?.remaining_amount).toBe(0);
    expect(result.bill?.status).toBe("paid");

    // Verificar que la OTRA bill no fue tocada
    const otherBill = await BillRepository.findByLocalUuid("bill-other-001");
    expect(otherBill?.paid_amount).toBe(0);
    expect(otherBill?.remaining_amount).toBe(10000);
    expect(otherBill?.status).toBe("open");
  });
});
