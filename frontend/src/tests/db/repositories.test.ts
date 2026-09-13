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
import { BillRepository } from "../../db/repositories/BillRepository";
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
    await localDb.execute("DELETE FROM table_local_mutations");
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");

    // Seed de mesas (necesario para tests que pasan table_id)
    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
       VALUES ('table-1', '1', 'Principal', 4, 'available', 'company-1', 'branch-1')`
    );
    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
       VALUES ('table-2', '2', 'Principal', 4, 'available', 'company-1', 'branch-1')`
    );
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
      // Neto: 12000 / 1.19 = 10084
      // IVA: 12000 - 10084 = 1916
      // Total venta: 12000
      expect(updatedOrder?.subtotal).toBe(12000);
      expect(updatedOrder?.net_amount).toBe(10084);
      expect(updatedOrder?.tax_total).toBe(1916);
      expect(updatedOrder?.grand_total).toBe(12000);
      expect(updatedOrder?.amount_due).toBe(12000);

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

  describe("BillRepository", () => {
    it("debería crear una bill local con paid_amount=0 (NO se encola por ADR-009)", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-001",
        subtotal: 10000,  // IVA incluido
        grand_total: 10000,
      });

      expect(bill).toBeDefined();
      expect(bill.local_uuid).toMatch(/^[a-f0-9-]{36}$/);
      expect(bill.bill_number).toBe("BILL-001");
      expect(bill.subtotal).toBe(10000);
      expect(bill.net_amount).toBe(8403);  // 10000 / 1.19
      expect(bill.tax_total).toBe(1597);   // 10000 - 8403
      expect(bill.grand_total).toBe(10000);
      expect(bill.amount_due).toBe(10000);
      expect(bill.paid_amount).toBe(0);
      expect(bill.remaining_amount).toBe(10000);
      expect(bill.status).toBe("open");
      expect(bill.sync_status).toBe("pending");
      expect(bill.idempotency_key).toMatch(/^[a-f0-9-]{36}$/);

      // Verificar que se encoló para sync
      // ADR-009: Las bills NO se encolan en sync_queue.
      // El backend reconstruye bills desde order + payments sincronizados.
      const pending = await SyncQueueRepository.getPending();
      const billQueueItem = pending.find(p => p.entity_local_uuid === bill.local_uuid);
      expect(billQueueItem).toBeUndefined(); // NO debe estar encolada
    });

    it("debería registrar pago y actualizar remaining_amount y status", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-002",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      // Pago parcial de $5000
      const updated = await BillRepository.registerPayment(bill.local_uuid, 5000);

      expect(updated.paid_amount).toBe(5000);
      expect(updated.remaining_amount).toBe(5000);
      expect(updated.status).toBe("partial");

      // Verificar que se encoló el update para sync
      // ADR-009: Las bills NO se encolan en sync_queue.
      // Solo verificar que la bill se actualizó correctamente en SQLite.
      const pending = await SyncQueueRepository.getPending();
      const updates = pending.filter(
        p => p.entity_local_uuid === bill.local_uuid && p.action === "update"
      );
      expect(updates).toHaveLength(0); // NO debe haber updates encolados
    });

    it("debería cambiar status a 'paid' cuando remaining_amount llega a 0", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-003",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      // Pago completo
      const updated = await BillRepository.registerPayment(bill.local_uuid, 11900);

      expect(updated.paid_amount).toBe(11900);
      expect(updated.remaining_amount).toBe(0);
      expect(updated.status).toBe("paid");
    });

    it("debería rechazar pago en bill ya pagada o cancelada", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-004",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      // Pagar completamente
      await BillRepository.registerPayment(bill.local_uuid, 11900);

      // Intentar pagar de nuevo debe lanzar error
      await expect(
        BillRepository.registerPayment(bill.local_uuid, 1000)
      ).rejects.toThrow(/paid|cancelled/i);
    });

    it("debería marcar bill como synced con cloud_id", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-005",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      await BillRepository.markAsSynced(bill.local_uuid, "cloud-bill-123");

      const updated = await BillRepository.findByLocalUuid(bill.local_uuid);
      expect(updated?.cloud_id).toBe("cloud-bill-123");
      expect(updated?.sync_status).toBe("synced");
    });

    it("debería cancelar una bill localmente (NO se encola por ADR-009)", async () => {
      const bill = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-006",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      await BillRepository.cancel(bill.local_uuid, "Cliente canceló");

      const updated = await BillRepository.findByLocalUuid(bill.local_uuid);
      expect(updated?.status).toBe("cancelled");

      // ADR-009: Las bills NO se encolan en sync_queue.
      // Solo verificar que la bill se marcó como cancelled en SQLite.
      const pending = await SyncQueueRepository.getPending();
      const cancelItem = pending.find(
        p => p.entity_local_uuid === bill.local_uuid && 
             p.action === "update" && 
             JSON.parse(p.payload).status === "cancelled"
      );
      expect(cancelItem).toBeUndefined(); // NO debe estar encolada
    });

    it("debería listar bills abiertas por branch", async () => {
      // Crear 3 bills: 2 abiertas, 1 pagada
      await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-A",
        subtotal: 10000,
        tax_total: 1900,
        grand_total: 11900,
      });

      const bill2 = await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-B",
        subtotal: 5000,
        tax_total: 950,
        grand_total: 5950,
      });
      
      await BillRepository.registerPayment(bill2.local_uuid, 5950);

      await BillRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        bill_number: "BILL-C",
        subtotal: 7000,
        tax_total: 1330,
        grand_total: 8330,
      });

      const open = await BillRepository.findOpenByBranch("branch-1");
      
      expect(open).toHaveLength(2);
      expect(open.every(b => b.status === "open" || b.status === "partial")).toBe(true);
    });
  });

});
