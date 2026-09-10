import { localDb } from "@/db/localDb";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { BillRepository } from "@/db/repositories/BillRepository";
import { PaymentRepository } from "@/db/repositories/PaymentRepository";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { CashMovementRepository } from "@/db/repositories/CashMovementRepository";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { getAuthContextSafe, getCashierContextSafe } from "./authContext";
import type { LocalOrder } from "@/db/repositories/OrderRepository";
import type { LocalBill } from "@/types/bills";
import type { LocalPayment } from "@/db/repositories/PaymentRepository";

export type PaymentMethodCode = "cash" | "card" | "transfer" | "gift_card";

export interface CreatePaymentOfflinePayload {
  orderLocalUuid: string;
  paymentMethod: PaymentMethodCode;
  amount: number;
  tipAmount?: number;
  referenceCode?: string;
  notes?: string;
  autoCreateBill?: boolean; // default: true
}

export interface CreatePaymentOfflineResult {
  payment: LocalPayment;
  bill: LocalBill | null;
  orderPaid: boolean;
  orderStatusUpdated: boolean;
  tableReleased: boolean;
}

// Estados del order en los que es válido cobrar
const PAYABLE_STATUSES: LocalOrder["status"][] = [
  "served",
  "ready",
  "ready_for_pickup",
  "dispatched",
  "delivered",
];

export class OfflinePaymentError extends Error {
  constructor(public code: string, message: string) {
    super(`${code}: ${message}`);
    this.name = "OfflinePaymentError";
  }
}

/**
 * Servicio para crear pagos offline de forma atómica.
 *
 * Flujo:
 * 1. Valida que el order existe y está en estado pagable
 * 2. Busca o crea una bill local asociada al order
 * 3. Registra el pago en la bill (actualizando paid/remaining/status)
 * 4. Crea el LocalPayment con referencia al order y bill
 * 5. Si la bill queda 100% pagada:
 *    - Marca el order como 'paid'
 *    - Libera la mesa (status = 'available') si existe
 * 6. Todo dentro de una transacción local (atomicidad)
 * 7. Todos los cambios se encolan automáticamente en SyncQueue
 */
