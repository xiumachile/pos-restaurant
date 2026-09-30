import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { PrintEngine } from "@/services/printing/PrintEngine";
import { printJobsApi, type PrintJob } from "@/services/printJobsApi";

vi.mock("@/services/printJobsApi");
const mockPrintJobsApi = vi.mocked(printJobsApi);

// [AUDIT FIX] Mockear useAuthStore para que isAuthenticated = true
vi.mock("@/store/useAuthStore", () => ({
  useAuthStore: {
    getState: () => ({
      isAuthenticated: true,
      token: "test-token",
      user: { id: "user-1", company_id: "company-1", branch_id: "branch-1" },
    }),
  },
}));

describe("PrintEngine - Prevención de Ghost Claim (doble impresión)", () => {
  let engine: PrintEngine;
  let printSpy: ReturnType<typeof vi.fn>;
  const CLIENT_ID = "test-client-id";

  beforeEach(() => {
    vi.clearAllMocks();
    printSpy = vi.fn().mockResolvedValue({ success: true });
    const mockAdapter = {
      print: printSpy,
      testConnection: vi.fn().mockResolvedValue({ connected: true }),
      type: "tcp" as const,
    };
    engine = new PrintEngine({
      pollIntervalMs: 100000,
      clientId: CLIENT_ID,
      adapter: mockAdapter as any,
    });
  });

  afterEach(() => {
    engine.stop();
  });

  const buildJob = (uuid: string): PrintJob => ({
    uuid,
    job_type: "receipt",
    printer_uuid: "printer-1",
    printer_name: "Receipt Printer",
    printer_connection: { type: "tcp", host: "192.168.1.100", port: 9100 },
    status: "pending",
    attempts: 0,
    max_attempts: 3,
    bytes_size: 100,
    created_at: new Date().toISOString(),
    escpos_base64: btoa("TEST ESCPOS DATA"),
  });

  describe("Escenario multi-terminal (Claim conflict)", () => {
    it("NO debe imprimir localmente cuando claim lanza error 409 Conflict", async () => {
      const job = buildJob("job-123");
      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);

      const conflictError = Object.assign(new Error("Job already claimed"), {
        response: { status: 409, data: { error: "Job already claimed by another terminal" } },
        isAxiosError: true,
      });
      mockPrintJobsApi.claim.mockRejectedValueOnce(conflictError);
      mockPrintJobsApi.fail.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
      expect(mockPrintJobsApi.complete).not.toHaveBeenCalled();
    });

    it("NO debe imprimir cuando claim lanza error 404 (job inexistente)", async () => {
      const job = buildJob("job-456");
      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);

      const notFoundError = Object.assign(new Error("Not found"), {
        response: { status: 404, data: { error: "Print job not found" } },
        isAxiosError: true,
      });
      mockPrintJobsApi.claim.mockRejectedValueOnce(notFoundError);
      mockPrintJobsApi.fail.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });

    it("NO debe imprimir cuando claim lanza error 423 Locked", async () => {
      const job = buildJob("job-789");
      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);

      const lockedError = Object.assign(new Error("Locked"), {
        response: { status: 423 },
        isAxiosError: true,
      });
      mockPrintJobsApi.claim.mockRejectedValueOnce(lockedError);
      mockPrintJobsApi.fail.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });

    it("NO debe imprimir cuando claim lanza error 500 (error de servidor)", async () => {
      const job = buildJob("job-error");
      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);

      const serverError = Object.assign(new Error("Server Error"), {
        response: { status: 500 },
        isAxiosError: true,
      });
      mockPrintJobsApi.claim.mockRejectedValueOnce(serverError);
      mockPrintJobsApi.fail.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });
  });

  describe("Escenario exitoso (Claim aprobado)", () => {
    it("SÍ debe imprimir cuando claim retorna el job exitosamente", async () => {
      const job = buildJob("job-success");
      const claimedJob = { ...job, status: "printing" as const, claimed_by: CLIENT_ID };

      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);
      mockPrintJobsApi.claim.mockResolvedValueOnce(claimedJob);
      mockPrintJobsApi.getWithBytes.mockResolvedValueOnce({
        ...claimedJob,
        escpos_base64: btoa("TEST BYTES"),
      });
      mockPrintJobsApi.complete.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).toHaveBeenCalledTimes(1);
      expect(mockPrintJobsApi.complete).toHaveBeenCalledWith("job-success");
    });
  });

  describe("Errores de red en claim", () => {
    it("NO debe imprimir cuando claim lanza excepción de red sin response", async () => {
      const job = buildJob("job-network-error");
      mockPrintJobsApi.listPending.mockResolvedValueOnce([job]);

      const networkError = Object.assign(new Error("Network Error"), {
        isAxiosError: true,
        code: "ECONNREFUSED",
      });
      mockPrintJobsApi.claim.mockRejectedValueOnce(networkError);
      mockPrintJobsApi.fail.mockResolvedValueOnce(undefined);

      await engine.processPendingJobs();

      expect(printSpy).not.toHaveBeenCalled();
    });
  });

  describe("Múltiples jobs en cola", () => {
    it("debe imprimir solo los jobs con claim exitoso y saltar los con conflicto", async () => {
      const jobA = buildJob("job-a");
      const jobB = buildJob("job-b");
      const jobC = buildJob("job-c");

      mockPrintJobsApi.listPending.mockResolvedValueOnce([jobA, jobB, jobC]);

      mockPrintJobsApi.claim
        .mockResolvedValueOnce({ ...jobA, status: "printing" as const })
        .mockRejectedValueOnce(
          Object.assign(new Error("Claimed"), {
            response: { status: 409, data: { error: "claimed" } },
            isAxiosError: true,
          })
        )
        .mockResolvedValueOnce({ ...jobC, status: "printing" as const });

      mockPrintJobsApi.getWithBytes
        .mockResolvedValueOnce({ ...jobA, escpos_base64: btoa("A") })
        .mockResolvedValueOnce({ ...jobC, escpos_base64: btoa("C") });

      mockPrintJobsApi.complete.mockResolvedValue(undefined);
      mockPrintJobsApi.fail.mockResolvedValue(undefined);

      await engine.processPendingJobs();

      expect(printSpy).toHaveBeenCalledTimes(2);
      expect(mockPrintJobsApi.complete).toHaveBeenCalledWith("job-a");
      expect(mockPrintJobsApi.complete).toHaveBeenCalledWith("job-c");
      expect(mockPrintJobsApi.complete).not.toHaveBeenCalledWith("job-b");
    });
  });
});
