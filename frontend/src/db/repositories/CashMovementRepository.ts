import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";
import { SyncQueueRepository } from "./SyncQueueRepository";
import { CashSessionRepository } from "./CashSessionRepository";

/**
 * Tipos de movimiento de caja (local).
 * 
 * Convención:
 * - opening/closing: ciclo de vida de sesión (sync como cash_session)
 * - payment: pago en efectivo (sync como billing/payments)
 * - withdrawal/deposit/adjustment: movimientos manuales (sync como cashier/movements)
 */
export type CashMovementType =
  | "opening"
  | "payment"
  | "withdrawal"
  | "deposit"
  | "adjustment"
  | "closing";

export type CashMovementSyncStatus = "pending" | "syncing" | "synced" | "failed";

export interface LocalCashMovement {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  cash_session_local_uuid: string;
  cash_session_cloud_id: string | null;
  user_id: string;
  user_name: string | null;
  type: CashMovementType;
  amount: number;
  balance_after: number;
  reason: string | null;
  notes: string | null;
  reference_type: string | null;
  reference_local_uuid: string | null;
  reference_cloud_id: string | null;
  authorized_by: string | null;
  authorized_at: string | null;
  idempotency_key: string;
  sync_status: CashMovementSyncStatus;
  sync_error: string | null;
  created_at: string;
}

export interface CreateMovementPayload {
  type: CashMovementType;
  amount: number;
  reason?: string;
  notes?: string;
  reference_type?: string;
  reference_local_uuid?: string;
  reference_cloud_id?: string;
  authorized_by?: string;
  authorized_at?: string;
}

export class CashMovementRepository {
  /**
   * Crea un movimiento de caja local con validación y encolado de sync.
   * 
   * Calcula balance_after automáticamente basado en el estado actual de la sesión.
   */
  static async create(
    cashSessionLocalUuid: string,
    payload: CreateMovementPayload
  ): Promise<LocalCashMovement> {
    const session = await CashSessionRepository.findByLocalUuid(cashSessionLocalUuid);
    if (!session) {
      throw new Error(`Sesión ${cashSessionLocalUuid} no encontrada`);
    }

    if (session.status !== "open") {
      throw new Error(`Sesión ${cashSessionLocalUuid} no está abierta (status: ${session.status})`);
    }

    const balance = await CashSessionRepository.getBalance(cashSessionLocalUuid);
    const signedAmount = this.getSignedAmount(payload.type, payload.amount);
    const balanceAfter = balance.current_balance + signedAmount;

    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();

    await localDb.execute(
      `INSERT INTO local_cash_movements (
        local_uuid, cloud_id, company_id, branch_id, terminal_id,
        cash_session_local_uuid, cash_session_cloud_id, user_id, user_name,
        type, amount, balance_after, reason, notes,
        reference_type, reference_local_uuid, reference_cloud_id,
        authorized_by, authorized_at,
        idempotency_key, sync_status, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)`,
      [
        local_uuid,
        null,
        session.company_id,
        session.branch_id,
        session.terminal_id,
        session.local_uuid,
        session.cloud_id,
        session.user_id,
        session.user_name,
        payload.type,
        signedAmount,
        balanceAfter,
        payload.reason || null,
        payload.notes || null,
        payload.reference_type || null,
        payload.reference_local_uuid || null,
        payload.reference_cloud_id || null,
        payload.authorized_by || null,
        payload.authorized_at || null,
        idempotency_key,
      ]
    );

    const movement = (await this.findByLocalUuid(local_uuid)) as LocalCashMovement;

    // Solo withdrawal/deposit/adjustment se encolan como movimientos.
    // opening/closing se sincronizan como parte de cash_session.
    // payment se sincroniza como billing/payments.
    if (this.shouldSyncAsMovement(payload.type)) {
      await SyncQueueRepository.enqueue({
        company_id: session.company_id,
        branch_id: session.branch_id,
        entity_type: "cash_movement",
        entity_local_uuid: local_uuid,
        action: "create",
        payload: {
          ...movement,
          idempotency_key,
        },
      });
      console.log(`[CashMovementRepository] 📤 Movimiento encolado para sync: ${local_uuid}`);
    } else {
      console.log(`[CashMovementRepository] 📝 Movimiento local (no requiere sync propio): ${local_uuid}`);
    }

    return movement;
  }

  /**
   * Busca movimiento por local_uuid.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalCashMovement | null> {
    const results = await localDb.select<LocalCashMovement>(
      "SELECT * FROM local_cash_movements WHERE local_uuid = ?",
      [localUuid]
    );
    return results[0] || null;
  }

  /**
   * Lista todos los movimientos de una sesión.
   */
  static async findBySession(cashSessionLocalUuid: string): Promise<LocalCashMovement[]> {
    return await localDb.select<LocalCashMovement>(
      `SELECT * FROM local_cash_movements 
       WHERE cash_session_local_uuid = ? 
       ORDER BY created_at ASC`,
      [cashSessionLocalUuid]
    );
  }

  /**
   * Lista movimientos por tipo dentro de una sesión.
   */
  static async findBySessionAndType(
    cashSessionLocalUuid: string,
    type: CashMovementType
  ): Promise<LocalCashMovement[]> {
    return await localDb.select<LocalCashMovement>(
      `SELECT * FROM local_cash_movements 
       WHERE cash_session_local_uuid = ? AND type = ? 
       ORDER BY created_at ASC`,
      [cashSessionLocalUuid, type]
    );
  }

  /**
   * Lista movimientos pendientes de sincronización.
   * 
   * NOTA: Filtra en JavaScript porque el mock de SQLite no soporta IN correctamente.
   */
  static async findPendingSync(): Promise<LocalCashMovement[]> {
    const allMovements = await localDb.select<LocalCashMovement>(
      `SELECT * FROM local_cash_movements ORDER BY created_at ASC`
    );
    
    // Filtrar en JavaScript: solo pending o failed
    return allMovements.filter(m => m.sync_status === 'pending' || m.sync_status === 'failed');
  }

  /**
   * Marca movimiento como sincronizado con cloud_id.
   */
  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_cash_movements 
       SET cloud_id = ?, sync_status = 'synced', sync_error = NULL 
       WHERE local_uuid = ?`,
      [cloudId, localUuid]
    );
  }

  /**
   * Marca movimiento como fallido.
   */
  static async markAsFailed(localUuid: string, error: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_cash_movements 
       SET sync_status = 'failed', sync_error = ? 
       WHERE local_uuid = ?`,
      [error, localUuid]
    );
  }

  /**
   * Retorna el signo del monto según el tipo de movimiento.
   */
  private static getSignedAmount(type: CashMovementType, amount: number): number {
    switch (type) {
      case "opening":
      case "payment":
      case "deposit":
        return Math.abs(amount); // positivo: entra dinero
      case "withdrawal":
      case "adjustment":
      case "closing":
        return -Math.abs(amount); // negativo: sale dinero
      default:
        throw new Error(`Tipo de movimiento inválido: ${type}`);
    }
  }

  /**
   * Indica si el movimiento debe sincronizarse como cashier/movements.
   */
  private static shouldSyncAsMovement(type: CashMovementType): boolean {
    return type === "withdrawal" || type === "deposit" || type === "adjustment";
  }
}
