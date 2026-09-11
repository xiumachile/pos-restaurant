import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";

export type PrintJobType = "receipt" | "kitchen_command" | "bar_command";
export type PrintJobStatus = "pending" | "printing" | "completed" | "failed";
export type PrinterType = "receipt" | "kitchen" | "bar";

export interface LocalPrintJob {
  local_uuid: string;
  cloud_id: string | null;
  idempotency_key: string;
  job_type: PrintJobType;
  entity_type: string;
  entity_uuid: string;
  payload: string;
  escpos_base64: string | null;
  printer_name: string | null;
  printer_type: PrinterType | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  user_id: string;
  user_name: string | null;
  reference_number: string | null;
  status: PrintJobStatus;
  attempts: number;
  max_attempts: number;
  error_message: string | null;
  created_at: string;
  updated_at: string;
  printed_at: string | null;
}

export interface CreatePrintJobPayload {
  job_type: PrintJobType;
  entity_type: string;
  entity_uuid: string;
  payload: any;
  escpos_base64?: string;
  printer_name?: string;
  printer_type?: PrinterType;
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  user_id: string;
  user_name?: string;
  reference_number?: string;
  idempotency_key?: string;
  max_attempts?: number;
}

// Timeout para considerar un job "printing" como abandonado (2 minutos)
const PRINTING_TIMEOUT_MINUTES = 2;

export class LocalPrintJobRepository {
  /**
   * Crea un nuevo print job local.
   * Retorna el UUID local generado.
   */
  static async create(data: CreatePrintJobPayload): Promise<string> {
    const localUuid = uuidv4();
    const idempotencyKey = data.idempotency_key || `print-${localUuid}`;
    const payloadStr = typeof data.payload === "string" 
      ? data.payload 
      : JSON.stringify(data.payload);

    await localDb.execute(
      `INSERT INTO local_print_jobs (
        local_uuid, cloud_id, idempotency_key, job_type, entity_type, entity_uuid,
        payload, escpos_base64, printer_name, printer_type,
        company_id, branch_id, terminal_id, user_id, user_name,
        reference_number, status, attempts, max_attempts,
        created_at, updated_at
      ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, datetime('now'), datetime('now'))`,
      [
        localUuid,
        idempotencyKey,
        data.job_type,
        data.entity_type,
        data.entity_uuid,
        payloadStr,
        data.escpos_base64 || null,
        data.printer_name || null,
        data.printer_type || null,
        data.company_id,
        data.branch_id,
        data.terminal_id || null,
        data.user_id,
        data.user_name || null,
        data.reference_number || null,
        data.max_attempts ?? 5,
      ]
    );

    return localUuid;
  }

  /**
   * Obtiene un job por su UUID local.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalPrintJob | null> {
    return await localDb.selectOne<LocalPrintJob>(
      "SELECT * FROM local_print_jobs WHERE local_uuid = ?",
      [localUuid]
    );
  }

  /**
   * Obtiene todos los jobs pendientes, ordenados por fecha de creación.
   */
  static async getPending(limit: number = 50): Promise<LocalPrintJob[]> {
    // Primero recupera jobs "printing" abandonados
    await this.recoverAbandonedPrinting();

    return await localDb.select<LocalPrintJob>(
      `SELECT * FROM local_print_jobs 
       WHERE status = 'pending' 
       ORDER BY created_at ASC 
       LIMIT ?`,
      [limit]
    );
  }

  /**
   * Recupera jobs que quedaron en estado "printing" por más de PRINTING_TIMEOUT_MINUTES.
   * Esto ocurre cuando el proceso se interrumpe (crash, corte de energía).
   */
  static async recoverAbandonedPrinting(): Promise<number> {
    const cutoff = new Date(
      Date.now() - PRINTING_TIMEOUT_MINUTES * 60 * 1000
    ).toISOString();

    const abandoned = await localDb.select<LocalPrintJob>(
      `SELECT * FROM local_print_jobs 
       WHERE status = 'printing' 
         AND updated_at < ?`,
      [cutoff]
    );

    if (abandoned.length === 0) return 0;

    console.log(`[PrintJobRepo] ⚠️  Recuperando ${abandoned.length} jobs printing abandonados`);

    for (const job of abandoned) {
      const attempts = job.attempts + 1;
      
      if (attempts >= job.max_attempts) {
        await this.markAsFailed(
          job.local_uuid,
          `Abandonado tras ${PRINTING_TIMEOUT_MINUTES}min (intento ${attempts})`
        );
      } else {
        await localDb.execute(
          `UPDATE local_print_jobs 
           SET status = 'pending', 
               attempts = ?,
               error_message = ?,
               updated_at = datetime('now')
           WHERE local_uuid = ?`,
          [
            attempts,
            `Print abandonado (timeout ${PRINTING_TIMEOUT_MINUTES}min, intento ${attempts})`,
            job.local_uuid,
          ]
        );
      }
    }

    return abandoned.length;
  }

