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
  static async close(localUuid: string, closingAmount: number): Promise<void> {
    await localDb.execute(
      `UPDATE local_cash_sessions 
       SET status = 'closed', 
           closing_amount = ?, 
           closed_at = CURRENT_TIMESTAMP,
           sync_status = 'pending'
       WHERE local_uuid = ?`,
      [closingAmount, localUuid]
    );
    console.log(`[CashSessionRepository] Sesión cerrada localmente: ${localUuid}`);
  }

  /**
   * Calcula el balance actual de una sesión abierta.
   * 
   * Balance = opening_amount + sum(payments en efectivo) - sum(withdrawals) + sum(deposits)
   * 
   * NOTA: Por ahora solo considera opening_amount.
   * TODO: Integrar con CashMovementRepository cuando esté implementado.
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

    // Por ahora solo retornamos opening_amount
    // TODO: Cuando implementemos CashMovementRepository, calcular balance completo
    return {
      opening_amount: session.opening_amount,
      cash_payments: 0,
      withdrawals: 0,
      deposits: 0,
      adjustments: 0,
      current_balance: session.opening_amount,
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
