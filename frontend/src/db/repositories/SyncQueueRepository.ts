import { isTestEnvironment, claimPendingSyncItems } from "../nativeDb";
import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";

export interface SyncQueueItem {
  id: string;
  company_id: string;
  branch_id: string;
  entity_type: "order" | "payment" | "bill" | "table_status" | "cash_session" | "cash_movement";
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

// Timeout para considerar una operación syncing como abandonada (minutos).
// Todos los comentarios y mensajes de este archivo deben referirse a esta constante.
const SYNCING_TIMEOUT_MINUTES = 5;

// Retención de items ya sincronizados antes de limpiarlos (días).
const SYNCED_RETENTION_DAYS = 7;

/**
 * Normaliza el valor devuelto por localDb.execute() a un número de filas afectadas.
 * - Rust (producción) y nativeDb (tests) devuelven { success, count, rowsAffected }.
 * - Si por compatibilidad llega un número, se usa tal cual.
 * - Si no hay información, devuelve 0.
 */
function affectedRows(result: any): number {
  if (typeof result === "number") return result;
  const n = result?.rowsAffected;
  return typeof n === "number" ? n : 0;
}

export class SyncQueueRepository {
  /**
   * Recupera operaciones que quedaron en estado 'syncing' por más de SYNCING_TIMEOUT_MINUTES.
   * Esto ocurre cuando el proceso se interrumpe (crash, corte de energía, timeout).
   *
   * Regla:
   * - syncing por menos de SYNCING_TIMEOUT_MINUTES → mantener (proceso activo)
   * - syncing por SYNCING_TIMEOUT_MINUTES o más → volver a pending (+1 intento),
   *   o a failed si se alcanzó max_attempts
   *
   * La comparación se hace 100% en SQLite con segundos Unix, de modo que no
   * depende del formato de fecha ('T' vs espacio) ni de la zona horaria de JS.
   *
   * @returns Número de items recuperados
   */
  static async recoverAbandonedSyncing(): Promise<number> {
    const abandoned = await localDb.select<{
      id: string;
      attempts: number;
      max_attempts: number;
      entity_type: string;
      action: string;
    }>(
      `SELECT id, attempts, max_attempts, entity_type, action FROM sync_queue
       WHERE sync_status = 'syncing'
         AND CAST(strftime('%s', updated_at) AS INTEGER) < CAST(strftime('%s', 'now', ?) AS INTEGER)
       ORDER BY created_at ASC`,
      [`-${SYNCING_TIMEOUT_MINUTES} minutes`]
    );

    if (abandoned.length === 0) return 0;

    console.log(`[SyncQueue] ⚠️  Recuperando ${abandoned.length} operaciones syncing abandonadas`);

    let recoveredCount = 0;
    for (const item of abandoned) {
      const newAttempts = item.attempts + 1;

      if (newAttempts >= item.max_attempts) {
        // UPDATE atómico: solo si sigue en 'syncing' (previene TOCTOU)
        const result = await localDb.execute(
          `UPDATE sync_queue
           SET sync_status = 'failed',
               attempts = ?,
               last_error = ?,
               updated_at = CURRENT_TIMESTAMP
           WHERE id = ? AND sync_status = 'syncing'`,
          [newAttempts, `Abandonado tras ${SYNCING_TIMEOUT_MINUTES}min (intento ${newAttempts})`, item.id]
        );
        if (affectedRows(result) > 0) recoveredCount++;
      } else {
        // UPDATE atómico: volver a pending para reintentar
        const result = await localDb.execute(
          `UPDATE sync_queue
           SET sync_status = 'pending',
               attempts = ?,
               updated_at = CURRENT_TIMESTAMP
           WHERE id = ? AND sync_status = 'syncing'`,
          [newAttempts, item.id]
        );
        if (affectedRows(result) > 0) recoveredCount++;
      }
    }

    return recoveredCount;
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
    return affectedRows(result);
  }

