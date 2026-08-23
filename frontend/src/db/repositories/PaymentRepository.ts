import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";
import { SyncQueueRepository } from "./SyncQueueRepository";

export interface LocalPayment {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  order_local_uuid: string | null;
  order_cloud_id: string | null;
  payment_method: "cash" | "card" | "transfer" | "gift_card";
  payment_method_uuid: string | null;
  amount: number;
  tip_amount: number;
  reference_code: string | null;
  status: "pending" | "completed" | "failed";
  idempotency_key: string;
  sync_status: "pending" | "syncing" | "synced" | "failed";
  created_at: string;
}

export interface CreatePaymentPayload {
  company_id: string;
  branch_id: string;
  order_local_uuid?: string;
  order_cloud_id?: string;
  payment_method: "cash" | "card" | "transfer" | "gift_card";
  payment_method_uuid?: string;
  amount: number;
  tip_amount?: number;
  reference_code?: string;
  notes?: string;
}

export class PaymentRepository {
  /**
   * Resuelve el UUID del método de pago a partir del code (cash/card/transfer/gift_card).
   */
  static async resolvePaymentMethodUuid(code: string): Promise<string | null> {
    const codeMap: Record<string, string> = {
      cash: "CASH",
      card: "CARD",
      transfer: "TRANSFER",
      gift_card: "GIFT_CARD",
    };
    const dbCode = codeMap[code] || code.toUpperCase();
    const results = await localDb.select<{ uuid: string }>(
      "SELECT uuid FROM local_payment_methods WHERE code = ? AND is_active = 1 LIMIT 1",
      [dbCode]
    );
    return results[0]?.uuid || null;
  }

  /**
   * Registra un pago local y lo encola automáticamente para sincronización.
   */
  static async create(payload: CreatePaymentPayload): Promise<LocalPayment> {
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();

    // Resolver payment_method_uuid si no se pasó
    let paymentMethodUuid = payload.payment_method_uuid || null;
    if (!paymentMethodUuid) {
      paymentMethodUuid = await this.resolvePaymentMethodUuid(payload.payment_method);
    }

    await localDb.execute(
      `INSERT INTO local_payments (
        local_uuid, company_id, branch_id, order_local_uuid, order_cloud_id,
        payment_method, amount, tip_amount, reference_code, status,
        idempotency_key, sync_status, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending', CURRENT_TIMESTAMP)`,
      [
        local_uuid,
        payload.company_id,
        payload.branch_id,
        payload.order_local_uuid || null,
        payload.order_cloud_id || null,
        payload.payment_method,
        payload.amount,
        payload.tip_amount || 0,
        payload.reference_code || null,
        idempotency_key,
      ]
    );

    const payment = (await this.findByLocalUuid(local_uuid)) as LocalPayment;

    // Encolar automáticamente para sincronización
    await SyncQueueRepository.enqueue({
      company_id: payload.company_id,
      branch_id: payload.branch_id,
      entity_type: "payment",
      entity_local_uuid: local_uuid,
      action: "create",
      payload: {
        ...payment,
        payment_method_uuid: paymentMethodUuid,
        order_local_uuid: payload.order_local_uuid || null,
        notes: payload.notes || null,
        idempotency_key,
      },
    });

    console.log(`[PaymentRepository] 📤 Pago encolado para sync: ${local_uuid}`);
    return payment;
  }

  /**
   * Busca un pago por local_uuid.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalPayment | null> {
    const results = await localDb.select<LocalPayment>(
      "SELECT * FROM local_payments WHERE local_uuid = ?",
      [localUuid]
    );
    return results[0] || null;
  }

  /**
   * Lista todos los pagos de un pedido.
   */
  static async findByOrderLocalUuid(orderLocalUuid: string): Promise<LocalPayment[]> {
    return await localDb.select<LocalPayment>(
      "SELECT * FROM local_payments WHERE order_local_uuid = ? ORDER BY created_at ASC",
      [orderLocalUuid]
    );
  }

  /**
   * Calcula el total pagado de un pedido.
   */
  static async getTotalPaidByOrderLocalUuid(orderLocalUuid: string): Promise<number> {
    const results = await localDb.select<{ total: number }>(
      "SELECT COALESCE(SUM(amount), 0) as total FROM local_payments WHERE order_local_uuid = ? AND status = 'pending'",
      [orderLocalUuid]
    );
    return results[0]?.total || 0;
  }

  /**
   * Marca el pago como sincronizado.
   */
  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_payments SET cloud_id = ?, sync_status = 'synced' WHERE local_uuid = ?`,
      [cloudId, localUuid]
    );
  }

  /**
   * Marca un pago como fallido con error.
   */
  static async markSyncError(localUuid: string, error: string): Promise<void> {
    await localDb.execute(
      "UPDATE local_payments SET sync_status = 'failed', sync_error = ? WHERE local_uuid = ?",
      [error, localUuid]
    );
  }
}