export const offlinePaymentService = {
  /**
   * Crea un pago offline de forma atómica.
   *
   * @throws OfflinePaymentError si el order no existe, no es pagable, o amount inválido
   */
  async createPaymentOffline(
    payload: CreatePaymentOfflinePayload
  ): Promise<CreatePaymentOfflineResult> {
    const {
      orderLocalUuid,
      paymentMethod,
      amount,
      tipAmount = 0,
      referenceCode,
      notes,
      autoCreateBill = true,
    } = payload;

    // ═══════════════════════════════════════════════════════
    // VALIDACIONES PRE-TRANSACCIÓN
    // ═══════════════════════════════════════════════════════

    if (amount <= 0) {
      throw new OfflinePaymentError(
        "INVALID_AMOUNT",
        `amount must be positive, got ${amount}`
      );
    }

    if (tipAmount < 0) {
      throw new OfflinePaymentError(
        "INVALID_TIP",
        `tipAmount must be non-negative, got ${tipAmount}`
      );
    }

    const order = await OrderRepository.findByLocalUuid(orderLocalUuid);
    if (!order) {
      throw new OfflinePaymentError(
        "ORDER_NOT_FOUND",
        `Order ${orderLocalUuid} not found`
      );
    }

    if (!PAYABLE_STATUSES.includes(order.status)) {
      throw new OfflinePaymentError(
        "ORDER_NOT_PAYABLE",
        `Order ${orderLocalUuid} is in status '${order.status}', expected one of: ${PAYABLE_STATUSES.join(", ")}`
      );
    }

    // ═══════════════════════════════════════════════════════
    // TRANSACCIÓN ATÓMICA
    // ═══════════════════════════════════════════════════════

    return await localDb.transaction(async () => {
      // 1. Buscar bill existente del order
      const existingBills = await BillRepository.findByOrder(orderLocalUuid);
      let bill: LocalBill | null = existingBills[0] || null;

      // 2. Si no existe bill y autoCreateBill=true, crear una
      if (!bill) {
        if (!autoCreateBill) {
          throw new OfflinePaymentError(
            "NO_BILL_FOUND",
            `Order ${orderLocalUuid} has no bill and autoCreateBill=false`
          );
        }

        // 🔒 Propagar company/branch/terminal desde el order (IDs garantizados)
        bill = await BillRepository.create({
          company_id: order.company_id,
          branch_id: order.branch_id,
          terminal_id: order.terminal_id || undefined,
          order_local_uuid: order.local_uuid,
          order_cloud_id: order.cloud_id || undefined,
          bill_number: `${order.order_number}-1`,
          subtotal: order.subtotal,
          discount_total: order.discount_total,
          tax_total: order.tax_total,
          tip_amount: order.tip_amount,
          grand_total: order.grand_total,
        });
      }

      // 3. Validar que amount <= remaining_amount
      if (amount > bill.remaining_amount + 0.01) {
        throw new OfflinePaymentError(
          "AMOUNT_EXCEEDS_REMAINING",
          `amount ${amount} exceeds remaining ${bill.remaining_amount}`
        );
      }

      // 4. Validar que bill no esté paid/cancelled
      if (bill.status === "paid") {
        throw new OfflinePaymentError(
          "BILL_ALREADY_PAID",
          `Bill ${bill.local_uuid} is already paid`
        );
      }
      if (bill.status === "cancelled") {
        throw new OfflinePaymentError(
          "BILL_CANCELLED",
          `Bill ${bill.local_uuid} is cancelled`
        );
      }

      // 5. Registrar pago en la bill (actualiza paid/remaining/status)
      const updatedBill = await BillRepository.registerPayment(
        bill.local_uuid,
        amount
      );

      // 6. Crear el LocalPayment (propaga company/branch desde el order)
      const payment = await PaymentRepository.create({
        company_id: order.company_id,
        branch_id: order.branch_id,
        order_local_uuid: order.local_uuid,
        order_cloud_id: order.cloud_id || undefined,
        payment_method: paymentMethod,
        amount,
        tip_amount: tipAmount,
        reference_code: referenceCode,
        notes,
      });

      // 7. Si es pago en efectivo y hay sesión abierta, registrar como movimiento de caja
      let cashMovementCreated = false;
      if (paymentMethod === "cash") {
        try {
          // 🔒 FIX DE AUDITORÍA: usar contexto del cajero actual (no waiter_id)
          // El cajero que procesa el pago puede ser diferente al mesero del pedido
          const ctx = getCashierContextSafe();
          if (!ctx) {
            console.warn("[offlinePaymentService] ⚠️ Sin contexto de caja, no se puede registrar movimiento");
          } else {
            const session = await CashSessionRepository.findActive(
              order.company_id,
              order.branch_id,
              ctx.user_id,  // ✅ UUID del cajero actual
              ctx.terminal_id
            );
            if (session) {
              await this.registerCashPayment(
                session.local_uuid,
                amount,
                payment.local_uuid,
                payment.cloud_id || undefined,
                notes
              );
              cashMovementCreated = true;
              console.log(`[offlinePaymentService] ✅ Movimiento de caja registrado: ${amount} por ${ctx.user_name}`);
            } else {
              console.warn("[offlinePaymentService] ⚠️ No hay sesión de caja abierta para este cajero");
            }
          }
        } catch (movementErr: any) {
          // No crítico: si falla registrar movimiento, el pago sigue siendo válido
          console.warn("[offlinePaymentService] ⚠️ No se pudo registrar movimiento de caja:", movementErr?.message);
        }
      }

      // 7. Si bill está completamente pagada → actualizar order + liberar mesa
      let orderStatusUpdated = false;
      let tableReleased = false;

      if (updatedBill.status === "paid") {
        // Marcar order como paid
        await OrderRepository.updateStatus(order.local_uuid, "paid");
        orderStatusUpdated = true;

        // Liberar mesa si existe
        if (order.table_id) {
          await this.releaseTableOffline(order.table_id);
          tableReleased = true;
        }
      }

      return {
        payment,
        bill: updatedBill,
        orderPaid: updatedBill.status === "paid",
        orderStatusUpdated,
        tableReleased,
      };
    });
  },

  /**
   * Libera una mesa offline: actualiza status + encola sync.
   *
   * Similar a localTablesService.markAvailable() pero:
   * - No elimina mutaciones pendientes (porque las preserva para auditoría)
   * - Encola 'table_status' en SyncQueue para sincronización
   */
  async releaseTableOffline(tableUuid: string): Promise<void> {
    // 1. Actualizar local_tables
    await localDb.execute(
      `UPDATE local_tables 
       SET status = 'available', 
           current_order_uuid = NULL, 
           last_updated = CURRENT_TIMESTAMP 
       WHERE uuid = ?`,
      [tableUuid]
    );

    // 2. Obtener branch_id y company_id de la mesa
    const tables = await localDb.select<{
      company_id: string;
      branch_id: string;
    }>(
      "SELECT company_id, branch_id FROM local_tables WHERE uuid = ?",
      [tableUuid]
    );

    if (tables.length === 0) return;

    const { company_id, branch_id } = tables[0];

    // 3. Encolar en SyncQueue para sincronización
    await SyncQueueRepository.enqueue({
      company_id,
      branch_id,
      entity_type: "table_status",
      entity_local_uuid: tableUuid,
      action: "update",
      payload: {
        status: "available",
        current_order_uuid: null,
      },
    });
  },

  /**
   * Registra un pago en efectivo como movimiento de caja.
   * 
   * Usado cuando el método de pago es 'cash' y hay una sesión abierta.
   * Crea un movimiento local de tipo 'payment' con balance_after calculado.
   */
  async registerCashPayment(
    cashSessionLocalUuid: string,
    amount: number,
    referenceLocalUuid: string,
    referenceCloudId?: string,
    notes?: string
  ): Promise<any> {
    return await CashMovementRepository.create(cashSessionLocalUuid, {
      type: "payment",
      amount,
      reason: "Pago en efectivo",
      notes,
      reference_type: "payment",
      reference_local_uuid: referenceLocalUuid,
      reference_cloud_id: referenceCloudId,
    });
  },

  /**
   * Lista bills abiertas de una branch (wrapper sobre BillRepository).
   */
  async listOpenBillsByBranch(branchId: string): Promise<LocalBill[]> {
    return BillRepository.findOpenByBranch(branchId);
  },

  /**
   * Obtiene el estado de pago completo de un order.
   */
  async getOrderPaymentStatus(orderLocalUuid: string): Promise<{
    order: LocalOrder | null;
    bills: LocalBill[];
    payments: LocalPayment[];
    totalPaid: number;
    totalRemaining: number;
    isPaid: boolean;
  }> {
    const order = await OrderRepository.findByLocalUuid(orderLocalUuid);
    if (!order) {
      return {
        order: null,
        bills: [],
        payments: [],
        totalPaid: 0,
        totalRemaining: 0,
        isPaid: false,
      };
    }

    const bills = await BillRepository.findByOrder(orderLocalUuid);
    const payments = await PaymentRepository.findByOrderLocalUuid(orderLocalUuid);

    const totalPaid = bills.reduce((sum, b) => sum + b.paid_amount, 0);
    const totalRemaining = bills.reduce((sum, b) => sum + b.remaining_amount, 0);

    return {
      order,
      bills,
      payments,
      totalPaid,
      totalRemaining,
      isPaid: totalRemaining <= 0.01 && bills.length > 0,
    };
  },
};
