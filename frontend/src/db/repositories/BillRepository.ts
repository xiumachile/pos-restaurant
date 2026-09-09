import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";
import { SyncQueueRepository } from "./SyncQueueRepository";

export type BillStatus = "open" | "partial" | "paid" | "cancelled";
export type BillSyncStatus = "pending" | "syncing" | "synced" | "failed";

export interface LocalBill {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  order_local_uuid: string | null;
  order_cloud_id: string | null;
  bill_number: string;
  subtotal: number;
  discount_total: number;
  tax_total: number;
  tip_amount: number;
  grand_total: number;
  paid_amount: number;
  remaining_amount: number;
  status: BillStatus;
  idempotency_key: string;
  sync_status: BillSyncStatus;
  sync_error: string | null;
  notes: string | null;
  created_at: string;
}

export interface CreateBillPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  order_local_uuid?: string;
  order_cloud_id?: string;
  bill_number: string;
  subtotal: number;
  discount_total?: number;
  tax_total?: number;
  tip_amount?: number;
  grand_total: number;
  notes?: string;
}

export class BillRepository {
  /**
   * Crea una bill local y la encola automáticamente para sincronización.
   * El paid_amount inicia en 0, remaining_amount = grand_total.
   */
  static async create(payload: CreateBillPayload): Promise<LocalBill> {
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();
    const now = new Date().toISOString();

    const discount_total = payload.discount_total ?? 0;
    const tax_total = payload.tax_total ?? 0;
    const tip_amount = payload.tip_amount ?? 0;

    await localDb.execute(
      `INSERT INTO local_bills (
        local_uuid, company_id, branch_id, terminal_id,
        order_local_uuid, order_cloud_id, bill_number,
        subtotal, discount_total, tax_total, tip_amount, grand_total,
        paid_amount, remaining_amount, status,
        idempotency_key, sync_status, notes, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'open', ?, 'pending', ?, ?)`,
      [
        local_uuid,
        payload.company_id,
        payload.branch_id,
        payload.terminal_id || null,
        payload.order_local_uuid || null,
        payload.order_cloud_id || null,
        payload.bill_number,
        payload.subtotal,
        discount_total,
        tax_total,
        tip_amount,
        payload.grand_total,
        payload.grand_total, // remaining_amount = grand_total
        idempotency_key,
        payload.notes || null,
        now,
      ]
    );

    const bill = (await this.findByLocalUuid(local_uuid)) as LocalBill;

    // Encolar automáticamente para sincronización
    await SyncQueueRepository.enqueue({
      company_id: payload.company_id,
      branch_id: payload.branch_id,
      entity_type: "bill",
      entity_local_uuid: local_uuid,
      action: "create",
      payload: {
        ...bill,
        idempotency_key,
      },
    });

    console.log(`[BillRepository] 📤 Bill encolada para sync: ${local_uuid}`);
    return bill;
  }