  /**
   * Marca un job como "printing" (en proceso de impresión).
   */
  static async markAsPrinting(localUuid: string): Promise<void> {
    // Obtener el job actual para calcular attempts + 1 en JS
    // (el mock de tauriSql no evalúa expresiones aritméticas)
    const job = await this.findByLocalUuid(localUuid);
    if (!job) return;

    const newAttempts = job.attempts + 1;

    await localDb.execute(
      `UPDATE local_print_jobs 
       SET status = 'printing', 
           attempts = ?,
           updated_at = datetime('now')
       WHERE local_uuid = ?`,
      [newAttempts, localUuid]
    );
  }

  /**
   * Marca un job como "completed" (impresión exitosa).
   */
  static async markAsCompleted(
    localUuid: string,
    cloudId?: string
  ): Promise<void> {
    await localDb.execute(
      `UPDATE local_print_jobs 
       SET status = 'completed', 
           cloud_id = COALESCE(?, cloud_id),
           printed_at = datetime('now'),
           updated_at = datetime('now')
       WHERE local_uuid = ?`,
      [cloudId || null, localUuid]
    );
  }

  /**
   * Marca un job como "failed" con mensaje de error.
   * Si alcanzó max_attempts, queda en failed permanente.
   * Si no, vuelve a pending para reintentar.
   */
  static async markAsFailed(localUuid: string, error: string): Promise<void> {
    const job = await this.findByLocalUuid(localUuid);
    if (!job) return;

    const attempts = job.attempts;
    const maxAttempts = job.max_attempts;

    if (attempts >= maxAttempts) {
      await localDb.execute(
        `UPDATE local_print_jobs 
         SET status = 'failed', 
             error_message = ?,
             updated_at = datetime('now')
         WHERE local_uuid = ?`,
        [error, localUuid]
      );
    } else {
      // Volver a pending para reintentar
      await localDb.execute(
        `UPDATE local_print_jobs 
         SET status = 'pending', 
             error_message = ?,
             updated_at = datetime('now')
         WHERE local_uuid = ?`,
        [error, localUuid]
      );
    }
  }

  /**
   * Marca un job como "failed" permanentemente (sin retry).
   * Usado para errores críticos que no son transitorios
   * (ej: job sin bytes ESC/POS, datos corruptos).
   */
  static async markAsPermanentlyFailed(localUuid: string, error: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_print_jobs 
       SET status = 'failed', 
           error_message = ?,
           attempts = max_attempts,
           updated_at = datetime('now')
       WHERE local_uuid = ?`,
      [error, localUuid]
    );
  }

  /**
   * Cuenta jobs por status.
   */
  static async countByStatus(): Promise<{
    pending: number;
    printing: number;
    completed: number;
    failed: number;
  }> {
    const results = await localDb.select<{ status: string; count: number }>(
      `SELECT status, COUNT(*) as count 
       FROM local_print_jobs 
       GROUP BY status`
    );

    const counts = { pending: 0, printing: 0, completed: 0, failed: 0 };
    for (const row of results) {
      if (row.status in counts) {
        counts[row.status as keyof typeof counts] = row.count;
      }
    }
    return counts;
  }

  /**
   * Cuenta jobs pendientes.
   */
  static async countPending(): Promise<number> {
    const result = await localDb.selectOne<{ count: number }>(
      "SELECT COUNT(*) as count FROM local_print_jobs WHERE status = 'pending'"
    );
    return result?.count || 0;
  }

  /**
   * Limpia jobs completados antiguos (más de 7 días).
   */
  static async cleanupOldCompleted(): Promise<number> {
    const cutoff = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();
    return await localDb.execute(
      `DELETE FROM local_print_jobs 
       WHERE status = 'completed' AND printed_at < ?`,
      [cutoff]
    );
  }

  /**
   * Obtiene todos los jobs (para panel de diagnóstico).
   */
  static async getAll(limit: number = 100): Promise<LocalPrintJob[]> {
    return await localDb.select<LocalPrintJob>(
      "SELECT * FROM local_print_jobs ORDER BY created_at DESC LIMIT ?",
      [limit]
    );
  }

  /**
   * Elimina un job por UUID.
   */
  static async deleteById(localUuid: string): Promise<void> {
    await localDb.execute(
      "DELETE FROM local_print_jobs WHERE local_uuid = ?",
      [localUuid]
    );
  }
}
