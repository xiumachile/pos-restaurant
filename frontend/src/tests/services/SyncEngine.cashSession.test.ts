import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { syncEngine } from "@/services/sync/SyncEngine";
import { syncApi } from "@/services/syncApi";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("@/services/syncApi");
const mockSyncApi = vi.mocked(syncApi);

describe("SyncEngine - Cash Session Sync", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_cash_sessions");
    vi.clearAllMocks();
  });

  describe("Action: create (open session)", () => {
    it("debería abrir sesión de caja y marcar como synced", async () => {
      // Mock response del backend
      mockSyncApi.openCashSession.mockResolvedValue({
        uuid: "cloud-session-uuid-123",
        opened_at: new Date().toISOString(),
      });

      // Crear sesión local
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 100000,
      });

      // Encolar evento de sync
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "cash_session",
        entity_local_uuid: session.local_uuid,
        action: "create",
        payload: {
          opening_amount: 100000,
          notes: "Apertura de caja",
          idempotency_key: `cash-open-${session.local_uuid}`,
        },
      });

      // Procesar batch
      await syncEngine.processBatch();

      // Verificar que se llamó al endpoint correcto
      expect(mockSyncApi.openCashSession).toHaveBeenCalledWith({
        opening_amount: 100000,
        notes: "Apertura de caja",
        idempotency_key: `cash-open-${session.local_uuid}`,
      });

      // Verificar que la sesión quedó marcada como synced
      const updatedSession = await CashSessionRepository.findByLocalUuid(session.local_uuid);
      expect(updatedSession?.sync_status).toBe("synced");
      expect(updatedSession?.cloud_id).toBe("cloud-session-uuid-123");

      // Verificar que el item de sync quedó como synced
      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("synced");
    });
  });

  describe("Action: update (close session)", () => {
    it("debería cerrar sesión de caja y confirmar en backend", async () => {
      // Mock response del backend
      mockSyncApi.closeCashSession.mockResolvedValue({
        uuid: "cloud-session-uuid-456",
        closed_at: new Date().toISOString(),
      });

      // Crear sesión local con cloud_id (simulando que ya fue sincronizada)
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 100000,
        cloud_id: "cloud-session-uuid-456",
        sync_status: "synced",
      });

      // Cerrar sesión localmente
      await CashSessionRepository.close(session.local_uuid, 350000);

      // Encolar evento de sync (cierre)
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "cash_session",
        entity_local_uuid: session.local_uuid,
        action: "update",
        payload: {
          session_uuid: session.local_uuid,
          closing_amount: 350000,
          expected_amount: 360000,
          difference: -10000,
          notes: "Cierre de caja",
          closed_at: new Date().toISOString(),
          idempotency_key: `cash-close-${session.local_uuid}`,
        },
      });

      // Procesar batch
      await syncEngine.processBatch();

      // Verificar que se llamó al endpoint correcto con cloud_id
      expect(mockSyncApi.closeCashSession).toHaveBeenCalledWith(
        "cloud-session-uuid-456",
        {
          closing_amount: 350000,
          notes: "Cierre de caja",
          idempotency_key: `cash-close-${session.local_uuid}`,
        }
      );

      // Verificar que el item de sync quedó como synced
      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("synced");
    });

    it("debería fallar si la sesión no tiene cloud_id", async () => {
      // Crear sesión local SIN cloud_id
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 100000,
      });

      // Encolar evento de sync (cierre)
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "cash_session",
        entity_local_uuid: session.local_uuid,
        action: "update",
        payload: {
          closing_amount: 350000,
        },
      });

      // Procesar batch
      await syncEngine.processBatch();

      // Verificar que NO se llamó al endpoint
      expect(mockSyncApi.closeCashSession).not.toHaveBeenCalled();

      // Verificar que el item quedó como failed
      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending"); // Retry programado
      expect(item?.last_error).toContain("sin cloud_id");
    });
  });
});
