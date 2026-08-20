import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";

export interface LocalPayment {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  order_local_uuid: string | null;
  order_cloud_id: string | null;
  payment_method: "cash" | "card" | "transfer" | "gift_card";
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
  amount: number;
  tip_amount?: number;
  reference_code?: string;
}

export class PaymentRepository {
  /**
   * Registra un pago local.
   */
  static async create(payload: CreatePaymentPayload): Promise<LocalPayment> {
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();

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

    return await this.findByLocalUuid(local_uuid) as LocalPayment;
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
}
