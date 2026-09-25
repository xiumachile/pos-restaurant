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
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { offlinePaymentService } from "../../services/offlinePaymentService";

describe("P0-1: Split Bill Ambiguity Protection", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  afterAll(async () => {
    // localDb.close() gestionado por Rust
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

    // 🔗 ADR-019: Verificar que el payment queda vinculado a la bill correcta
    // Esto es crítico para preservar la estructura del split bill durante sync
    const payment = await PaymentRepository.findByLocalUuid(result.payment.local_uuid);
    expect(payment).not.toBeNull();
    expect(payment?.bill_local_uuid).toBe(targetBill.local_uuid);
  });
});

describe("ADR-019: Bill link en payments", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  afterAll(async () => {
    // localDb.close() gestionado por Rust
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_orders");
  });

  /**
   * Crea order con monto grande para evitar que quede "paid" prematuramente.
   * El sistema marca el order como "paid" cuando UNA bill queda pagada al 100%.
   */
  async function createLargeOrder(totalAmount: number = 100000) {
    const order = await OrderRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_type: "dine_in",
    });
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Producto Grande",
      quantity: 1,
      unit_price: totalAmount,
    });
    await OrderRepository.updateStatus(order.local_uuid, "served");
    return await OrderRepository.findByLocalUuid(order.local_uuid);
  }

  it("pago con billLocalUuid específico en split bill vincula payment a esa bill", async () => {
    const order = await createLargeOrder();

    const targetBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-TARGET",
      subtotal: 10000,
      grand_total: 10000,
    });

    const otherBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-OTHER",
      subtotal: 10000,
      grand_total: 10000,
    });

    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: targetBill.local_uuid,
      paymentMethod: "cash",
      amount: 10000,
      autoCreateBill: false,
    });

    // 🔗 ADR-019: Verificar que el payment queda vinculado a la bill específica
    const payment = await PaymentRepository.findByLocalUuid(result.payment.local_uuid);
    expect(payment).not.toBeNull();
    expect(payment?.bill_local_uuid).toBe(targetBill.local_uuid);
    expect(payment?.bill_local_uuid).not.toBe(otherBill.local_uuid);
  });

  it("múltiples payments PARCIALES a diferentes bills preservan estructura split", async () => {
    // Order grande (100000) para evitar estado paid
    const order = await createLargeOrder(100000);

    // Crear 3 bills con montos grandes
    const bill1 = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "SPLIT-001",
      subtotal: 15000,
      grand_total: 15000,
    });

    const bill2 = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "SPLIT-002",
      subtotal: 15000,
      grand_total: 15000,
    });

    const bill3 = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "SPLIT-003",
      subtotal: 15000,
      grand_total: 15000,
    });

    // 🔑 PAGO PARCIAL a cada bill (para que ninguna quede "paid" y el order siga "served")
    const result1 = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: bill1.local_uuid,
      paymentMethod: "cash",
      amount: 2000,  // PARCIAL de 15000
      autoCreateBill: false,
    });

    const result2 = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: bill2.local_uuid,
      paymentMethod: "card",
      amount: 3000,  // PARCIAL de 15000
      autoCreateBill: false,
    });

    const result3 = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: bill3.local_uuid,
      paymentMethod: "transfer",
      amount: 4000,  // PARCIAL de 15000
      autoCreateBill: false,
    });

    // 🔗 ADR-019: Cada payment vinculado a su bill correcta
    const payment1 = await PaymentRepository.findByLocalUuid(result1.payment.local_uuid);
    const payment2 = await PaymentRepository.findByLocalUuid(result2.payment.local_uuid);
    const payment3 = await PaymentRepository.findByLocalUuid(result3.payment.local_uuid);

    expect(payment1?.bill_local_uuid).toBe(bill1.local_uuid);
    expect(payment2?.bill_local_uuid).toBe(bill2.local_uuid);
    expect(payment3?.bill_local_uuid).toBe(bill3.local_uuid);

    // Verificar todas las bills siguen en status "partial" (ninguna quedó "paid")
    const bill1Reloaded = await BillRepository.findByLocalUuid(bill1.local_uuid);
    const bill2Reloaded = await BillRepository.findByLocalUuid(bill2.local_uuid);
    const bill3Reloaded = await BillRepository.findByLocalUuid(bill3.local_uuid);

    expect(bill1Reloaded?.status).toBe("partial");
    expect(bill2Reloaded?.status).toBe("partial");
    expect(bill3Reloaded?.status).toBe("partial");

    // Verificar orden cronológico en BD
    const allPayments = await localDb.select<{ local_uuid: string; bill_local_uuid: string }>(
      "SELECT local_uuid, bill_local_uuid FROM local_payments WHERE order_local_uuid = ? ORDER BY created_at",
      [order!.local_uuid]
    );

    expect(allPayments).toHaveLength(3);
    expect(allPayments[0].bill_local_uuid).toBe(bill1.local_uuid);
    expect(allPayments[1].bill_local_uuid).toBe(bill2.local_uuid);
    expect(allPayments[2].bill_local_uuid).toBe(bill3.local_uuid);
  });

  it("payload de sync_queue incluye bill_local_uuid para el backend", async () => {
    const order = await createLargeOrder();

    const targetBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-SYNC",
      subtotal: 15000,
      grand_total: 15000,
    });

    await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: targetBill.local_uuid,
      paymentMethod: "cash",
      amount: 5000,  // PARCIAL
      autoCreateBill: false,
    });

    // 🔗 ADR-019: El sync_queue debe incluir bill_local_uuid en el payload
    const queueItems = await localDb.select<{ payload: string }>(
      "SELECT payload FROM sync_queue WHERE entity_type = 'payment' ORDER BY created_at DESC LIMIT 1"
    );

    expect(queueItems).toHaveLength(1);
    const payload = JSON.parse(queueItems[0].payload);
    expect(payload.bill_local_uuid).toBe(targetBill.local_uuid);
    expect(payload.order_local_uuid).toBe(order!.local_uuid);
  });

  it("pago que completa una bill la vincula correctamente al payment", async () => {
    const order = await createLargeOrder();

    const targetBill = await BillRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      order_local_uuid: order!.local_uuid,
      bill_number: "BILL-COMPLETE",
      subtotal: 10000,
      grand_total: 10000,
    });

    // Pago que COMPLETA la bill (grand_total = amount)
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      billLocalUuid: targetBill.local_uuid,
      paymentMethod: "cash",
      amount: 10000,
      autoCreateBill: false,
    });

    // 🔗 ADR-019: Aún cuando la bill queda paid, el link payment→bill debe existir
    const payment = await PaymentRepository.findByLocalUuid(result.payment.local_uuid);
    expect(payment).not.toBeNull();
    expect(payment?.bill_local_uuid).toBe(targetBill.local_uuid);

    // La bill queda paid
    const billReloaded = await BillRepository.findByLocalUuid(targetBill.local_uuid);
    expect(billReloaded?.status).toBe("paid");
    expect(billReloaded?.paid_amount).toBe(10000);
  });
});
