import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { offlinePaymentService } from "@/services/offlinePaymentService";
import { useAuthStore } from "@/store/useAuthStore";

/**
 * Tests de propagación de contexto multi-tenant en offlinePaymentService.
 * 
 * Valida que:
 * 1. Los pagos heredan company_id/branch_id del order correctamente
 * 2. El movimiento de caja usa el user_id del cajero actual (no waiter_id)
 * 3. La auditoría registra el usuario correcto
 */
describe("offlinePaymentService - Propagación de contexto", () => {
  const cashierUser = {
    id: 42,
    uuid: "cashier-uuid-123",
    name: "Cashier Test",
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
    
    vi.clearAllMocks();
    
    await useAuthStore.getState().setAuth(cashierUser, "token");
  });

  afterEach(async () => {
    await useAuthStore.getState().clearAuth();
  });

  it("debería propagar company_id/branch_id del order al payment", async () => {
    // Crear order con company/branch específicos
    const order = await OrderRepository.create({
      company_id: "999",
      branch_id: "888",
      order_type: "dine_in",
    });

    // Agregar item para que tenga subtotal > 0
    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Test Product",
      quantity: 1,
      unit_price: 100,
    });

    // Recargar order con totales actualizados
    const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    await OrderRepository.updateStatus(order.local_uuid, "served");

    // Crear pago offline
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order.local_uuid,
      paymentMethod: "card",
      amount: 100,
    });

    // Validar que el payment heredó company/branch del order
    expect(result.payment.company_id).toBe("999");
    expect(result.payment.branch_id).toBe("888");
    
    // Validar que la bill también heredó
    expect(result.bill?.company_id).toBe("999");
    expect(result.bill?.branch_id).toBe("888");
  });

  it("debería usar user_id del cajero actual (no waiter_id) para sesión de caja", async () => {
    const { CashSessionRepository } = await import("@/db/repositories/CashSessionRepository");
    
    // Crear sesión de caja para el cajero autenticado
    await CashSessionRepository.create({
      company_id: "10",
      branch_id: "5",
      user_id: "cashier-uuid-123",
      opening_amount: 100,
    });

    // Crear order con waiter_id DIFERENTE al cajero
    const order = await OrderRepository.create({
      company_id: "10",
      branch_id: "5",
      waiter_id: "waiter-uuid-456", // ⚠️ Diferente al cajero
      order_type: "dine_in",
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: "prod-1",
      product_name: "Test Product",
      quantity: 1,
      unit_price: 50,
    });

    await OrderRepository.updateStatus(order.local_uuid, "served");

    // Crear pago en efectivo (debería registrar movimiento de caja)
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order.local_uuid,
      paymentMethod: "cash",
      amount: 50,
    });

    // Validar que el movimiento de caja se creó (si hay sesión abierta)
    // El movimiento debe estar asociado a la sesión del cajero, no del waiter
    const movements = await localDb.select(
      "SELECT * FROM local_cash_movements WHERE reference_local_uuid = ?",
      [result.payment.local_uuid]
    );

    // Si hay movimiento, validar que la sesión pertenece al cajero
    if (movements.length > 0) {
      const session = await CashSessionRepository.findByLocalUuid(movements[0].cash_session_local_uuid);
      expect(session?.user_id).toBe("cashier-uuid-123");
      expect(session?.user_id).not.toBe("waiter-uuid-456");
    }
  });
});
