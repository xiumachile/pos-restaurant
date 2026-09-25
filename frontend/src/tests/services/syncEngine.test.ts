import { describe, it, expect, beforeAll, beforeEach, vi } from "vitest";

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
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";
import { mockAuthContext } from "../testUtils";

describe("SyncEngine", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  beforeEach(async () => {
    // FIX 2: resetAllMocks (no clearAllMocks) para vaciar la cola de
    // mockResolvedValueOnce/mockRejectedValueOnce entre tests.
    // Con clearAllMocks, los once-mocks de tests anteriores se filtraban
    // a tests posteriores (causaba "Network error" fantasma).
    vi.resetAllMocks();

    // ADR-014: mockAuthContext con valores que coinciden con los tests (c1/b1)
    mockAuthContext({ companyId: "c1", branchId: "b1" });
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
  });

  it("no debería procesar si la cola está vacía", async () => {
    const stats = await syncEngine.processBatch();
    expect(stats.processed).toBe(0);
    expect(stats.success).toBe(0);
    expect(apiClient.post).not.toHaveBeenCalled();
  });

  it("debería sincronizar una orden creada localmente", async () => {
    // FIX 1: tenant c1/b1, coherente con mockAuthContext del beforeEach.
    // Con company-1/branch-1 el SyncEngine rechaza el item por validación
    // multi-tenant (ADR-014) antes de llamar apiClient.post.
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
      order_type: "dine_in",
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: {
        data: { uuid: "cloud-order-123", order_number: "ORD-001" },
      },
    });

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(1);
    expect(stats.success).toBe(1);
    expect(stats.failed).toBe(0);

    const updatedOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(updatedOrder?.cloud_id).toBe("cloud-order-123");
    expect(updatedOrder?.sync_status).toBe("synced");

    const pending = await SyncQueueRepository.countPending();
    expect(pending).toBe(0);
  });

  it("debería manejar errores y aplicar backoff", async () => {
    // FIX 1: tenant coherente con el auth mockeado
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
    });

    // Buscar el queueId del evento encolado automáticamente
    const queueItem = await localDb.select<any>(
      "SELECT id FROM sync_queue WHERE entity_local_uuid = ? LIMIT 1",
      [order.local_uuid]
    );
    const queueId = queueItem[0].id;

    (apiClient.post as any).mockRejectedValueOnce(new Error("Network error"));

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(1);
    expect(stats.failed).toBe(1);

    const items = await localDb.select<any>(
      "SELECT * FROM sync_queue WHERE id = ?",
      [queueId]
    );
    expect(items).toHaveLength(1);
    expect(items[0].attempts).toBe(1);
    expect(items[0].last_error).toContain("Network error");
    expect(items[0].sync_status).toBe("pending");
    expect(items[0].next_retry_at).toBeDefined();
  });

  it("debería marcar como failed tras exceder max_attempts", async () => {
    // FIX 1: tenant coherente con el auth mockeado
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
    });

    // Buscar el queueId del evento encolado automáticamente
    const queueItem = await localDb.select<any>(
      "SELECT id FROM sync_queue WHERE entity_local_uuid = ? LIMIT 1",
      [order.local_uuid]
    );
    const queueId = queueItem[0].id;

    // Simular que ya falló 4 veces (max_attempts = 5)
    await localDb.execute(
      "UPDATE sync_queue SET attempts = 4 WHERE id = ?",
      [queueId]
    );

    (apiClient.post as any).mockRejectedValueOnce(new Error("Final error"));

    await syncEngine.processBatch();

    const items = await localDb.select<any>(
      "SELECT * FROM sync_queue WHERE id = ?",
      [queueId]
    );
    expect(items).toHaveLength(1);
    expect(items[0].sync_status).toBe("failed");
    expect(items[0].attempts).toBe(5);
  });

  it("debería procesar múltiples eventos en secuencia", async () => {
    // OrderRepository.create() ya encola automáticamente cada uno
    const order1 = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
    });
    const order2 = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
    });

    (apiClient.post as any)
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-1" } } })
      .mockResolvedValueOnce({ data: { data: { uuid: "cloud-2" } } });

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(2);
    expect(stats.success).toBe(2);

    const o1 = await OrderRepository.findByLocalUuid(order1.local_uuid);
    const o2 = await OrderRepository.findByLocalUuid(order2.local_uuid);
    expect(o1?.cloud_id).toBe("cloud-1");
    expect(o2?.cloud_id).toBe("cloud-2");
  });

  it("debería omitir items con backoff futuro", async () => {
    // FIX 1: tenant coherente (aunque aquí no se procesa, evita falsos positivos)
    const order = await OrderRepository.create({
      company_id: "c1",
      branch_id: "b1",
    });

    // Buscar el queueId del evento encolado automáticamente
    const queueItem = await localDb.select<any>(
      "SELECT id FROM sync_queue WHERE entity_local_uuid = ? LIMIT 1",
      [order.local_uuid]
    );
    const queueId = queueItem[0].id;

    // Usar ISO timestamp futuro directamente
    const futureTime = new Date(Date.now() + 3600 * 1000).toISOString();
    await localDb.execute(
      "UPDATE sync_queue SET attempts = 1, next_retry_at = ? WHERE id = ?",
      [futureTime, queueId]
    );

    const stats = await syncEngine.processBatch();

    // getPending() debería filtrar el item (next_retry_at > now)
    expect(stats.processed).toBe(0);
    expect(apiClient.post).not.toHaveBeenCalled();

    // El item sigue en la cola con attempts sin cambiar
    const items = await localDb.select<any>(
      "SELECT * FROM sync_queue WHERE id = ?",
      [queueId]
    );
    expect(items).toHaveLength(1);
    expect(items[0].attempts).toBe(1);
    expect(items[0].next_retry_at).toBe(futureTime);
  });

  // Test nuevo (documenta ADR-014): con SQLite real los items ahora sí
  // persisten company_id, así que la validación multi-tenant se ejerce
  // de verdad. Este test fija el comportamiento de seguridad.
  it("debería rechazar permanentemente items de otro tenant (ADR-014)", async () => {
    mockAuthContext({ companyId: "c1", branchId: "b1" });
    const order = await OrderRepository.create({
      company_id: "otra-company", // simula dato de otro tenant
      branch_id: "otra-branch",
    });

    (apiClient.post as any).mockResolvedValue({
      data: { data: { uuid: "no-deberia-llegar" } },
    });

    const stats = await syncEngine.processBatch();

    expect(stats.processed).toBe(1);
    expect(stats.failed).toBe(1);
    // Seguridad: el item malicioso jamás debe salir por la red
    expect(apiClient.post).not.toHaveBeenCalled();

    const items = await localDb.select<any>(
      "SELECT * FROM sync_queue WHERE entity_local_uuid = ?",
      [order.local_uuid]
    );
    expect(items[0].sync_status).toBe("failed");
    expect(items[0].attempts).toBe(items[0].max_attempts); // permanente, sin backoff
  });
});
