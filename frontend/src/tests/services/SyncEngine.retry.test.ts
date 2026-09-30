import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { syncEngine } from "@/services/sync/SyncEngine";
import { syncApi } from "@/services/syncApi";
import { mockAuthContext } from "../testUtils";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("@/services/syncApi");
const mockSyncApi = vi.mocked(syncApi);

describe("SyncEngine - Fail-secure en errores permanentes (4xx)", () => {
  beforeEach(async () => {
    mockAuthContext();
    localDb;
    await runMigrations();
    await localDb.execute("DELETE FROM sync_queue");
    vi.clearAllMocks();
  });

  const enqueueOrder = async () => {
    return await SyncQueueRepository.enqueue({
      company_id: "company-1",
      branch_id: "branch-1",
      entity_type: "order",
      entity_local_uuid: `order-${Date.now()}-${Math.random()}`,
      action: "create",
      payload: { total: 12000, items: [] },
    });
  };

  describe("Rechazo permanente (errores 4xx) - [AUDIT: deben marcarse como failed]", () => {
    it("error 422 debe marcar el ítem con sync_status=failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Invalid"), {
        response: { status: 422, data: { message: "Invalid data" } },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      // [AUDIT] Si este test falla, el SyncEngine NO está marcando 4xx como failed
      expect(item?.sync_status).toBe("failed");
      expect(item?.last_error).toBeTruthy();
    });

    it("error 400 debe marcar el ítem como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Bad Request"), {
        response: { status: 400 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("failed");
    });

    it("error 403 (tenant inválido) debe marcar el ítem como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Forbidden"), {
        response: { status: 403 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("failed");
    });

    it("error 404 debe marcar el ítem como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Not Found"), {
        response: { status: 404 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("failed");
    });
  });

  describe("Errores transitorios (deben seguir en pending con attempts++)", () => {
    it("error 500 NO debe marcar como failed (reintentable)", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Server Error"), {
        response: { status: 500 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending");
      expect(item?.attempts).toBeGreaterThanOrEqual(1);
    });

    it("error 408 (timeout) NO debe marcar como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Timeout"), {
        response: { status: 408 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending");
    });

    it("error 429 (rate limit) NO debe marcar como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Rate limited"), {
        response: { status: 429 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending");
    });

    it("error de red sin status NO debe marcar como failed", async () => {
      const itemId = await enqueueOrder();
      const error = Object.assign(new Error("Network Error"), {
        isAxiosError: true,
        code: "ECONNREFUSED",
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending");
    });
  });

  describe("Límite de reintentos", () => {
    it("debe marcar como failed cuando attempts >= max_attempts", async () => {
      const itemId = await enqueueOrder();

      await localDb.execute(
        "UPDATE sync_queue SET attempts = max_attempts - 1 WHERE id = ?",
        [itemId]
      );

      const error = Object.assign(new Error("Server Error"), {
        response: { status: 500 },
        isAxiosError: true,
      });
      mockSyncApi.createOrder.mockRejectedValueOnce(error);

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("failed");
    });
  });
});
