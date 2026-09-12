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
 * COMPORTAMIENTO ESPERADO:
 * - Primer intento: error + vuelta a 'pending' con backoff (retry)
 * - Intentos subsiguientes: mismo comportamiento
 * - Después de max_attempts: marcado como 'failed'
 * - NUNCA marcado como 'synced'
 * 
 * Esto es correcto: el sistema reintenta en caso de errores transitorios,
 * pero lo crítico es que NUNCA miente diciendo que sincronizó algo
 * que no envió al backend.
 */
describe("SyncEngine - Entity types no implementados", () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM sync_queue");
  });

  describe("cash_session", () => {
    it("debería NUNCA marcar como 'synced' cuando no está implementado", async () => {
      // Encolar un cash_session
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "cash_session",
        entity_local_uuid: "session-uuid-123",
        action: "update",
        payload: {
          session_uuid: "session-uuid-123",
          closing_amount: 350000,
        },
      });

      // Procesar batch
      await syncEngine.processBatch();

      // Verificar que NO quedó como 'synced' (esto era el bug P0)
      const item = await SyncQueueRepository.findById(itemId);
      expect(item).toBeDefined();
      expect(item?.sync_status).not.toBe("synced");  // ← PRINCIPIO P0
      expect(item?.sync_status).toBe("pending");      // ← Retry programado
      expect(item?.attempts).toBe(1);
      expect(item?.last_error).toContain("cash_session sync not implemented");
      expect(item?.last_error).toContain("P0");
    });

    it("debería quedar como 'failed' permanente tras agotar reintentos", async () => {
      // Encolar cash_session con max_attempts = 2 para test rápido
      const itemId = await SyncQueueRepository.enqueue({
        company_id: "company-1",
        branch_id: "branch-1",
        entity_type: "cash_session",
        entity_local_uuid: "session-uuid-456",
        action: "update",
        payload: {
          session_uuid: "session-uuid-456",
          closing_amount: 350000,
        },
      });

      // Reducir max_attempts a 2 para acelerar test
      await localDb.execute(
        "UPDATE sync_queue SET max_attempts = 2 WHERE id = ?",
        [itemId]
      );

      // Intento 1 → queda pending
      await syncEngine.processBatch();
      let item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("pending");
      expect(item?.attempts).toBe(1);

      // Forzar next_retry_at al pasado para que el siguiente batch lo procese
      await localDb.execute(
        "UPDATE sync_queue SET next_retry_at = datetime('now', '-1 hour') WHERE id = ?",
        [itemId]
      );

      // Intento 2 → agota max_attempts, queda failed permanente
      await syncEngine.processBatch();
      item = await SyncQueueRepository.findById(itemId);
      expect(item?.sync_status).toBe("failed");
      expect(item?.attempts).toBe(2);
      expect(item?.last_error).toContain("cash_session sync not implemented");
    });
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
      expect(item?.sync_status).toBe("pending");
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
