import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { kitchenPrintService } from "@/services/kitchenPrintService";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";

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

describe("kitchenPrintService", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");

    // Seed mesa
    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status)
       VALUES ('table-1', '7', 'Principal', 4, 'available')`
    );

    vi.clearAllMocks();
  });

  describe("sendOrderToKitchen", () => {
    it("falla si order no existe", async () => {
      await expect(
        kitchenPrintService.sendOrderToKitchen("non-existent-uuid")
      ).rejects.toThrow("Order no encontrada");
    });

    it("falla si order no tiene items", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await expect(
        kitchenPrintService.sendOrderToKitchen(order.local_uuid)
      ).rejects.toThrow("no tiene items");
    });

    it("crea print job con escpos_base64 válido", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
        waiter_name: "María",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Pad Thai",
        quantity: 2,
        unit_price: 8500,
        notes: "Sin maní",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-2",
        product_name: "Spring Rolls",
        quantity: 1,
        unit_price: 4500,
      });

      const result = await kitchenPrintService.sendOrderToKitchen(order.local_uuid);

      expect(result.success).toBe(true);
      expect(result.itemsCount).toBe(2);
      expect(result.printJobUuid).toBeTruthy();
    });

    it("el escpos_base64 contiene datos del order", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
        waiter_name: "María",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Pad Thai",
        quantity: 2,
        unit_price: 8500,
        notes: "Sin maní",
      });

      await kitchenPrintService.sendOrderToKitchen(order.local_uuid);

      const jobs = await LocalPrintJobRepository.getAll();
      const kitchenJob = jobs.find((j) => j.job_type === "kitchen_command");

      expect(kitchenJob).toBeDefined();
      expect(kitchenJob?.escpos_base64).toBeTruthy();

      const decoded = atob(kitchenJob!.escpos_base64!);
      expect(decoded).toContain("COCINA");
      expect(decoded).toContain("Pad Thai");
      expect(decoded).toContain("Sin maní");
      expect(decoded).toContain("María");
    });

    it("el payload contiene datos completos del order", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
        waiter_name: "María",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Pad Thai",
        quantity: 2,
        unit_price: 8500,
        notes: "Sin maní",
      });

      await kitchenPrintService.sendOrderToKitchen(order.local_uuid);

      const jobs = await LocalPrintJobRepository.getAll();
      const kitchenJob = jobs.find((j) => j.job_type === "kitchen_command");

      expect(kitchenJob?.entity_type).toBe("order");
      expect(kitchenJob?.entity_uuid).toBe(order.local_uuid);
      expect(kitchenJob?.printer_name).toBe("kitchen-printer");
      expect(kitchenJob?.printer_type).toBe("kitchen");
      expect(kitchenJob?.reference_number).toContain("Orden #");

      const payload = JSON.parse(kitchenJob!.payload);
      expect(payload.items).toHaveLength(1);
      expect(payload.items[0].name).toBe("Pad Thai");
      expect(payload.items[0].quantity).toBe(2);
      expect(payload.items[0].notes).toBe("Sin maní");
      expect(payload.waiterName).toBe("María");
    });

    it("genera idempotency_key único por cada llamada (permite reimpresión)", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Test Item",
        quantity: 1,
        unit_price: 1000,
      });

      const result1 = await kitchenPrintService.sendOrderToKitchen(order.local_uuid);
      
      // Esperar un poco para que el timestamp sea diferente
      await new Promise((r) => setTimeout(r, 10));
      
      const result2 = await kitchenPrintService.sendOrderToKitchen(order.local_uuid);

      expect(result1.printJobUuid).not.toBe(result2.printJobUuid);
      
      const jobs = await LocalPrintJobRepository.getAll();
      expect(jobs).toHaveLength(2);
    });
  });

  describe("reprintKitchenTicket", () => {
    it("genera nuevo ticket para el mismo order", async () => {
      const order = await OrderRepository.create({
        table_id: "table-1",
        order_type: "dine_in",
        company_id: "company-1",
        branch_id: "branch-1",
      });

      await OrderRepository.addItem(order.local_uuid, {
        product_id: "prod-1",
        product_name: "Test",
        quantity: 1,
        unit_price: 1000,
      });

      const result = await kitchenPrintService.reprintKitchenTicket(order.local_uuid);

      expect(result.success).toBe(true);
      expect(result.itemsCount).toBe(1);
    });
  });
});
