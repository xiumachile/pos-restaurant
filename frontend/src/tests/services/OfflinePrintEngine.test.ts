import { describe, it, expect, beforeEach, vi, afterEach } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";
import { OfflinePrintEngine } from "@/services/printing/OfflinePrintEngine";
import { MockPrinterAdapter } from "@/services/printing/adapters/MockPrinterAdapter";
import type { PrinterType } from "@/db/repositories/PrinterConfigRepository";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

// Mock de PrinterConfigRepository para siempre retornar null (fallback a MockAdapter)
vi.mock("@/db/repositories/PrinterConfigRepository", () => ({
  PrinterConfigRepository: {
    getDefault: vi.fn().mockResolvedValue(null),
  },
}));

describe("OfflinePrintEngine", () => {
  let engine: OfflinePrintEngine;
  let mockAdapter: MockPrinterAdapter;

  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM local_print_jobs");

    mockAdapter = new MockPrinterAdapter();
    engine = new OfflinePrintEngine({
      fallbackAdapter: mockAdapter,
      pollIntervalMs: 10000, // Alto para evitar que el interval interfiera con tests
    });
    vi.clearAllMocks();
  });

  afterEach(() => {
    engine.stop();
  });

  const basePayload = {
    job_type: "receipt" as const,
    entity_type: "bill",
    entity_uuid: "bill-uuid-123",
    payload: { bill_number: "42-1", total: 15000 },
    escpos_base64: btoa("TEST ESCPOS DATA"),
    printer_type: "receipt" as PrinterType,
    printer_name: "test-printer",
    company_id: "company-1",
    branch_id: "branch-1",
    user_id: "user-1",
    reference_number: "Cuenta #42-1",
    max_attempts: 3, // Default: permite retries
  };

  describe("start/stop", () => {
    it("inicia y detiene el engine correctamente", () => {
      engine.start();
      expect(engine.running).toBe(true);

      engine.stop();
      expect(engine.running).toBe(false);
    });

    it("no inicia dos veces (warning con emoji)", () => {
      const consoleSpy = vi.spyOn(console, "log");

      engine.start();
      engine.start(); // Segundo intento

      expect(consoleSpy).toHaveBeenCalledWith(
        "[OfflinePrintEngine] ⚠️ Ya está corriendo"
      );
    });
  });

  describe("processJobs (invocación directa)", () => {
    it("procesa job pendiente y lo marca como completado", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);

      // Llamada directa con await (determinístico)
      await engine.processJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("completed");
      expect(job?.printed_at).toBeTruthy();
    });

    it("procesa múltiples jobs en orden FIFO", async () => {
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      await new Promise((r) => setTimeout(r, 10));
      const uuid2 = await LocalPrintJobRepository.create(basePayload);

      await engine.processJobs();

      const job1 = await LocalPrintJobRepository.findByLocalUuid(uuid1);
      const job2 = await LocalPrintJobRepository.findByLocalUuid(uuid2);
      expect(job1?.status).toBe("completed");
      expect(job2?.status).toBe("completed");
    });

    it("marca job como failed si no tiene bytes ESC/POS", async () => {
      // Usar max_attempts = 1 para que el primer fallo sea permanente
      const uuid = await LocalPrintJobRepository.create({
        ...basePayload,
        escpos_base64: undefined as any,
        max_attempts: 1,
      });

      await engine.processJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("failed");
      expect(job?.error_message).toContain("bytes ESC/POS");
    });

    it("no imprime si no hay jobs pendientes", async () => {
      const printSpy = vi.spyOn(mockAdapter, "print");

      await engine.processJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });

    it("usa el fallback adapter cuando no hay config", async () => {
      const printSpy = vi.spyOn(mockAdapter, "print");

      await LocalPrintJobRepository.create(basePayload);
      await engine.processJobs();

      expect(printSpy).toHaveBeenCalledTimes(1);
      expect(printSpy.mock.calls[0][1]).toEqual({ type: "serial" });
    });

    it("recupera jobs abandonados antes de procesar", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsPrinting(uuid);
      
      // Forzar updated_at antiguo (más de 2 min) - campo que usa recoverAbandonedPrinting
      await localDb.execute(
        `UPDATE local_print_jobs 
         SET updated_at = datetime('now', '-3 minutes')
         WHERE local_uuid = ?`,
        [uuid]
      );

      await engine.processJobs();

      // Debió ser recuperado a pending y luego procesado a completed
      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("completed");
    });
  });

  describe("manejo de errores", () => {
    it("continúa procesando jobs si uno falla", async () => {
      // Primer job con max_attempts = 1 (falla permanente)
      const uuid1 = await LocalPrintJobRepository.create({
        ...basePayload,
        escpos_base64: undefined as any, // Este fallará
        max_attempts: 1,
      });
      await new Promise((r) => setTimeout(r, 10));
      const uuid2 = await LocalPrintJobRepository.create(basePayload); // Este debe procesarse

      await engine.processJobs();

      const job1 = await LocalPrintJobRepository.findByLocalUuid(uuid1);
      const job2 = await LocalPrintJobRepository.findByLocalUuid(uuid2);
      expect(job1?.status).toBe("failed");
      expect(job2?.status).toBe("completed");
    });

    it("si adapter.print() lanza error, marca como failed (con max_attempts=1)", async () => {
      mockAdapter.print = vi.fn().mockRejectedValue(new Error("Impresora offline"));
      
      // Usar max_attempts = 1 para que el primer fallo sea permanente
      const uuid = await LocalPrintJobRepository.create({
        ...basePayload,
        max_attempts: 1,
      });

      await engine.processJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("failed");
      expect(job?.error_message).toContain("Impresora offline");
    });

    it("si adapter.print() lanza error con max_attempts > 1, vuelve a pending (retry)", async () => {
      mockAdapter.print = vi.fn().mockRejectedValue(new Error("Impresora offline"));
      
      // max_attempts = 3 (default): primer fallo vuelve a pending
      const uuid = await LocalPrintJobRepository.create(basePayload);

      await engine.processJobs();

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      // Debe volver a pending para reintentar
      expect(job?.status).toBe("pending");
      expect(job?.attempts).toBe(1); // Incrementó attempts
    });
  });
});
