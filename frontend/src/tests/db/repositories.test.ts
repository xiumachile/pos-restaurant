import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

// Mock del plugin SQL (igual que en localDb.test.ts)
vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";

describe("Repositorios locales", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    // Limpiar tablas entre tests
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_orders");
  });

  describe("OrderRepository", () => {
    it("debería crear un pedido con idempotency_key", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        table_id: "table-1",
        waiter_name: "Juan",
      });

      expect(order).toBeDefined();
      expect(order.local_uuid).toMatch(/^[a-f0-9-]{36}$/);
      expect(order.idempotency_key).toMatch(/^[a-f0-9-]{36}$/);
      expect(order.order_number).toContain("TEMP-");
      expect(order.status).toBe("confirmed");
      expect(order.sync_status).toBe("pending");
      expect(order.waiter_name).toBe("Juan");
    });

    it("debería agregar items y recalcular totales con IVA 19%", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Hamburguesa",
        quantity: 2,
        unit_price: 5000,
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-2",
        product_name: "Papas",
        quantity: 1,
        unit_price: 2000,
      });

      const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
      
      // Subtotal: 2*5000 + 1*2000 = 12000
      // Tax (19%): 2280
      // Total: 14280
      expect(updatedOrder?.subtotal).toBe(12000);
      expect(updatedOrder?.tax_total).toBe(2280);
      expect(updatedOrder?.grand_total).toBe(14280);

      const items = await OrderRepository.findItemsByOrderLocalUuid(order.local_uuid);
      expect(items).toHaveLength(2);
    });

    it("debería actualizar el estado del pedido", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await OrderRepository.updateStatus(order.local_uuid, "confirmed");

      const updated = await OrderRepository.findByLocalUuid(order.local_uuid);
      expect(updated?.status).toBe("confirmed");
    });

    it("debería marcar como sincronizado con cloud_id", async () => {
      const order = await OrderRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await OrderRepository.markAsSynced(order.local_uuid, "cloud-order-123");

      const updated = await OrderRepository.findByLocalUuid(order.local_uuid);
      expect(updated?.cloud_id).toBe("cloud-order-123");
      expect(updated?.sync_status).toBe("synced");
    });
  });

  describe("SyncQueueRepository", () => {
    it("debería encolar eventos y recuperarlos como pendientes", async () => {
      await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "order",
        entity_local_uuid: "order-uuid-1",
        action: "create",
        payload: { order_number: "TEST-001" },
      });

      const pending = await SyncQueueRepository.getPending();
      expect(pending).toHaveLength(1);
      expect(pending[0].entity_type).toBe("order");
      expect(pending[0].action).toBe("create");
      expect(JSON.parse(pending[0].payload)).toEqual({ order_number: "TEST-001" });
    });

    it("debería contar eventos pendientes", async () => {
      await SyncQueueRepository.enqueue({
        company_id: "c1",
        branch_id: "b1",
        entity_type: "order",
        entity_local_uuid: "o1",
        action: "create",
        payload: {},
      });

      await SyncQueueRepository.enqueue({
        company_id: "c1",
        branch_id: "b1",
        entity_type: "payment",
        entity_local_uuid: "p1",
        action: "create",
        payload: {},
      });

      const count = await SyncQueueRepository.countPending();
      expect(count).toBe(2);
    });

    it("debería marcar eventos como sincronizados", async () => {
      await SyncQueueRepository.enqueue({
        company_id: "c1",
        branch_id: "b1",
        entity_type: "order",
        entity_local_uuid: "o1",
        action: "create",
        payload: {},
      });

      const pending = await SyncQueueRepository.getPending();
      const id = pending[0].id;

      await SyncQueueRepository.markAsSynced(id);

      const after = await SyncQueueRepository.getPending();
      expect(after).toHaveLength(0);
    });
  });
});