  static async enqueue(data: EnqueuePayload, txDb?: any): Promise<string> {
    const id = uuidv4();
    const payloadStr = JSON.stringify(data.payload);
    const dbToUse = txDb || localDb;

    await (dbToUse as any).execute(
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
   * Obtiene items pendientes sin modificar su estado (salvo la recuperación de abandonados).
   * Útil para inspección, tests o UI.
   * NOTA: Para procesar, usar claimPending() para garantizar atomicidad.
   *
   * Antes de consultar, recupera automáticamente cualquier operación 'syncing'
   * abandonada (más de SYNCING_TIMEOUT_MINUTES).
   *
   * Nota: El filtrado por next_retry_at se hace en JS para compatibilidad con tests.
   */
  static async getPending(limit: number = 50): Promise<SyncQueueItem[]> {
    // Paso 1: Recuperar cualquier syncing abandonado
    await this.recoverAbandonedSyncing();

    // Paso 2: Consultar pendientes (solo lectura)
    const allPending = await localDb.select<SyncQueueItem>(
      "SELECT * FROM sync_queue WHERE sync_status = 'pending' ORDER BY created_at ASC"
    );

    const now = new Date();

    // Paso 3: Filtrar en JS: solo incluir items sin next_retry_at o con next_retry_at <= now
    const eligible = allPending.filter((item) => {
      if (!item.next_retry_at) return true;
      return new Date(item.next_retry_at) <= now;
    });

    return eligible.slice(0, limit);
  }

  /**
   * P1-009: Claim atómico de items pendientes.
   * Actualiza el estado a 'syncing' y devuelve los registros reclamados en una sola operación,
   * garantizando que ningún otro proceso pueda reclamar los mismos items simultáneamente.
   */
  static async claimPending(limit: number = 10): Promise<SyncQueueItem[]> {
    // Paso 1: Recuperar cualquier syncing abandonado (timeout)
    await this.recoverAbandonedSyncing();

    // Paso 2: Claim atómico con UPDATE ... RETURNING
    // La subconsulta limita el número de filas, y el UPDATE las marca como 'syncing'
    // y las devuelve exclusivamente a este proceso.
    const query = `
      UPDATE sync_queue
      SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP
      WHERE id IN (
        SELECT id FROM sync_queue
        WHERE sync_status = 'pending'
          AND (next_retry_at IS NULL OR datetime(next_retry_at) <= datetime('now'))
        ORDER BY created_at ASC
        LIMIT ?
      )
      RETURNING *
    `;

    if (!isTestEnvironment()) {
      return await claimPendingSyncItems<SyncQueueItem>(limit);
    }
    const claimed = await localDb.select<SyncQueueItem>(query, [limit]);
    return claimed || [];
  }

  /**
   * @deprecated Usar claimPending() para garantizar atomicidad.
   */
  static async markAsSyncing(id: string): Promise<void> {
    console.warn("[SyncQueueRepository] markAsSyncing is deprecated. Use claimPending().");
    await localDb.execute(
      `UPDATE sync_queue SET sync_status = 'syncing', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND sync_status = 'pending'`,
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

  /**
   * Elimina items ya sincronizados con más de SYNCED_RETENTION_DAYS días.
   * (Antes comparaba segundos Unix contra SYNCING_TIMEOUT_MINUTES, por lo que nunca borraba nada.)
   */
  static async cleanupOldSynced(): Promise<number> {
    const result = await localDb.execute(
      `DELETE FROM sync_queue
       WHERE sync_status = 'synced'
         AND CAST(strftime('%s', updated_at) AS INTEGER) < CAST(strftime('%s', 'now', ?) AS INTEGER)`,
      [`-${SYNCED_RETENTION_DAYS} days`]
    );
    return affectedRows(result);
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

  /**
   * Marca un item como failed PERMANENTEMENTE (sin reintentos).
   * Usado para rechazos de seguridad (multi-tenant, datos maliciosos).
   * Fuerza attempts = max_attempts para evitar backoff.
   */
  static async markAsPermanentlyFailed(id: string, error: string): Promise<void> {
    const item = await this.findById(id);
    if (!item) return;

    await localDb.execute(
      `UPDATE sync_queue
       SET sync_status = 'failed',
           attempts = max_attempts,
           last_error = ?,
           updated_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [error, id]
    );
  }

  /**
   * Obtiene todos los items de la cola (para panel de diagnóstico).
   */
  static async getAll(limit: number = 100): Promise<SyncQueueItem[]> {
    return await localDb.select<SyncQueueItem>(
      "SELECT * FROM sync_queue ORDER BY created_at DESC LIMIT ?",
      [limit]
    );
  }

  /**
   * Obtiene conteo agrupado por status.
   */
  static async countByStatus(): Promise<{
    pending: number;
    syncing: number;
    synced: number;
    failed: number;
  }> {
    const results = await localDb.select<{ sync_status: string; count: number }>(
      `SELECT sync_status, COUNT(*) as count
       FROM sync_queue
       GROUP BY sync_status`
    );

    const counts = { pending: 0, syncing: 0, synced: 0, failed: 0 };
    for (const row of results) {
      if (row.sync_status in counts) {
        counts[row.sync_status as keyof typeof counts] = row.count;
      }
    }
    return counts;
  }

  /**
   * Elimina un item de la cola (para limpieza manual).
   */
  static async deleteById(id: string): Promise<void> {
    await localDb.execute("DELETE FROM sync_queue WHERE id = ?", [id]);
  }

  /**
   * Elimina todos los items fallidos (limpieza masiva).
   */
  static async deleteAllFailed(): Promise<number> {
    const result = await localDb.execute(
      "DELETE FROM sync_queue WHERE sync_status = 'failed'"
    );
    return affectedRows(result);
  }

  /**
   * Resetea un item a pending (para reintentar manualmente).
   */
  static async resetToPending(id: string): Promise<void> {
    await localDb.execute(
      `UPDATE sync_queue
       SET sync_status = 'pending',
           attempts = 0,
           last_error = NULL,
           next_retry_at = NULL,
           updated_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [id]
    );
  }

  /**
   * Resetea todos los items fallidos a pending.
   */
  static async resetAllFailed(): Promise<number> {
    const result = await localDb.execute(
      `UPDATE sync_queue
       SET sync_status = 'pending',
           attempts = 0,
           last_error = NULL,
           next_retry_at = NULL,
           updated_at = CURRENT_TIMESTAMP
       WHERE sync_status = 'failed'`
    );
    return affectedRows(result);
  }
}
