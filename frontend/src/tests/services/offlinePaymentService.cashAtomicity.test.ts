import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { PaymentRepository } from "@/db/repositories/PaymentRepository";
import { BillRepository } from "@/db/repositories/BillRepository";
import { getTerminalId } from "@/services/terminalIdentity";
import {
  offlinePaymentService,
  OfflinePaymentError,
} from "@/services/offlinePaymentService";
import { useAuthStore } from "@/store/useAuthStore";

/**
 * Tests de atomicidad del pago en efectivo.
 *
 * Regla de negocio crítica (fix de integridad financiera):
 *
 * 1. Si hay sesión de caja abierta + fallo en movimiento → ROLLBACK
 *    → No queda payment huérfano sin movimiento de caja
 *
 * 2. Si no hay sesión de caja → pago válido sin movimiento (warning)
 *    → Permite delivery, takeout o cajero que no abrió caja
 *
 * 3. Si método es card/transfer → sin requerimientos de caja
 */
describe("offlinePaymentService - Atomicidad de pago cash", () => {
  const cashierUser = {
    id: 42,
    uuid: "cashier-uuid-123",
    name: "Cashier Atomicity",
    email: "cashier@test.com",
    role: "cashier" as const,
    company_id: 10,
    branch_id: 5,
  };

  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();

    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");
    await localDb.execute("DELETE FROM sync_queue");

    vi.clearAllMocks();

    await useAuthStore.getState().setAuth(cashierUser, "token");
  });

  afterEach(async () => {
    await useAuthStore.getState().clearAuth();
  });

  // Helper: crear order con item y estado servido
  // Retorna order + monto total exacto (incluye IVA)
  async function createServedOrder() {
    const order = await OrderRepository.create({
      company_id: "10",
      branch_id: "5",
      order_type: "dine_in",
    });
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Test Product",
      quantity: 1,
      unit_price: 5000,
    });
    await OrderRepository.updateStatus(order.local_uuid, "served");
    
    // Recargar order para obtener grand_total exacto (con IVA)
    const reloaded = await OrderRepository.findByLocalUuid(order.local_uuid);
    return { order: reloaded!, totalAmount: reloaded!.grand_total };
  }

  describe("Cash con sesión de caja abierta", () => {
    it("debería crear payment + movimiento de caja en la misma transacción", async () => {
      const terminalId = getTerminalId();
      
      // ✅ FIX: pasar terminal_id para que coincida con findActive()
      await CashSessionRepository.create({
        company_id: "10",
        branch_id: "5",
        terminal_id: terminalId,
        user_id: "cashier-uuid-123",
        opening_amount: 100000,
      });

      const { order, totalAmount } = await createServedOrder();

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: totalAmount,
      });

      // Verificar payment creado
      expect(result.payment).toBeDefined();
      expect(result.payment.amount).toBe(totalAmount);

      // Verificar movimiento de caja creado
      const movements = await localDb.select(
        "SELECT * FROM local_cash_movements WHERE reference_local_uuid = ?",
        [result.payment.local_uuid]
      );
      expect(movements).toHaveLength(1);
      expect(movements[0].amount).toBe(totalAmount);
      expect(movements[0].type).toBe("payment");
    });

    it("debería hacer ROLLBACK si falla el movimiento de caja (integridad financiera)", async () => {
      const terminalId = getTerminalId();
      
      // ✅ FIX: pasar terminal_id
      await CashSessionRepository.create({
        company_id: "10",
        branch_id: "5",
        terminal_id: terminalId,
        user_id: "cashier-uuid-123",
        opening_amount: 100000,
      });

      const { order, totalAmount } = await createServedOrder();

      // Simular fallo en CashMovementRepository.create
      const movementRepo = await import("@/db/repositories/CashMovementRepository");
      const originalCreate = movementRepo.CashMovementRepository.create;
      movementRepo.CashMovementRepository.create = vi
        .fn()
        .mockRejectedValue(new Error("DB constraint violation"));

      // Intentar pagar → debe fallar con error
      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: order.local_uuid,
          paymentMethod: "cash",
          amount: totalAmount,
        })
      ).rejects.toThrow();

      // Restaurar mock
      movementRepo.CashMovementRepository.create = originalCreate;

      // Verificar ROLLBACK: NO debe haber payment creado
      const payments = await localDb.select(
        "SELECT * FROM local_payments WHERE order_local_uuid = ?",
        [order.local_uuid]
      );
      expect(payments).toHaveLength(0);

      // Verificar ROLLBACK: bill no debe estar pagada
      const bills = await BillRepository.findByOrder(order.local_uuid);
      expect(bills[0]?.paid_amount || 0).toBe(0);

      // Verificar ROLLBACK: order sigue en estado "served"
      const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
      expect(updatedOrder?.status).toBe("served");

      // Verificar ROLLBACK: no hay movimientos en caja
      const movements = await localDb.select("SELECT * FROM local_cash_movements");
      expect(movements).toHaveLength(0);
    });
  });

  describe("Cash sin sesión de caja", () => {
    it("debería crear payment válido pero sin movimiento (escenario delivery/takeout)", async () => {
      // NO abrir sesión de caja

      const { order, totalAmount } = await createServedOrder();

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: totalAmount,
      });

      // Payment debe crearse
      expect(result.payment).toBeDefined();
      expect(result.payment.amount).toBe(totalAmount);

      // ✅ FIX: Bill debe marcarse como pagada (monto cubre total)
      expect(result.bill?.status).toBe("paid");
      expect(result.orderPaid).toBe(true);

      // PERO: no debe haber movimiento de caja
      const movements = await localDb.select(
        "SELECT * FROM local_cash_movements WHERE reference_local_uuid = ?",
        [result.payment.local_uuid]
      );
      expect(movements).toHaveLength(0);
    });
  });

  describe("Card/Transfer (sin requerimientos de caja)", () => {
    it("card: debería crear payment sin requerir sesión de caja", async () => {
      const { order, totalAmount } = await createServedOrder();

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "card",
        amount: totalAmount,
      });

      expect(result.payment).toBeDefined();
      expect(result.payment.payment_method).toBe("card");

      // Sin movimiento de caja (solo aplica para cash)
      const movements = await localDb.select(
        "SELECT * FROM local_cash_movements WHERE reference_local_uuid = ?",
        [result.payment.local_uuid]
      );
      expect(movements).toHaveLength(0);
    });

    it("card con sesión abierta: debería ignorar la sesión", async () => {
      const terminalId = getTerminalId();
      
      // ✅ FIX: pasar terminal_id
      await CashSessionRepository.create({
        company_id: "10",
        branch_id: "5",
        terminal_id: terminalId,
        user_id: "cashier-uuid-123",
        opening_amount: 100000,
      });

      const { order, totalAmount } = await createServedOrder();

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "card",
        amount: totalAmount,
      });

      expect(result.payment).toBeDefined();

      // Sin movimiento de caja (card no genera movimientos)
      const movements = await localDb.select(
        "SELECT * FROM local_cash_movements WHERE reference_local_uuid = ?",
        [result.payment.local_uuid]
      );
      expect(movements).toHaveLength(0);
    });

    it("transfer: debería crear payment sin requerir sesión de caja", async () => {
      const { order, totalAmount } = await createServedOrder();

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "transfer",
        amount: totalAmount,
        referenceCode: "TRX-001",
      });

      expect(result.payment).toBeDefined();
      expect(result.payment.payment_method).toBe("transfer");
      expect(result.payment.reference_code).toBe("TRX-001");
    });
  });

  describe("Escenario crítico de integridad financiera", () => {
    it("NUNCA debería quedar con payment huérfano sin movimiento de caja cuando hay sesión abierta", async () => {
      const terminalId = getTerminalId();
      
      // ✅ FIX: pasar terminal_id
      await CashSessionRepository.create({
        company_id: "10",
        branch_id: "5",
        terminal_id: terminalId,
        user_id: "cashier-uuid-123",
        opening_amount: 100000,
      });

      const { order, totalAmount } = await createServedOrder();

      // Simular fallo catastrófico en el movimiento
      const movementRepo = await import("@/db/repositories/CashMovementRepository");
      const originalCreate = movementRepo.CashMovementRepository.create;
      movementRepo.CashMovementRepository.create = vi
        .fn()
        .mockRejectedValue(new Error("Sesión no encontrada"));

      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: order.local_uuid,
          paymentMethod: "cash",
          amount: totalAmount,
        })
      ).rejects.toThrow();

      movementRepo.CashMovementRepository.create = originalCreate;

      // VERIFICACIÓN CRÍTICA: no debe quedar NADA de la transacción
      const payments = await localDb.select(
        "SELECT * FROM local_payments WHERE order_local_uuid = ?",
        [order.local_uuid]
      );
      const bills = await BillRepository.findByOrder(order.local_uuid);
      const movements = await localDb.select("SELECT * FROM local_cash_movements");
      const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);

      // Todos deben estar vacíos/limpios - el cliente no pagó porque falló el registro
      expect(payments).toHaveLength(0);
      expect(bills[0]?.paid_amount || 0).toBe(0);
      expect(movements).toHaveLength(0);
      expect(updatedOrder?.status).toBe("served");
    });
  });
});
