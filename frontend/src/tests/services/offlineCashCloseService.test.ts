import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { offlineCashCloseService } from "@/services/offlineCashCloseService";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { CashMovementRepository } from "@/db/repositories/CashMovementRepository";
import { PaymentRepository } from "@/db/repositories/PaymentRepository";
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

describe("offlineCashCloseService", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");
    vi.clearAllMocks();
  });

  describe("closeSession", () => {
    it("falla si sesión no existe", async () => {
      await expect(
        offlineCashCloseService.closeSession({
          sessionUuid: "non-existent",
          closingAmount: 100000,
        })
      ).rejects.toThrow("not found");
    });

    it("falla si sesión ya está cerrada", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      await localDb.execute(
        `UPDATE local_cash_sessions SET status = 'closed' WHERE local_uuid = ?`,
        [session.local_uuid]
      );

      await expect(
        offlineCashCloseService.closeSession({
          sessionUuid: session.local_uuid,
          closingAmount: 100000,
        })
      ).rejects.toThrow("already closed");
    });

    it("falla si closingAmount es negativo", async () => {
      await expect(
        offlineCashCloseService.closeSession({
          sessionUuid: "any",
          closingAmount: -100,
        })
      ).rejects.toThrow("non-negative");
    });

    it("cierra sesión y genera print job con escpos_base64", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 150000,
        notes: "Cierre normal",
      });

      expect(result.sessionUuid).toBe(session.local_uuid);
      expect(result.actualAmount).toBe(150000);
      expect(result.printJobUuid).toBeTruthy();

      const updatedSession = await CashSessionRepository.findByLocalUuid(session.local_uuid);
      expect(updatedSession?.status).toBe("closed");
      expect(updatedSession?.closing_amount).toBe(150000);
    });

    it("el escpos_base64 contiene datos del cierre", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 100000,
      });

      const jobs = await LocalPrintJobRepository.getAll();
      const cashJob = jobs.find((j) => j.entity_type === "cash_session");

      expect(cashJob).toBeDefined();
      expect(cashJob?.escpos_base64).toBeTruthy();

      const decoded = atob(cashJob!.escpos_base64!);
      expect(decoded).toContain("CIERRE DE CAJA");
      expect(decoded).toContain("Juan Pérez");
      expect(decoded).toContain("ARQUEO");
    });

    it("calcula diferencia correctamente", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      // Abrir la sesión (50000) + no hay ventas → esperado = 50000
      // Contado = 60000 → diferencia = +10000 (sobrante)
      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 60000,
      });

      expect(result.difference).toBe(10000);
      expect(result.expectedAmount).toBe(50000);
      expect(result.actualAmount).toBe(60000);
    });

    it("incluye movimientos en la copia", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      // Llamada correcta: (cashSessionLocalUuid, payload)
      await CashMovementRepository.create(session.local_uuid, {
        type: "withdrawal",
        amount: 5000,
        reason: "Cambio",
      });

      await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 100000,
      });

      const jobs = await LocalPrintJobRepository.getAll();
      const cashJob = jobs.find((j) => j.entity_type === "cash_session");

      expect(cashJob).toBeDefined();
      const payload = JSON.parse(cashJob!.payload);
      expect(payload.movements).toHaveLength(1);
      expect(payload.movements[0].type).toBe("withdrawal");
      expect(payload.movements[0].reason).toBe("Cambio");
    });
  });

    it("clasifica ventas por método de pago real (cash/card/transfer)", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      // Crear 3 payments con diferentes métodos
      const cashPayment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        amount: 100000,
        payment_method: "cash",
      });

      const cardPayment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        amount: 180000,
        payment_method: "card",
      });

      const transferPayment = await PaymentRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        amount: 70000,
        payment_method: "transfer",
      });

      // Crear movimientos de caja referenciando los payments
      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 100000,
        reason: "Pago en efectivo",
        reference_type: "payment",
        reference_local_uuid: cashPayment.local_uuid,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 180000,
        reason: "Pago con tarjeta",
        reference_type: "payment",
        reference_local_uuid: cardPayment.local_uuid,
      });

      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 70000,
        reason: "Transferencia",
        reference_type: "payment",
        reference_local_uuid: transferPayment.local_uuid,
      });

      await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 400000,
      });

      // Verificar que la copia de caja tiene clasificación correcta
      const jobs = await LocalPrintJobRepository.getAll();
      const cashJob = jobs.find((j) => j.entity_type === "cash_session");

      expect(cashJob).toBeDefined();
      const payload = JSON.parse(cashJob!.payload);
      
      expect(payload.cashSales).toBe(100000);
      expect(payload.cardSales).toBe(180000);
      expect(payload.transferSales).toBe(70000);
      expect(payload.totalSales).toBe(350000);
    });

    it("maneja movimientos sin payment asociado (edge case)", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan Pérez",
        opening_amount: 50000,
      });

      // Crear movimiento con reference_local_uuid inexistente
      await CashMovementRepository.create(session.local_uuid, {
        type: "payment",
        amount: 50000,
        reason: "Pago orphan",
        reference_type: "payment",
        reference_local_uuid: "non-existent-uuid",
      });

      // No debe fallar, solo warn en consola
      const result = await offlineCashCloseService.closeSession({
        sessionUuid: session.local_uuid,
        closingAmount: 100000,
      });

      expect(result.sessionUuid).toBe(session.local_uuid);

      // El movimiento orphan no debe contar en ventas
      const jobs = await LocalPrintJobRepository.getAll();
      const cashJob = jobs.find((j) => j.entity_type === "cash_session");
      const payload = JSON.parse(cashJob!.payload);
      
      expect(payload.totalSales).toBe(0);
    });

});
