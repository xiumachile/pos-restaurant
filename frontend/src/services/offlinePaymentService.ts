import { localDb } from "@/db/localDb";
import { OrderRepository, type LocalOrder } from "@/db/repositories/OrderRepository";
import { BillRepository } from "@/db/repositories/BillRepository";
import { PaymentRepository } from "@/db/repositories/PaymentRepository";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { CashMovementRepository } from "@/db/repositories/CashMovementRepository";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";
import { ticketToBase64, type ReceiptData } from "@/services/printing/ticketFormatters";
import { getAuthContextSafe, getCashierContextSafe } from "./authContext";
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
  autoCreateBill?: boolean;
  billLocalUuid?: string;
}

export interface CreatePaymentOfflineResult {
  payment: LocalPayment;
  bill: LocalBill | null;
  orderPaid: boolean;
  orderStatusUpdated: boolean;
  tableReleased: boolean;
}

const PAYABLE_STATUSES: LocalOrder["status"][] = [
  "served", "ready", "ready_for_pickup", "dispatched", "delivered",
];

export class OfflinePaymentError extends Error {
  constructor(public code: string, message: string) {
    super(`${code}: ${message}`);
    this.name = "OfflinePaymentError";
  }
}

export const offlinePaymentService = {
  async createPaymentOffline(payload: CreatePaymentOfflinePayload): Promise<CreatePaymentOfflineResult> {
    const { orderLocalUuid, paymentMethod, amount, tipAmount = 0, referenceCode, notes, autoCreateBill = true, billLocalUuid } = payload;

    // CA-04: Validaciones con mensajes bilingües (ES/ZH)
    if (!Number.isSafeInteger(amount) || amount <= 0) {
      throw new OfflinePaymentError("INVALID_AMOUNT", `El monto debe ser positivo / 金额必须为正数: ${amount}`);
    }

    if (!Number.isSafeInteger(tipAmount) || tipAmount < 0) {
      throw new OfflinePaymentError("INVALID_TIP", `La propina debe ser no negativa / 小费必须为非负数: ${tipAmount}`);
    }

    let order = await OrderRepository.findByLocalUuid(orderLocalUuid);
    if (!order) {
      throw new OfflinePaymentError("ORDER_NOT_FOUND", `Pedido no encontrado / 未找到订单: ${orderLocalUuid}`);
    }

    if (!PAYABLE_STATUSES.includes(order.status)) {
      throw new OfflinePaymentError(
        "ORDER_NOT_PAYABLE",
        `El pedido está en estado '${order.status}' / 订单处于状态 '${order.status}', 预期为: ${PAYABLE_STATUSES.join(", ")}`
      );
    }

    return await localDb.transaction(async (db) => {
      const existingBills = await BillRepository.findByOrder(orderLocalUuid);
      let bill: LocalBill | null;

      if (billLocalUuid) {
        bill = existingBills.find(b => b.local_uuid === billLocalUuid) ?? null;
        if (!bill && !autoCreateBill) {
          throw new OfflinePaymentError("BILL_NOT_FOUND", `Cuenta no encontrada / 未找到账单: ${billLocalUuid}`);
        }
      } else if (existingBills.length > 1) {
        throw new OfflinePaymentError(
          "MULTIPLE_BILLS_FOUND",
          `El pedido tiene ${existingBills.length} cuentas. Especifique billLocalUuid / 订单有 ${existingBills.length} 个账单。请指定 billLocalUuid`
        );
      } else {
        bill = existingBills[0] ?? null;
      }

      if (!bill) {
        if (!autoCreateBill) {
          throw new OfflinePaymentError("NO_BILL_FOUND", `El pedido no tiene cuenta / 订单没有账单`);
        }

        const effectiveTipAmount = tipAmount > 0 ? tipAmount : order.tip_amount;
        let effectiveOrder = order;
        
        if (effectiveTipAmount !== order.tip_amount) {
          await OrderRepository.updateTipAmount(orderLocalUuid, effectiveTipAmount);
          const updatedOrder = await OrderRepository.findByLocalUuid(orderLocalUuid);
          if (!updatedOrder) {
            throw new OfflinePaymentError("ORDER_UPDATE_FAILED", `Error al actualizar propina / 更新小费失败: ${orderLocalUuid}`);
          }
          effectiveOrder = updatedOrder;
        }

        bill = await BillRepository.create({
          company_id: effectiveOrder.company_id,
          branch_id: effectiveOrder.branch_id,
          terminal_id: effectiveOrder.terminal_id || undefined,
          order_local_uuid: effectiveOrder.local_uuid,
          order_cloud_id: effectiveOrder.cloud_id || undefined,
          bill_number: `${effectiveOrder.order_number}-1`,
          subtotal: effectiveOrder.subtotal,
          discount_total: effectiveOrder.discount_total,
          tax_total: effectiveOrder.tax_total,
          tip_amount: effectiveOrder.tip_amount,
          grand_total: effectiveOrder.grand_total,
        });
      }

      // CA-04 FIX: Validar contra remaining_amount (que ahora incluye la propina en amount_due)
      if (amount > bill.remaining_amount) {
        throw new OfflinePaymentError(
          "AMOUNT_EXCEEDS_REMAINING",
          `El monto excede el saldo restante / 金额超过剩余余额: ${amount} > ${bill.remaining_amount}`
        );
      }

      if (bill.status === "paid") {
        throw new OfflinePaymentError("BILL_ALREADY_PAID", `La cuenta ya está pagada / 账单已支付: ${bill.local_uuid}`);
      }
      if (bill.status === "cancelled") {
        throw new OfflinePaymentError("BILL_CANCELLED", `La cuenta está cancelada / 账单已取消: ${bill.local_uuid}`);
      }

      const updatedBill = await BillRepository.registerPayment(bill.local_uuid, amount, db);

      const payment = await PaymentRepository.create({
        company_id: order.company_id,
        branch_id: order.branch_id,
        order_local_uuid: order.local_uuid,
        order_cloud_id: order.cloud_id || undefined,
        bill_local_uuid: bill?.local_uuid,
        payment_method: paymentMethod,
        amount,
        tip_amount: tipAmount ?? order.tip_amount,
        reference_code: referenceCode,
        notes,
      }, db);

      if (paymentMethod === "cash") {
        const ctx = getCashierContextSafe();
        if (ctx) {
          const session = await CashSessionRepository.findActive(order.company_id, order.branch_id, ctx.user_id, ctx.terminal_id);
          if (session) {
            await this.registerCashPayment(session.local_uuid, amount, payment.local_uuid, payment.cloud_id || undefined, notes);
            console.log(`[offlinePaymentService] ✅ Movimiento de caja registrado / 已记录现金变动: ${amount}`);
          } else {
            console.warn(`[offlinePaymentService] ⚠️ Sin sesión de caja abierta / 没有打开的收银会话`);
          }
        }
      }

      let orderStatusUpdated = false;
      let tableReleased = false;

      if (updatedBill.status === "paid") {
        await OrderRepository.updateStatus(order.local_uuid, "paid");
        orderStatusUpdated = true;

        if (order.table_id) {
          await this.releaseTableOffline(order.table_id);
          tableReleased = true;
        }
      }

      if (updatedBill.status === "paid") {
        try {
          const ctx = (await import("./authContext")).getCashierContextSafe();
          if (ctx) {
            const items = orderLocalUuid ? await OrderRepository.findItemsByOrderLocalUuid(orderLocalUuid) : [];
            const receiptData: ReceiptData = {
              billNumber: updatedBill.bill_number,
              items: items.map((item) => ({
                name: item.product_name,
                quantity: item.quantity,
                unitPrice: item.unit_price,
                subtotal: item.subtotal,
                notes: item.notes,
              })),
              subtotal: updatedBill.subtotal,
              taxTotal: updatedBill.tax_total,
              tipAmount: updatedBill.tip_amount,
              grandTotal: updatedBill.grand_total,
              paymentMethod: paymentMethod,
              paidAmount: updatedBill.paid_amount,
              remainingAmount: updatedBill.remaining_amount,
              cashierName: ctx.user_name || undefined,
              createdAt: new Date(),
            };

            const escposBase64 = ticketToBase64.receipt(receiptData);
            await LocalPrintJobRepository.create({
              job_type: "receipt",
              entity_type: "bill",
              entity_uuid: updatedBill.local_uuid,
              payload: receiptData,
              escpos_base64: escposBase64,
              printer_name: "receipt-printer",
              printer_type: "receipt",
              company_id: ctx.company_id,
              branch_id: ctx.branch_id,
              terminal_id: ctx.terminal_id,
              user_id: ctx.user_id,
              user_name: ctx.user_name || undefined,
              reference_number: `Cuenta #${updatedBill.bill_number} / 账单 #${updatedBill.bill_number}`,
            });
            console.log(`[offlinePaymentService] 🖨️ Ticket encolado / 票据已加入队列`);
          }
        } catch (printErr: any) {
          console.warn(`[offlinePaymentService] ⚠️ Error al encolar impresión / 打印队列错误:`, printErr?.message);
        }
      }

      return { payment, bill: updatedBill, orderPaid: updatedBill.status === "paid", orderStatusUpdated, tableReleased };
    });
  },

  async releaseTableOffline(tableUuid: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_tables SET status = 'available', current_order_uuid = NULL, last_updated = CURRENT_TIMESTAMP WHERE uuid = ?`,
      [tableUuid]
    );
    const tables = await localDb.select<{ company_id: string; branch_id: string }>("SELECT company_id, branch_id FROM local_tables WHERE uuid = ?", [tableUuid]);
    if (tables.length === 0) return;
    const { company_id, branch_id } = tables[0];
    await SyncQueueRepository.enqueue({
      company_id, branch_id, entity_type: "table_status", entity_local_uuid: tableUuid, action: "update", payload: { status: "available", current_order_uuid: null },
    });
  },

  async registerCashPayment(cashSessionLocalUuid: string, amount: number, referenceLocalUuid: string, referenceCloudId?: string, notes?: string): Promise<any> {
    return await CashMovementRepository.create(cashSessionLocalUuid, {
      type: "payment", amount, reason: "Pago en efectivo / 现金支付", notes, reference_type: "payment", reference_local_uuid: referenceLocalUuid, reference_cloud_id: referenceCloudId,
    });
  },

  async listOpenBillsByBranch(branchId: string): Promise<LocalBill[]> {
    return BillRepository.findOpenByBranch(branchId);
  },

  async getOrderPaymentStatus(orderLocalUuid: string): Promise<{ order: LocalOrder | null; bills: LocalBill[]; payments: LocalPayment[]; totalPaid: number; totalRemaining: number; isPaid: boolean; }> {
    const order = await OrderRepository.findByLocalUuid(orderLocalUuid);
    if (!order) return { order: null, bills: [], payments: [], totalPaid: 0, totalRemaining: 0, isPaid: false };

    const bills = await BillRepository.findByOrder(orderLocalUuid);
    const payments = await PaymentRepository.findByOrderLocalUuid(orderLocalUuid);

    const totalPaid = bills.reduce((sum, b) => sum + b.paid_amount, 0);
    const totalRemaining = bills.reduce((sum, b) => sum + b.remaining_amount, 0);

    return { order, bills, payments, totalPaid, totalRemaining, isPaid: totalRemaining <= 0 && bills.length > 0 };
  },
};
