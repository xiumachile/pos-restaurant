import { describe, it, expect, beforeAll, beforeEach } from "vitest";
import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";

describe("SyncQueueRepository - Recuperación de abandonados (P1-03)", () => {
  beforeAll(async () => {
    await runMigrations();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
  });

  it("NO debe recuperar operaciones recientes (hace 1 minuto)", async () => {
    await localDb.execute(`
      INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, attempts, max_attempts, created_at, updated_at)
      VALUES ('test-recent', '10', '5', 'order', 'order-1', 'create', '{}', 'syncing', 0, 5, datetime('now', '-1 minute'), datetime('now', '-1 minute'))
    `);

    const recovered = await SyncQueueRepository.recoverAbandonedSyncing();
    expect(recovered).toBe(0);

    const status = await localDb.select("SELECT sync_status, attempts FROM sync_queue WHERE id = 'test-recent'");
    expect(status[0].sync_status).toBe('syncing');
    expect(status[0].attempts).toBe(0);
  });

  it("DEBE recuperar operaciones verdaderamente abandonadas (hace 10 minutos)", async () => {
    await localDb.execute(`
      INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, attempts, max_attempts, created_at, updated_at)
      VALUES ('test-abandoned', '10', '5', 'order', 'order-2', 'create', '{}', 'syncing', 0, 5, datetime('now', '-10 minutes'), datetime('now', '-10 minutes'))
    `);

    const recovered = await SyncQueueRepository.recoverAbandonedSyncing();
    expect(recovered).toBe(1);

    const status = await localDb.select("SELECT sync_status, attempts FROM sync_queue WHERE id = 'test-abandoned'");
    expect(status[0].sync_status).toBe('pending');
    expect(status[0].attempts).toBe(1);
  });
});
