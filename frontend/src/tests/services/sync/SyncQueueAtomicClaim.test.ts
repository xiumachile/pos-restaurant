import { describe, it, expect } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

/**
 * P1-009: Validación de claim atómico en SyncQueue.
 * 
 * El bug original permitía que múltiples procesos reclamaran el mismo item
 * porque usaban SELECT (getPending) seguido de UPDATE (markAsSyncing) en 
 * operaciones separadas, creando una condición de carrera.
 * 
 * La solución implementa UPDATE ... RETURNING en una sola operación atómica.
 */
describe('P1-009: Claim atómico en SyncQueue', () => {
  
  it('SyncQueueRepository debería usar UPDATE ... RETURNING en claimPending', () => {
    const repoPath = path.resolve(__dirname, '../../../db/repositories/SyncQueueRepository.ts');
    const content = fs.readFileSync(repoPath, 'utf-8');

    // 1. Verificar que existe el método claimPending
    expect(content).toContain('static async claimPending');
    
    // 2. Verificar que usa UPDATE con RETURNING (atomicidad garantizada por SQLite)
    expect(content).toMatch(/UPDATE\s+sync_queue\s+SET\s+sync_status\s*=\s*['"]syncing['"]/);
    expect(content).toContain('RETURNING *');
    
    // 3. Verificar que la subconsulta filtra correctamente por estado 'pending'
    expect(content).toContain("sync_status = 'pending'");
    
    // 4. Verificar que maneja correctamente el next_retry_at
    expect(content).toMatch(/next_retry_at\s+IS\s+NULL\s+OR\s+datetime\(next_retry_at\)\s+<=\s+datetime\(['"]now['"]\)/);
  });

  it('SyncEngine debería usar claimPending y NO usar el patrón antiguo', () => {
    const enginePath = path.resolve(__dirname, '../../../services/sync/SyncEngine.ts');
    const content = fs.readFileSync(enginePath, 'utf-8');

    // 1. Verificar que llama a claimPending
    expect(content).toContain('SyncQueueRepository.claimPending');
    
    // 2. Verificar que NO llama al método antiguo getPending
    expect(content).not.toMatch(/SyncQueueRepository\.getPending\(/);
    
    // 3. Verificar que NO llama a markAsSyncing dentro de processItem
    // (porque el item ya viene reclamado por claimPending)
    expect(content).not.toMatch(/await\s+SyncQueueRepository\.markAsSyncing\(/);
  });

  it('markAsSyncing debería estar marcado como deprecated o tener protección de estado', () => {
    const repoPath = path.resolve(__dirname, '../../../db/repositories/SyncQueueRepository.ts');
    const content = fs.readFileSync(repoPath, 'utf-8');

    // Si markAsSyncing aún existe, debe tener una cláusula WHERE sync_status = 'pending'
    // para evitar sobrescribir items que ya fueron reclamados por otro proceso.
    const markAsSyncingMatch = content.match(/static async markAsSyncing[\s\S]*?\n  \}/);
    if (markAsSyncingMatch) {
      expect(markAsSyncingMatch[0]).toContain("sync_status = 'pending'");
      expect(markAsSyncingMatch[0]).toMatch(/@deprecated|deprecated/i);
    }
  });
});
