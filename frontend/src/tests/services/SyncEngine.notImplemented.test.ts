import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { syncEngine } from "@/services/sync/SyncEngine";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

/**
 * Tests defensivos para entity_types no implementados.
 * 
 * PRINCIPIO P0 DE INTEGRIDAD:
 * "NUNCA marcar como 'synced' un entity_type que no tiene handler"
 * 
 * NOTA: cash_session ahora tiene handler (SyncEngine.cashSession.test.ts)
 * por lo que solo testeamos bill y entity_types desconocidos aquí.
 */
describe("SyncEngine - Entity types no implementados", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM sync_queue");
  });

  describe("bill", () => {
    it("debería NUNCA marcar como 'synced' cuando no está implementado", async () => {
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "bill",
        entity_local_uuid: "bill-uuid-123",
        action: "create",
        payload: {
          bill_number: "BILL-001",
          total: 50000,
        },
      });

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item).toBeDefined();
      expect(item?.sync_status).not.toBe("synced");  // ← PRINCIPIO P0
      expect(item?.sync_status).toBe("pending");      // ← Retry programado
      expect(item?.attempts).toBe(1);
      expect(item?.last_error).toContain("bill sync not implemented");
    });
  });

  describe("Garantía P0 de integridad", () => {
    it("ningún entity_type desconocido debe quedar como 'synced'", async () => {
      // Encolar un entity_type inválido (para probar default case)
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "unknown_entity" as any,
        entity_local_uuid: "uuid-123",
        action: "create",
        payload: {},
      });

      await syncEngine.processBatch();

      const item = await SyncQueueRepository.findById(itemId);
      expect(item).toBeDefined();
      expect(item?.sync_status).not.toBe("synced");  // ← NUNCA synced
      expect(item?.last_error).toContain("Entity type no soportado");
    });
  });
});
