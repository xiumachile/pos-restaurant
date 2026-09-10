import { describe, it, expect, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("../../services/apiClient", () => ({
  apiClient: {
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    get: vi.fn(),
  },
}));

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { useAuthStore } from "../../store/useAuthStore";
import { useSyncStore } from "../../store/useSyncStore";

/**
 * Tests de validación multi-tenant en SyncEngine.
 * 
 * Garantiza que el SyncEngine RECHACE items encolados que
 * pertenecen a otra company/branch diferente al usuario actual.
 */
describe("SyncEngine - Validación Multi-Tenant", () => {
  const authorizedUser = {
    id: 1,
    uuid: "user-authorized",
    name: "User Authorized",
    email: "auth@test.com",
    role: "cashier" as const,
    company_id: 42,
    branch_id: 7,
  };

  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    
    // Reset completo de estado
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM offline_events");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    
    // Resetear flag isProcessing del SyncEngine
    (syncEngine as any).__resetForTests();
    
    // Resetear estado del store
    useSyncStore.setState({
      status: "online",
      pendingCount: 0,
      lastError: null,
      progress: null,
      simulatedOffline: false,
    });
    
    vi.clearAllMocks();
    vi.resetAllMocks();
  });

  describe("Rechazo de items no autorizados", () => {
    it("debería rechazar item con company_id diferente al usuario actual", async () => {
      await useAuthStore.getState().setAuth(authorizedUser, "token");

      const maliciousItemId = "malicious-1";
      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          maliciousItemId,
          "999",
          "7",
          "order",
          "fake-uuid",
          "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      await syncEngine.processBatch();

      const failedItem = await SyncQueueRepository.findById(maliciousItemId);
      expect(failedItem?.sync_status).toBe("failed");
      expect(failedItem?.last_error).toContain("MultiTenant");
      expect(failedItem?.last_error).toContain("999");

      const { apiClient } = await import("../../services/apiClient");
      expect((apiClient.post as any).mock.calls.length).toBe(0);

      await useAuthStore.getState().clearAuth();
    });

    it("debería rechazar item con branch_id diferente al usuario actual", async () => {
      await useAuthStore.getState().setAuth(authorizedUser, "token");

      const maliciousItemId = "malicious-2";
      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          maliciousItemId,
          "42",
          "999",
          "order",
          "fake-uuid",
          "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      await syncEngine.processBatch();

      const failedItem = await SyncQueueRepository.findById(maliciousItemId);
      expect(failedItem?.sync_status).toBe("failed");
      expect(failedItem?.last_error).toContain("MultiTenant");
      expect(failedItem?.last_error).toContain("999");

      await useAuthStore.getState().clearAuth();
    });

    it("debería procesar item con company_id y branch_id del usuario actual", async () => {
      await useAuthStore.getState().setAuth(authorizedUser, "token");

      const validItemId = "valid-1";
      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          validItemId,
          "42",
          "7",
          "order",
          "valid-uuid",
          "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      const { apiClient } = await import("../../services/apiClient");
      (apiClient.post as any).mockResolvedValueOnce({
        data: { data: { uuid: "cloud-order-valid" } },
      });

      const stats = await syncEngine.processBatch();

      expect(stats.success).toBeGreaterThanOrEqual(1);
      expect((apiClient.post as any).mock.calls.length).toBeGreaterThanOrEqual(1);

      await useAuthStore.getState().clearAuth();
    });

    it("debería procesar items cuando no hay usuario autenticado (modo test/dev)", async () => {
      // Escenario: tests legacy sin configurar auth
      // Comportamiento: permisivo (no rechaza) para no romper tests legacy
      await useAuthStore.getState().clearAuth();

      const itemId = "no-auth-1";
      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          itemId,
          "42",
          "7",
          "order",
          "fake-uuid",
          "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      // Mock éxito
      const { apiClient } = await import("../../services/apiClient");
      (apiClient.post as any).mockResolvedValueOnce({
        data: { data: { uuid: "cloud-no-auth" } },
      });

      await syncEngine.processBatch();

      // Sin usuario autenticado → permisivo → item se procesa
      const item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("synced");
    });

    it("debería procesar múltiples items mixtos (solo autorizados)", async () => {
      await useAuthStore.getState().setAuth(authorizedUser, "token");

      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          "valid-mixed",
          "42", "7", "order", "valid-uuid", "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      await localDb.execute(
        `INSERT INTO sync_queue (
          id, company_id, branch_id, entity_type, entity_local_uuid,
          action, payload, sync_status, attempts, max_attempts,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          "malicious-mixed",
          "999", "7", "order", "fake-uuid", "create",
          JSON.stringify({ order_type: "dine_in" }),
        ]
      );

      const { apiClient } = await import("../../services/apiClient");
      (apiClient.post as any).mockResolvedValueOnce({
        data: { data: { uuid: "cloud-valid" } },
      });

      await syncEngine.processBatch();

      const validItem = await SyncQueueRepository.findById("valid-mixed");
      const maliciousItem = await SyncQueueRepository.findById("malicious-mixed");

      expect(validItem?.sync_status).toBe("synced");
      expect(maliciousItem?.sync_status).toBe("failed");
      expect(maliciousItem?.last_error).toContain("MultiTenant");

      expect((apiClient.post as any).mock.calls.length).toBe(1);

      await useAuthStore.getState().clearAuth();
    });
  });
});
