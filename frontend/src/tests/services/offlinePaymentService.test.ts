import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import {
  offlinePaymentService,
  OfflinePaymentError,
} from "../../services/offlinePaymentService";

describe("offlinePaymentService", () => {
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
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");
    await localDb.execute("DELETE FROM table_local_mutations");

    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
       VALUES ('table-1', '1', 'Principal', 4, 'occupied', 'company-1', 'branch-1')`
    );
  });

  describe("createPaymentOffline - happy path", () => {
    it("debería crear pago offline completo con auto-creación de bill", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        table_id: "table-1",
        waiter_name: "Juan",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 2,
        unit_price: 5000,
      });

      await OrderRepository.updateStatus(order.local_uuid, "served");

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: 10000,
      });

      expect(result.payment).toBeDefined();
      expect(result.payment.amount).toBe(10000);
      expect(result.payment.status).toBe("pending");

      expect(result.bill).toBeDefined();
      expect(result.bill!.status).toBe("paid");
      expect(result.bill!.paid_amount).toBe(10000);
      expect(result.bill!.remaining_amount).toBe(0);

      expect(result.orderPaid).toBe(true);
      expect(result.orderStatusUpdated).toBe(true);
      expect(result.tableReleased).toBe(true);

      const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
      expect(updatedOrder?.status).toBe("paid");

      const tables = await localDb.select<{ status: string; current_order_uuid: string | null }>(
        "SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?",
        ["table-1"]
      );
      expect(tables[0].status).toBe("available");
      expect(tables[0].current_order_uuid).toBeNull();
    });

    it("debería permitir pago parcial (split payment)", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        table_id: "table-1",
      });
      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 2,
        unit_price: 5000,
      });
      await OrderRepository.updateStatus(order.local_uuid, "served");

      const result1 = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: 5000,
      });

      expect(result1.bill!.status).toBe("partial");
      expect(result1.bill!.paid_amount).toBe(5000);
      expect(result1.orderPaid).toBe(false);
      expect(result1.tableReleased).toBe(false);

      const result2 = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "card",
        amount: 5000,
      });

      expect(result2.bill!.status).toBe("paid");
      expect(result2.orderPaid).toBe(true);
      expect(result2.orderStatusUpdated).toBe(true);
      expect(result2.tableReleased).toBe(true);
    });

    it("debería manejar order sin mesa (takeout/delivery)", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });
      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 1,
        unit_price: 5000,
      });
      await OrderRepository.updateStatus(order.local_uuid, "ready_for_pickup");

      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: 5000,
      });

      expect(result.orderPaid).toBe(true);
      expect(result.orderStatusUpdated).toBe(true);
      expect(result.tableReleased).toBe(false);
    });
  });

  describe("createPaymentOffline - validaciones", () => {
    it("debería rechazar order en estado no pagable", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: order.local_uuid,
          paymentMethod: "cash",
          amount: 1000,
        })
      ).rejects.toThrow(OfflinePaymentError);
    });

    it("debería rechazar amount que excede remaining", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });
      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 1,
        unit_price: 5000,
      });
      await OrderRepository.updateStatus(order.local_uuid, "served");

      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: order.local_uuid,
          paymentMethod: "cash",
          amount: 99999,
        })
      ).rejects.toThrow(/AMOUNT_EXCEEDS_REMAINING/);
    });

    it("debería rechazar amount negativo", async () => {
      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: "any-uuid",
          paymentMethod: "cash",
          amount: -100,
        })
      ).rejects.toThrow(/INVALID_AMOUNT/);
    });

    it("debería rechazar order inexistente", async () => {
      await expect(
        offlinePaymentService.createPaymentOffline({
          orderLocalUuid: "non-existent-uuid",
          paymentMethod: "cash",
          amount: 1000,
        })
      ).rejects.toThrow(/ORDER_NOT_FOUND/);
    });
  });

  describe("createPaymentOffline - sync queue", () => {
    it("debería encolar payment y table update (bill NO se encola por ADR-009)", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        table_id: "table-1",
      });
      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 1,
        unit_price: 5000,
      });
      await OrderRepository.updateStatus(order.local_uuid, "served");

      await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: 5000,
      });

      const pending = await SyncQueueRepository.getPending();

      // ADR-009: bill NO se encola (backend la reconstruye desde order + payment)
      // Solo payment y table update se encolan
      expect(pending.length).toBeGreaterThanOrEqual(2);

      // Verificar que NO hay items de bill (ADR-009)
      const billItems = pending.filter(p => p.entity_type === "bill");
      expect(billItems).toHaveLength(0);

      const paymentItems = pending.filter(p => p.entity_type === "payment");
      expect(paymentItems).toHaveLength(1);

      const tableItems = pending.filter(p => p.entity_type === "table_status");
      expect(tableItems).toHaveLength(1);
      expect(tableItems[0].action).toBe("update");
    });
  });

  describe("getOrderPaymentStatus", () => {
    it("debería retornar estado completo de pago", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });
      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 1,
        unit_price: 5000,
      });
      await OrderRepository.updateStatus(order.local_uuid, "served");

      const before = await offlinePaymentService.getOrderPaymentStatus(order.local_uuid);
      expect(before.bills).toHaveLength(0);
      expect(before.payments).toHaveLength(0);
      expect(before.isPaid).toBe(false);

      await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "cash",
        amount: 2000,
      });

      const partial = await offlinePaymentService.getOrderPaymentStatus(order.local_uuid);
      expect(partial.bills).toHaveLength(1);
      expect(partial.payments).toHaveLength(1);
      expect(partial.totalPaid).toBe(2000);
      expect(partial.isPaid).toBe(false);

      await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: order.local_uuid,
        paymentMethod: "card",
        amount: partial.totalRemaining,
      });

      const complete = await offlinePaymentService.getOrderPaymentStatus(order.local_uuid);
      expect(complete.payments).toHaveLength(2);
      expect(complete.totalRemaining).toBe(0);
      expect(complete.isPaid).toBe(true);
    });

    it("debería retornar estado vacío para order inexistente", async () => {
      const result = await offlinePaymentService.getOrderPaymentStatus("non-existent");
      expect(result.order).toBeNull();
      expect(result.isPaid).toBe(false);
    });
  });
});

