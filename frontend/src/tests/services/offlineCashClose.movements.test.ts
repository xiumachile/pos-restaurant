import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { offlineCashCloseService } from "@/services/offlineCashCloseService";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { CashMovementRepository } from "@/db/repositories/CashMovementRepository";
import { PaymentRepository } from "@/db/repositories/PaymentRepository";
import { BillRepository } from "@/db/repositories/BillRepository";
import { OrderRepository } from "@/db/repositories/OrderRepository";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("@/services/authContext", () => ({
  getCashierContextSafe: () => ({
    company_id: "company-1",
    branch_id: "branch-1",
    terminal_id: "terminal-1",
    user_id: "user-1",
    user_name: "Juan Pérez",
  }),
}));

vi.mock("@/services/printing/ticketFormatters", () => ({
  ticketToBase64: {
    cashCopy: vi.fn().mockReturnValue(btoa("MOCK ESCPOS DATA")),
  },
}));

describe("offlineCashCloseService - Ajuste por movimientos manuales de caja", () => {
  beforeEach(async () => {
    localDb;
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_cash_sessions");
    vi.clearAllMocks();
  });

  // Helper: crear order + bill + session completos
  const createFullSetup = async (openingAmount = 50000) => {
    const session = await CashSessionRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      user_id: "user-1",
      user_name: "Juan Pérez",
      opening_amount: openingAmount,
    });

    // Crear order (requerido para bill por FK)
    const order = await OrderRepository.create({
      order_type: "dine_in",
      table_local_uuid: null,
      guest_count: 2,
      notes: null,
      company_id: "company-1",
      branch_id: "branch-1",
      user_id: "user-1",
    } as any);

    // Crear bill con todos los campos requeridos
    const bill = await BillRepository.create({
      order_local_uuid: order.local_uuid,
      bill_number: `42-${Date.now()}`,
      subtotal: 84034,
      tax_amount: 15966,
      tip_amount: 0,
      total: 100000,
      net_amount: 84034,
      amount_due: 100000,
      status: "open",
      company_id: "company-1",
      branch_id: "branch-1",
    } as any);

    return { session, order, bill };
  };

  describe("Cálculo de expectedAmount con movimientos manuales", () => {
    it("debe restar los retiros manuales (withdrawal) del efectivo esperado", async () => {
      const { session, bill } = await createFullSetup(50000);

      // Crear pago con firma correcta (2 args opcionales, payload primero)
      const payment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_local_uuid: bill.local_uuid,
        payment_method: "cash",
        amount: 100000,
        tip_amount: 0,
      });

      // Movimiento de pago (entrada automática) - firma correcta: (sessionUuid, payload)
      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 100000,
        reference_type: "payment",
        reference_local_uuid: payment.local_uuid,
        reason: "Pago bill",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      // Retiro manual - firma correcta: (sessionUuid, payload)
      await CashMovementRepository.create(session.local_uuid, {
        type: "withdrawal",
        amount: 20000,
        reason: "Compra de insumos",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 130000,
      });

      // [AUDIT FIX] Esperado: 50k + 100k - 20k = 130k
      expect(result.expectedAmount).toBe(130000);
      expect(result.difference).toBe(0);
    });

    it("debe sumar los ingresos manuales (deposit) al efectivo esperado", async () => {
      const { session, bill } = await createFullSetup(50000);

      const payment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_local_uuid: bill.local_uuid,
        payment_method: "cash",
        amount: 80000,
        tip_amount: 0,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 80000,
        reference_type: "payment",
        reference_local_uuid: payment.local_uuid,
        reason: "Pago bill",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      // Ingreso manual (deposit)
      await CashMovementRepository.create(session.local_uuid, {
        type: "deposit",
        amount: 15000,
        reason: "Cambio inicial extra",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 145000,
      });

      // [AUDIT FIX] Esperado: 50k + 80k + 15k = 145k
      expect(result.expectedAmount).toBe(145000);
      expect(result.difference).toBe(0);
    });

    it("debe calcular neto correctamente con múltiples movimientos mixtos", async () => {
      const { session, bill } = await createFullSetup(50000);

      const payment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_local_uuid: bill.local_uuid,
        payment_method: "cash",
        amount: 200000,
        tip_amount: 0,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 200000,
        reference_type: "payment",
        reference_local_uuid: payment.local_uuid,
        reason: "Pago bill",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      await CashMovementRepository.create(session.local_uuid, {
        type: "deposit",
        amount: 10000,
        reason: "Cambio extra",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      await CashMovementRepository.create(session.local_uuid, {
        type: "withdrawal",
        amount: 30000,
        reason: "Compra insumos",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      await CashMovementRepository.create(session.local_uuid, {
        type: "withdrawal",
        amount: 5000,
        reason: "Préstamo",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 225000,
      });

      // [AUDIT FIX] Esperado: 50k + 200k + 10k - 30k - 5k = 225k
      expect(result.expectedAmount).toBe(225000);
      expect(result.difference).toBe(0);
    });

    it("debe calcular discrepancia negativa cuando faltan billetes", async () => {
      const { session, bill } = await createFullSetup(50000);

      const payment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_local_uuid: bill.local_uuid,
        payment_method: "cash",
        amount: 100000,
        tip_amount: 0,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 100000,
        reference_type: "payment",
        reference_local_uuid: payment.local_uuid,
        reason: "Pago bill",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      await CashMovementRepository.create(session.local_uuid, {
        type: "withdrawal",
        amount: 20000,
        reason: "Retiro",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 125000, // Cajero cuenta 125k en vez de 130k
      });

      expect(result.expectedAmount).toBe(130000);
      expect(result.difference).toBe(-5000);
    });

    it("debe funcionar correctamente cuando no hay movimientos manuales", async () => {
      const { session, bill } = await createFullSetup(50000);

      const payment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_local_uuid: bill.local_uuid,
        payment_method: "cash",
        amount: 150000,
        tip_amount: 0,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 150000,
        reference_type: "payment",
        reference_local_uuid: payment.local_uuid,
        reason: "Pago bill",
        company_id: "company-1",
        branch_id: "branch-1",
      } as any);

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 200000,
      });

      expect(result.expectedAmount).toBe(200000);
      expect(result.difference).toBe(0);
    });
  });
});
