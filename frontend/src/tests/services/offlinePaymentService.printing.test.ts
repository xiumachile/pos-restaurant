import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { offlinePaymentService } from "@/services/offlinePaymentService";
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

describe("offlinePaymentService - Impresión offline", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");

    // Seed mesa
    await localDb.execute(
      `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
       VALUES ('table-1', '1', 'Principal', 4, 'available', 'company-1', 'branch-1')`
    );

    // Seed order con items
    await OrderRepository.create({
      table_id: "table-1",
      order_type: "dine_in",
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
    });

    const order = await localDb.selectOne<{ local_uuid: string }>(
      "SELECT local_uuid FROM local_orders LIMIT 1"
    );

    // Agregar items
    await OrderRepository.addItem(order!.local_uuid, {
      product_id: "prod-1",
      product_name: "Pad Thai",
      quantity: 2,
      unit_price: 8500,
      notes: "Sin maní",
    });

    await OrderRepository.addItem(order!.local_uuid, {
      product_id: "prod-2",
      product_name: "Spring Rolls",
      quantity: 1,
      unit_price: 4500,
    });

    // Marcar orden como served (pagable)
    await OrderRepository.updateStatus(order!.local_uuid, "served");

    vi.clearAllMocks();
  });

  it("al pagar, crea print job con escpos_base64 válido", async () => {
    const order = await localDb.selectOne<{ local_uuid: string }>(
      "SELECT local_uuid FROM local_orders LIMIT 1"
    );

    await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      paymentMethod: "cash",
      amount: 21500,
    });

    const jobs = await LocalPrintJobRepository.getAll();
    expect(jobs.length).toBeGreaterThan(0);

    const receiptJob = jobs.find((j) => j.job_type === "receipt");
    expect(receiptJob).toBeDefined();
    expect(receiptJob?.escpos_base64).toBeTruthy();
    expect(receiptJob!.escpos_base64!.length).toBeGreaterThan(100);
  });

  it("el escpos_base64 es decodificable y contiene texto esperado", async () => {
    const order = await localDb.selectOne<{ local_uuid: string }>(
      "SELECT local_uuid FROM local_orders LIMIT 1"
    );

    await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      paymentMethod: "cash",
      amount: 21500,
    });

    const jobs = await LocalPrintJobRepository.getAll();
    const receiptJob = jobs.find((j) => j.job_type === "receipt");

    // Decodificar base64
    const decoded = atob(receiptJob!.escpos_base64!);

    // Debe contener datos del order
    expect(decoded).toContain("Pad Thai");
    expect(decoded).toContain("Spring Rolls");
    expect(decoded).toContain("Sin maní");
    expect(decoded).toContain("Efectivo");
  });

  it("el payload del print job contiene todos los datos del receipt", async () => {
    const order = await localDb.selectOne<{ local_uuid: string }>(
      "SELECT local_uuid FROM local_orders LIMIT 1"
    );

    await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      paymentMethod: "cash",
      amount: 21500,
    });

    const jobs = await LocalPrintJobRepository.getAll();
    const receiptJob = jobs.find((j) => j.job_type === "receipt");

    const payload = JSON.parse(receiptJob!.payload);

    expect(payload.billNumber).toBeTruthy();
    expect(payload.items).toHaveLength(2);
    expect(payload.items[0].name).toBe("Pad Thai");
    expect(payload.items[0].quantity).toBe(2);
    expect(payload.items[0].notes).toBe("Sin maní");
    expect(payload.paymentMethod).toBe("cash");
    expect(payload.cashierName).toBe("Juan Pérez");
    expect(payload.grandTotal).toBeGreaterThan(0);
  });

  it("funciona aunque no se pueda generar el receipt (pago sigue siendo válido)", async () => {
    const order = await localDb.selectOne<{ local_uuid: string }>(
      "SELECT local_uuid FROM local_orders LIMIT 1"
    );

    // Espiar LocalPrintJobRepository.create y forzar error en la primera llamada
    vi.spyOn(LocalPrintJobRepository, "create").mockRejectedValueOnce(
      new Error("DB error")
    );

    // No debe lanzar excepción (es no-crítico)
    const result = await offlinePaymentService.createPaymentOffline({
      orderLocalUuid: order!.local_uuid,
      paymentMethod: "cash",
      amount: 21500,
    });

    // El pago debe seguir siendo exitoso
    expect(result.payment).toBeTruthy();
    expect(result.bill).toBeTruthy();
  });
});
