import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";
import { OfflinePrintEngine } from "@/services/printing/OfflinePrintEngine";
import { MockPrinterAdapter } from "@/services/printing/adapters/MockPrinterAdapter";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

describe("OfflinePrintEngine", () => {
  let engine: OfflinePrintEngine;
  let mockAdapter: MockPrinterAdapter;

  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");
    
    mockAdapter = new MockPrinterAdapter();
    engine = new OfflinePrintEngine({ adapter: mockAdapter, pollIntervalMs: 100 });
    vi.clearAllMocks();
  });

  const basePayload = {
    job_type: "receipt" as const,
    entity_type: "bill",
    entity_uuid: "bill-uuid-123",
    payload: { bill_number: "42-1", total: 15000 },
    escpos_base64: btoa("TEST ESCPOS DATA"),
    company_id: "company-1",
    branch_id: "branch-1",
    user_id: "user-1",
    reference_number: "Cuenta #42-1",
  };

  describe("processPendingJobs", () => {
    it("procesa job pendiente y lo marca como completado", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);

      await engine.processPendingJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("completed");
      expect(job?.printed_at).toBeTruthy();
    });

    it("procesa múltiples jobs en orden FIFO", async () => {
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      await new Promise(r => setTimeout(r, 10));
      const uuid2 = await LocalPrintJobRepository.create(basePayload);

      await engine.processPendingJobs();

      const job1 = await LocalPrintJobRepository.findByLocalUuid(uuid1);
      const job2 = await LocalPrintJobRepository.findByLocalUuid(uuid2);
      expect(job1?.status).toBe("completed");
      expect(job2?.status).toBe("completed");
    });

    it("marca job como failed si no tiene bytes ESC/POS", async () => {
      const uuid = await LocalPrintJobRepository.create({
        ...basePayload,
        escpos_base64: undefined, // Sin bytes
      });

      await engine.processPendingJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("failed");
      expect(job?.error_message).toContain("bytes ESC/POS");
    });

    it("no hace nada si no hay jobs pendientes", async () => {
      const printSpy = vi.spyOn(mockAdapter, "print");

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });

    it("incrementa attempts al procesar job", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);

      await engine.processPendingJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.attempts).toBe(1);
    });
  });

  describe("start/stop", () => {
    it("inicia y detiene el engine correctamente", () => {
      engine.start();
      expect(engine["isRunning"]).toBe(true);

      engine.stop();
      expect(engine["isRunning"]).toBe(false);
    });

    it("no inicia dos veces", () => {
      const consoleSpy = vi.spyOn(console, "warn");

      engine.start();
      engine.start(); // Segundo intento

      expect(consoleSpy).toHaveBeenCalledWith("[OfflinePrintEngine] Ya está corriendo");
      engine.stop();
    });
  });
});
