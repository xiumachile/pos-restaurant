import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('../mocks/tauriSql');
  return { default: mod.default };
});

import { localDb } from '@/db/localDb';
import { runMigrations } from '@/db/schema';
import { CashSessionRepository } from '@/db/repositories/CashSessionRepository';
import { SyncQueueRepository } from '@/db/repositories/SyncQueueRepository';

describe('paymentsService retry pattern', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute('DELETE FROM local_cash_sessions');
    await localDb.execute('DELETE FROM sync_queue');
  });

  it('CashSessionRepository.close acepta cloud_id cuando local_uuid no existe', async () => {
    // Crear sesión con cloud_id
    const session = await CashSessionRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      user_id: 'user-1',
      opening_amount: 50000,
      cloud_id: 'cloud-uuid-123',
    });

    // Cerrar usando cloud_id (no local_uuid)
    await CashSessionRepository.close('cloud-uuid-123', 100000);

    // Verificar que se cerró
    const updated = await CashSessionRepository.findByCloudId('cloud-uuid-123');
    expect(updated?.status).toBe('closed');
    expect(updated?.closing_amount).toBe(100000);
  });

  it('SyncQueueRepository puede encolar cash_session con cloud_id como entity_local_uuid', async () => {
    const itemId = await SyncQueueRepository.enqueue({
      company_id: 'company-1',
      branch_id: 'branch-1',
      entity_type: 'cash_session',
      entity_local_uuid: 'cloud-uuid-456',  // cloud_id, no local_uuid
      action: 'update',
      payload: {
        closing_amount: 150000,
        notes: 'Cierre normal',
        closed_at: new Date().toISOString(),
      },
    });

    expect(itemId).toBeDefined();
    
    // Verificar que el item se encoló correctamente
    const pending = await SyncQueueRepository.getPending();
    const item = pending.find(p => p.id === itemId);
    expect(item).toBeDefined();
    expect(item?.entity_type).toBe('cash_session');
    expect(item?.entity_local_uuid).toBe('cloud-uuid-456');
    expect(item?.action).toBe('update');
  });

  it('CashSessionRepository.findByCloudId encuentra sesión por cloud_id', async () => {
    await CashSessionRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      user_id: 'user-1',
      opening_amount: 50000,
      cloud_id: 'cloud-uuid-789',
    });

    const found = await CashSessionRepository.findByCloudId('cloud-uuid-789');
    expect(found).toBeDefined();
    expect(found?.cloud_id).toBe('cloud-uuid-789');
  });
});
