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
  payload: string;
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
  payload: any;
}

// Timeout para considerar una operación syncing como abandonada (2 minutos)
const SYNCING_TIMEOUT_MINUTES = 2;

export class SyncQueueRepository {
  /**
   * Recupera operaciones que quedaron en estado 'syncing' por más de SYNCING_TIMEOUT_MINUTES.
   * Esto ocurre cuando el proceso se interrumpe (crash, corte de energía, timeout).
   *
   * Regla:
   * - syncing < 2 minutos → mantener (proceso activo)
   * - syncing >= 2 minutos → volver a pending + increment attempts
   *
   * @returns Número de items recuperados
   */
  static async recoverAbandonedSyncing(): Promise<number> {
    const cutoff = new Date(
      Date.now() - SYNCING_TIMEOUT_MINUTES * 60 * 1000
    ).toISOString();

    // Buscar items en syncing que superaron el timeout
    const abandoned = await localDb.select<SyncQueueItem>(
      `SELECT * FROM sync_queue 
       WHERE sync_status = 'syncing' 
         AND updated_at < ?
       ORDER BY created_at ASC`,
      [cutoff]
    );

    if (abandoned.length === 0) return 0;

    console.log(`[SyncQueue] ⚠️  Recuperando ${abandoned.length} operaciones syncing abandonadas`);

    for (const item of abandoned) {
      const attempts = item.attempts + 1;
      
      if (attempts >= item.max_attempts) {
        // Máximo de intentos alcanzado → fallar definitivamente
        await localDb.execute(
          `UPDATE sync_queue 
           SET sync_status = 'failed', 
               attempts = ?, 
               last_error = ?,
               updated_at = CURRENT_TIMESTAMP 
           WHERE id = ?`,
          [attempts, `Abandonado tras ${SYNCING_TIMEOUT_MINUTES}min (intento ${attempts})`, item.id]
        );
        console.log(`[SyncQueue] ❌ ${item.entity_type}/${item.action} falló tras ${attempts} intentos`);
      } else {
        // Backoff exponencial
        const backoffSeconds = Math.pow(2, attempts) * 15;
        const nextRetryAt = new Date(Date.now() + backoffSeconds * 1000).toISOString();

        await localDb.execute(
          `UPDATE sync_queue 
           SET sync_status = 'pending', 
               attempts = ?, 
               last_error = ?,
               next_retry_at = ?,
               updated_at = CURRENT_TIMESTAMP 
           WHERE id = ?`,
          [
            attempts,
            `Sync abandonado (timeout ${SYNCING_TIMEOUT_MINUTES}min, intento ${attempts})`,
            nextRetryAt,
            item.id,
          ]
        );
        console.log(`[SyncQueue] 🔄 ${item.entity_type}/${item.action} → pending (intento ${attempts})`);
      }
    }

    return abandoned.length;
  }

  /**
   * Obtiene conteo de items en estado 'syncing' actualmente.
   */
  static async countSyncing(): Promise<number> {
    const results = await localDb.select<{ count: number }>(
      "SELECT COUNT(*) as count FROM sync_queue WHERE sync_status = ?",
      ["syncing"]
    );
    return results[0]?.count || 0;
  }

  /**
   * Fuerza reset de todos los items syncing a pending (para casos extremos).
   * Usar solo cuando se sabe que el proceso anterior definitivamente murió.
   */
  static async forceResetAllSyncing(): Promise<number> {
    const result = await localDb.execute(
      `UPDATE sync_queue 
       SET sync_status = 'pending', 
           attempts = attempts + 1,
           last_error = 'Reset forzado (posible crash)',
           updated_at = CURRENT_TIMESTAMP 
       WHERE sync_status = 'syncing'`
    );
    return result;
  }

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
   * Obtiene los próximos N eventos pendientes.
   * 
   * IMPORTANTE: Antes de consultar, recupera automáticamente cualquier
   * operación 'syncing' que haya quedado abandonada (timeout > 2min).
   * Esto garantiza que nunca queden operaciones pegadas en 'syncing'.
   * 
   * Nota: El filtrado por next_retry_at se hace en JS para compatibilidad con tests.
   */
  static async getPending(limit: number = 50): Promise<SyncQueueItem[]> {
    // Paso 1: Recuperar cualquier syncing abandonado
    await this.recoverAbandonedSyncing();

    // Paso 2: Consultar pendientes
    const allPending = await localDb.select<SyncQueueItem>(
      "SELECT * FROM sync_queue WHERE sync_status = ? ORDER BY created_at ASC",
      ["pending"]
    );

    const now = new Date();

    // Paso 3: Filtrar en JS: solo incluir items sin next_retry_at o con next_retry_at <= now
    const eligible = allPending.filter((item) => {
      if (!item.next_retry_at) return true;
      return new Date(item.next_retry_at) <= now;
    });

    return eligible.slice(0, limit);
  }

  static async markAsSyncing(id: string): Promise<void> {
    await localDb.execute(
      `UPDATE sync_queue SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      [id]
    );
  }

  static async markAsSynced(id: string, cloudId?: string): Promise<void> {
    if (cloudId) {
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

  static async markAsFailed(id: string, error: string): Promise<void> {
    const item = await this.findById(id);
    if (!item) return;

    const attempts = item.attempts + 1;
    const maxAttempts = item.max_attempts;

    if (attempts >= maxAttempts) {
      await localDb.execute(
        `UPDATE sync_queue SET sync_status = 'failed', attempts = ?, last_error = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
        [attempts, error, id]
      );
    } else {
      // Backoff exponencial calculado en JS (evita datetime() en SQL para compatibilidad)
      const backoffSeconds = Math.pow(2, attempts) * 15;
      const nextRetryAt = new Date(Date.now() + backoffSeconds * 1000).toISOString();
      
      await localDb.execute(
        `UPDATE sync_queue SET sync_status = 'pending', attempts = ?, last_error = ?, next_retry_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
        [attempts, error, nextRetryAt, id]
      );
    }
  }

  static async cleanupOldSynced(): Promise<number> {
    const cutoff = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();
    const result = await localDb.execute(
      `DELETE FROM sync_queue WHERE sync_status = 'synced' AND updated_at < ?`,
      [cutoff]
    );
    return result;
  }

  static async findById(id: string): Promise<SyncQueueItem | null> {
    const results = await localDb.select<SyncQueueItem>(
      "SELECT * FROM sync_queue WHERE id = ?",
      [id]
    );
    return results[0] || null;
  }

  static async countPending(): Promise<number> {
    const results = await localDb.select<{ count: number }>(
      "SELECT COUNT(*) as count FROM sync_queue WHERE sync_status = ?",
      ["pending"]
    );
    return results[0]?.count || 0;
  }
}
