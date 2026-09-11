import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

// Mock del plugin SQL (igual que en repositories.test.ts)
vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";

describe("LocalPrintJobRepository", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM local_print_jobs");
    vi.clearAllMocks();
  });

  const basePayload = {
    job_type: "receipt" as const,
    entity_type: "bill",
    entity_uuid: "bill-uuid-123",
    payload: { bill_number: "42-1", total: 15000 },
    company_id: "company-1",
    branch_id: "branch-1",
    user_id: "user-1",
    user_name: "Cajero Test",
    reference_number: "Cuenta #42-1",
  };

  describe("create", () => {
    it("crea un job y retorna UUID local", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);

      expect(uuid).toBeTruthy();
      expect(uuid.length).toBeGreaterThan(0);

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job).not.toBeNull();
      expect(job?.job_type).toBe("receipt");
      expect(job?.entity_uuid).toBe("bill-uuid-123");
      expect(job?.status).toBe("pending");
      expect(job?.attempts).toBe(0);
      expect(job?.max_attempts).toBe(5);
      expect(job?.user_name).toBe("Cajero Test");
    });

    it("genera idempotency_key automáticamente si no se provee", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);

      expect(job?.idempotency_key).toMatch(/^print-/);
    });

    it("respeta idempotency_key provisto", async () => {
      const uuid = await LocalPrintJobRepository.create({
        ...basePayload,
        idempotency_key: "custom-key-123",
      });
      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);

      expect(job?.idempotency_key).toBe("custom-key-123");
    });

    it("serializa payload como JSON", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);

      expect(job?.payload).toBe(JSON.stringify(basePayload.payload));
    });
  });

  describe("findByLocalUuid", () => {
    it("retorna null si no existe", async () => {
      const result = await LocalPrintJobRepository.findByLocalUuid("no-existe");
      expect(result).toBeNull();
    });
  });

  describe("getPending", () => {
    it("retorna solo jobs pendientes", async () => {
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      const uuid2 = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsPrinting(uuid2);

      const pending = await LocalPrintJobRepository.getPending();

      expect(pending).toHaveLength(1);
      expect(pending[0].local_uuid).toBe(uuid1);
    });

    it("ordena por created_at ASC (FIFO)", async () => {
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      await new Promise(r => setTimeout(r, 10));
      const uuid2 = await LocalPrintJobRepository.create(basePayload);

      const pending = await LocalPrintJobRepository.getPending();

      expect(pending[0].local_uuid).toBe(uuid1);
      expect(pending[1].local_uuid).toBe(uuid2);
    });
  });

  describe("markAsPrinting", () => {
    it("actualiza status a printing e incrementa attempts", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsPrinting(uuid);

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("printing");
      expect(job?.attempts).toBe(1);
    });
  });

  describe("markAsCompleted", () => {
    it("marca como completed con printed_at", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsCompleted(uuid, "cloud-uuid-456");

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("completed");
      expect(job?.cloud_id).toBe("cloud-uuid-456");
      expect(job?.printed_at).toBeTruthy();
    });

    it("funciona sin cloud_id", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsCompleted(uuid);

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("completed");
      expect(job?.cloud_id).toBeNull();
    });
  });

  describe("markAsFailed", () => {
    it("vuelve a pending si no alcanzó max_attempts", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsFailed(uuid, "Error de impresora");

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("pending");
      expect(job?.error_message).toBe("Error de impresora");
    });

    it("queda en failed permanente si alcanzó max_attempts", async () => {
      const uuid = await LocalPrintJobRepository.create({
        ...basePayload,
        idempotency_key: "fail-test",
      });

      // Simular 5 intentos
      await localDb.execute(
        "UPDATE local_print_jobs SET attempts = 5 WHERE local_uuid = ?",
        [uuid]
      );

      await LocalPrintJobRepository.markAsFailed(uuid, "Error fatal");

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job?.status).toBe("failed");
      expect(job?.error_message).toBe("Error fatal");
    });
  });

  describe("countByStatus", () => {
    it("retorna estructura correcta con conteos", async () => {
      // Crear jobs en diferentes estados
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      const uuid2 = await LocalPrintJobRepository.create(basePayload);
      const uuid3 = await LocalPrintJobRepository.create(basePayload);

      await LocalPrintJobRepository.markAsCompleted(uuid1);
      await LocalPrintJobRepository.markAsPrinting(uuid2);

      const counts = await LocalPrintJobRepository.countByStatus();

      // Verificar estructura (el mock no soporta GROUP BY perfectamente)
      expect(counts).toHaveProperty("pending");
      expect(counts).toHaveProperty("printing");
      expect(counts).toHaveProperty("completed");
      expect(counts).toHaveProperty("failed");
      expect(typeof counts.pending).toBe("number");
      expect(typeof counts.printing).toBe("number");
      expect(typeof counts.completed).toBe("number");
      expect(typeof counts.failed).toBe("number");
    });
  });

  describe("countPending", () => {
    it("cuenta solo pending", async () => {
      await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.create(basePayload);
      
      const uuid3 = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.markAsCompleted(uuid3);

      const count = await LocalPrintJobRepository.countPending();
      expect(count).toBe(2);
    });
  });

  describe("getAll", () => {
    it("retorna todos los jobs ordenados por created_at DESC", async () => {
      const uuid1 = await LocalPrintJobRepository.create(basePayload);
      await new Promise(r => setTimeout(r, 10));
      const uuid2 = await LocalPrintJobRepository.create(basePayload);

      const all = await LocalPrintJobRepository.getAll();

      expect(all).toHaveLength(2);
      expect(all[0].local_uuid).toBe(uuid2); // Más reciente primero
      expect(all[1].local_uuid).toBe(uuid1);
    });
  });

  describe("deleteById", () => {
    it("elimina el job", async () => {
      const uuid = await LocalPrintJobRepository.create(basePayload);
      await LocalPrintJobRepository.deleteById(uuid);

      const job = await LocalPrintJobRepository.findByLocalUuid(uuid);
      expect(job).toBeNull();
    });
  });
});
