import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";

export interface SyncQueueItem {
  id: string;
  company_id: string;
  branch_id: string;
  entity_type: "order" | "payment" | "table_status" | "cash_session";
  entity_local_uuid: string;
  entity_cloud_id: string | null;
  action: "create" | "update" | "delete";
  payload: string; // JSON string
  sync_status: "pending" | "syncing" | "synced" | "failed";
  attempts: number;
  max_attempts: number;
  last_error: string | null;
  next_retry_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface EnqueuePayload {
  company_id: string;
  branch_id: string;
  entity_type: SyncQueueItem["entity_type"];
  entity_local_uuid: string;
  action: SyncQueueItem["action"];
  payload: any; // Will be JSON.stringify'd
}

export class SyncQueueRepository {
  /**
   * Agrega un evento a la cola de sincronización.
   */
  static async enqueue(data: EnqueuePayload): Promise<string> {
    const id = uuidv4();
    const payloadStr = JSON.stringify(data.payload);

    await localDb.execute(
      `INSERT INTO sync_queue (
        id, company_id, branch_id, entity_type, entity_local_uuid,
        action, payload, sync_status, attempts, max_attempts,
        created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
      [
        id,
        data.company_id,
        data.branch_id,
        data.entity_type,
        data.entity_local_uuid,
        data.action,
        payloadStr,
      ]
    );

    return id;
  }

  /**
   * Obtiene los próximos N eventos pendientes de sincronizar.
   */
  static async getPending(limit: number = 50): Promise<SyncQueueItem[]> {
    return await localDb.select<SyncQueueItem>(
      `SELECT * FROM sync_queue 
       WHERE sync_status = 'pending' 
       AND (next_retry_at IS NULL OR next_retry_at <= CURRENT_TIMESTAMP)
       ORDER BY created_at ASC 
       LIMIT ?`,
      [limit]
    );
  }

  /**
   * Marca un evento como en proceso de sincronización.
   */
  static async markAsSyncing(id: string): Promise<void> {
    await localDb.execute(
      `UPDATE sync_queue SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      [id]
    );
  }

  /**
   * Marca un evento como sincronizado exitosamente.
   */
  static async markAsSynced(id: string, cloudId?: string): Promise<void> {
    if (cloudId) {
      // Actualizar el cloud_id en la entidad correspondiente
      const item = await this.findById(id);
      if (item) {
        const tableMap: Record<string, string> = {
          order: "local_orders",
          payment: "local_payments",
          table_status: "local_tables",
          cash_session: "local_cash_sessions",
        };
        const table = tableMap[item.entity_type];
        if (table) {
          await localDb.execute(
            `UPDATE ${table} SET cloud_id = ?, sync_status = 'synced' WHERE local_uuid = ?`,
            [cloudId, item.entity_local_uuid]
          );
        }
      }
    }

    await localDb.execute(
      `UPDATE sync_queue SET sync_status = 'synced', updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      [id]
    );
  }

  /**
   * Marca un evento como fallido con backoff exponencial.
   */
  static async markAsFailed(id: string, error: string): Promise<void> {
    const item = await this.findById(id);
    if (!item) return;

    const attempts = item.attempts + 1;
    const maxAttempts = item.max_attempts;

    if (attempts >= maxAttempts) {
      // Marcar como fallido permanentemente
      await localDb.execute(
        `UPDATE sync_queue SET sync_status = 'failed', attempts = ?, last_error = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
        [attempts, error, id]
      );
    } else {
      // Backoff exponencial: 2^attempts * 15 segundos
      const backoffSeconds = Math.pow(2, attempts) * 15;
      await localDb.execute(
        `UPDATE sync_queue SET sync_status = 'pending', attempts = ?, last_error = ?, next_retry_at = datetime('now', '+${backoffSeconds} seconds'), updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
        [attempts, error, id]
      );
    }
  }

  /**
   * Elimina eventos sincronizados antiguos (más de 7 días).
   */
  static async cleanupOldSynced(): Promise<number> {
    const result = await localDb.execute(
      `DELETE FROM sync_queue WHERE sync_status = 'synced' AND updated_at < datetime('now', '-7 days')`
    );
    return result;
  }

  /**
   * Busca un evento por ID.
   */
  private static async findById(id: string): Promise<SyncQueueItem | null> {
    const results = await localDb.select<SyncQueueItem>(
      "SELECT * FROM sync_queue WHERE id = ?",
      [id]
    );
    return results[0] || null;
  }

  /**
   * Cuenta eventos pendientes.
   */
  static async countPending(): Promise<number> {
    const results = await localDb.select<{ count: number }>(
      "SELECT COUNT(*) as count FROM sync_queue WHERE sync_status = 'pending'"
    );
    return results[0]?.count || 0;
  }
}
