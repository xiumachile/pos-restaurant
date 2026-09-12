import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";
import type { CashSession } from "@/types/payments";

export interface LocalCashSession {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  user_id: string;
  user_name: string | null;
  status: "open" | "closed" | "suspended";
  opening_amount: number;
  closing_amount: number | null;
  opened_at: string;
  closed_at: string | null;
  sync_status: "pending" | "syncing" | "synced" | "failed";
  created_at: string;
}

export interface CreateCashSessionPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  user_id: string;
  user_name?: string | null;
  opening_amount: number;
  opened_at?: string;
  cloud_id?: string | null;
  sync_status?: "pending" | "synced";
}

export class CashSessionRepository {
  /**
   * Crea una nueva sesión de caja local.
   */
  static async create(payload: CreateCashSessionPayload): Promise<LocalCashSession> {
    const local_uuid = uuidv4();

    await localDb.execute(
      `INSERT INTO local_cash_sessions 
       (local_uuid, cloud_id, company_id, branch_id, terminal_id, user_id, user_name, 
        status, opening_amount, opened_at, sync_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?)`,
      [
        local_uuid,
        payload.cloud_id || null,
        payload.company_id,
        payload.branch_id,
        payload.terminal_id || null,
        payload.user_id,
        payload.user_name || null,
        payload.opening_amount,
        payload.opened_at || new Date().toISOString(),
        payload.sync_status || "pending",
      ]
    );

    console.log(`[CashSessionRepository] Sesión creada: ${local_uuid} (cloud: ${payload.cloud_id || 'local'})`);
    const result = await this.findByLocalUuid(local_uuid);
    return result!;
  }

  /**
   * Busca la sesión activa para company + branch + terminal + user.
   *
   * P0 Caja Offline:
   * NUNCA debe devolver una caja de otra empresa, sucursal, terminal o usuario.
   */
  static async findActive(
    companyId: string,
    branchId: string,
    userId: string,
    terminalId: string
  ): Promise<LocalCashSession | null> {
    const rows = await localDb.select<LocalCashSession>(
      `SELECT * FROM local_cash_sessions 
       WHERE status = 'open'
         AND company_id = ?
         AND branch_id = ?
         AND user_id = ?
         AND terminal_id = ?
       ORDER BY opened_at DESC 
       LIMIT 1`,
      [companyId, branchId, userId, terminalId]
    );
    return rows[0] || null;
  }

  /**
   * Busca sesión por local_uuid.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalCashSession | null> {
    const rows = await localDb.select<LocalCashSession>(
      "SELECT * FROM local_cash_sessions WHERE local_uuid = ?",
      [localUuid]
    );
    return rows[0] || null;
  }

  /**
   * Busca sesión por cloud_id.
   */
  static async findByCloudId(cloudId: string): Promise<LocalCashSession | null> {
    const rows = await localDb.select<LocalCashSession>(
      "SELECT * FROM local_cash_sessions WHERE cloud_id = ?",
      [cloudId]
    );
    return rows[0] || null;
  }

  /**
   * Lista todas las sesiones de un branch.
   */
  static async findByBranch(branchId: string): Promise<LocalCashSession[]> {
    return await localDb.select<LocalCashSession>(
      "SELECT * FROM local_cash_sessions WHERE branch_id = ? ORDER BY opened_at DESC",
      [branchId]
    );
  }

  /**
   * Marca sesión como sincronizada con el backend.
   */
  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_cash_sessions 
       SET cloud_id = ?, sync_status = 'synced' 
       WHERE local_uuid = ?`,
      [cloudId, localUuid]
    );
  }

  /**
   * Cierra la sesión con el monto final.
   */
  /**
   * Cierra una sesión de caja localmente.
   * 
   * @param uuidOrCloudId - Puede ser local_uuid o cloud_id (para retry de sync)
   * @param closingAmount - Monto de cierre contado por el cajero
   * @param syncStatus - Estado de sync ('pending' si necesita sync, 'synced' si ya se sincronizó)
   */
  static async close(
    uuidOrCloudId: string, 
    closingAmount: number,
    syncStatus: 'pending' | 'synced' = 'pending'
  ): Promise<void> {
    // Intentar primero por local_uuid
    let session = await this.findByLocalUuid(uuidOrCloudId);
    
    if (!session) {
      // Si no encontró por local_uuid, intentar por cloud_id (retry pattern)
      session = await this.findByCloudId(uuidOrCloudId);
    }
    
    if (!session) {
      console.warn(`[CashSessionRepository] Sesión no encontrada: ${uuidOrCloudId}`);
      return;
    }

    await localDb.execute(
      `UPDATE local_cash_sessions 
       SET status = 'closed', 
           closing_amount = ?, 
           closed_at = CURRENT_TIMESTAMP,
           sync_status = ?
       WHERE local_uuid = ?`,
      [closingAmount, syncStatus, session.local_uuid]
    );

    console.log(`[CashSessionRepository] Sesión cerrada localmente: ${session.local_uuid} (sync: ${syncStatus})`);
  }

  /**
   * Calcula el balance actual de una sesión abierta.
   * 
   * Balance = opening_amount + sum(movements.amount)
   * donde amount ya tiene el signo correcto (positivo/negativo según tipo)
   */
  static async getBalance(localUuid: string): Promise<{
    opening_amount: number;
    cash_payments: number;
    withdrawals: number;
    deposits: number;
    adjustments: number;
    current_balance: number;
  }> {
    const session = await this.findByLocalUuid(localUuid);
    if (!session) {
      throw new Error(`Sesión ${localUuid} no encontrada`);
    }

    // Obtener todos los movimientos de la sesión
    const movements = await localDb.select<{ type: string; amount: number }>(
      "SELECT type, amount FROM local_cash_movements WHERE cash_session_local_uuid = ?",
      [localUuid]
    );

    let cash_payments = 0;
    let withdrawals = 0;
    let deposits = 0;
    let adjustments = 0;

    for (const movement of movements) {
      switch (movement.type) {
        case "payment":
          cash_payments += movement.amount;
          break;
        case "withdrawal":
          withdrawals += Math.abs(movement.amount);
          break;
        case "deposit":
          deposits += movement.amount;
          break;
        case "adjustment":
          adjustments += Math.abs(movement.amount);
          break;
      }
    }

    const current_balance = session.opening_amount + cash_payments - withdrawals + deposits - adjustments;

    return {
      opening_amount: session.opening_amount,
      cash_payments,
      withdrawals,
      deposits,
      adjustments,
      current_balance,
    };
  }

  /**
   * Convierte LocalCashSession a CashSession (tipo del backend).
   */
  static toCashSession(local: LocalCashSession): CashSession {
    return {
      uuid: local.cloud_id || local.local_uuid,
      session_number: `LOCAL-${local.local_uuid.slice(0, 8).toUpperCase()}`,
      status: local.status,
      opening_amount: local.opening_amount,
      closing_amount: local.closing_amount,
      expected_amount: local.opening_amount,
      difference: null,
      opening_notes: null,
      closing_notes: null,
      opened_at: local.opened_at,
      closed_at: local.closed_at,
      user: local.user_name ? {
        uuid: local.user_id,
        name: local.user_name,
      } : undefined,
      register: null,
    } as CashSession;
  }
}