  /**
   * Busca bill por local_uuid.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalBill | null> {
    const results = await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE local_uuid = ?",
      [localUuid]
    );
    return results[0] || null;
  }

  /**
   * Busca bill por cloud_id (ID del backend).
   */
  static async findByCloudId(cloudId: string): Promise<LocalBill | null> {
    const results = await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE cloud_id = ?",
      [cloudId]
    );
    return results[0] || null;
  }

  /**
   * Lista todas las bills de un order.
   */
  static async findByOrder(orderLocalUuid: string): Promise<LocalBill[]> {
    return await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE order_local_uuid = ? ORDER BY created_at ASC",
      [orderLocalUuid]
    );
  }

  /**
   * Lista todas las bills de una branch.
   */
  static async findByBranch(branchId: string): Promise<LocalBill[]> {
    return await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE branch_id = ? ORDER BY created_at DESC",
      [branchId]
    );
  }

  /**
   * Lista bills por status.
   */
  static async findByStatus(status: BillStatus): Promise<LocalBill[]> {
    return await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE status = ? ORDER BY created_at DESC",
      [status]
    );
  }

  /**
   * Registra un pago en la bill: actualiza paid_amount, remaining_amount y status.
   * Si remaining_amount llega a 0, status cambia a 'paid'.
   */
  static async registerPayment(localUuid: string, amount: number): Promise<LocalBill> {
    const bill = await this.findByLocalUuid(localUuid);
    if (!bill) {
      throw new Error(`Bill ${localUuid} not found`);
    }

    if (bill.status === "paid" || bill.status === "cancelled") {
      throw new Error(`Bill ${localUuid} is ${bill.status}, cannot receive payment`);
    }

    const newPaidAmount = bill.paid_amount + amount;
    const newRemainingAmount = Math.max(0, bill.grand_total - newPaidAmount);
    const newStatus: BillStatus =
      newRemainingAmount <= 0.01 ? "paid" : newPaidAmount > 0 ? "partial" : "open";

    await localDb.execute(
      `UPDATE local_bills
       SET paid_amount = ?, remaining_amount = ?, status = ?,
           sync_status = 'pending', sync_error = NULL
       WHERE local_uuid = ?`,
      [newPaidAmount, newRemainingAmount, newStatus, localUuid]
    );

    // Encolar update para sync
    const updated = await this.findByLocalUuid(localUuid);
    if (updated) {
      await SyncQueueRepository.enqueue({
        company_id: updated.company_id,
        branch_id: updated.branch_id,
        entity_type: "bill",
        entity_local_uuid: localUuid,
        action: "update",
        payload: {
          paid_amount: newPaidAmount,
          remaining_amount: newRemainingAmount,
          status: newStatus,
        },
      });
    }

    return updated!;
  }

  /**
   * Marca la bill como cancelada.
   */
  static async cancel(localUuid: string, reason?: string): Promise<LocalBill> {
    const bill = await this.findByLocalUuid(localUuid);
    if (!bill) {
      throw new Error(`Bill ${localUuid} not found`);
    }

    // Si hay reason, usarlo; si no, preservar notes actual
    const finalNotes = reason || bill.notes || null;

    await localDb.execute(
      `UPDATE local_bills
       SET status = 'cancelled',
           notes = ?,
           sync_status = 'pending',
           sync_error = NULL
       WHERE local_uuid = ?`,
      [finalNotes, localUuid]
    );

    const updated = await this.findByLocalUuid(localUuid);
    if (updated) {
      await SyncQueueRepository.enqueue({
        company_id: updated.company_id,
        branch_id: updated.branch_id,
        entity_type: "bill",
        entity_local_uuid: localUuid,
        action: "update",
        payload: { 
          status: "cancelled",
          reason: finalNotes 
        },
      });
    }

    return updated!;
  }

  /**
   * Actualiza cloud_id y marca como sync_status = 'synced'.
   * Llamado después de sincronización exitosa con el backend.
   */
  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_bills
       SET cloud_id = ?, sync_status = 'synced', sync_error = NULL
       WHERE local_uuid = ?`,
      [cloudId, localUuid]
    );
  }

  /**
   * Marca la bill con error de sincronización.
   */
  static async markAsFailed(localUuid: string, error: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_bills
       SET sync_status = 'failed', sync_error = ?
       WHERE local_uuid = ?`,
      [error, localUuid]
    );
  }

  /**
   * Lista bills pendientes de sincronización.
   */
  static async findPendingSync(): Promise<LocalBill[]> {
    return await localDb.select<LocalBill>(
      "SELECT * FROM local_bills WHERE sync_status IN ('pending', 'failed') ORDER BY created_at ASC"
    );
  }

  /**
   * Lista bills abiertas (status = 'open' o 'partial') de una branch.
   */
  static async findOpenByBranch(branchId: string): Promise<LocalBill[]> {
    const allBills = await localDb.select<LocalBill>(
      `SELECT * FROM local_bills WHERE branch_id = ? ORDER BY created_at ASC`,
      [branchId]
    );
    
    // Filtrar en JavaScript: solo open o partial
    return allBills.filter(b => b.status === "open" || b.status === "partial");
  }
}
