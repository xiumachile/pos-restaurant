import { localDb } from "../localDb";
import { validateLocalMoneyPayload, LocalMoneyContractViolation } from '../guards/localMoneyGuard';
import { v4 as uuidv4 } from "uuid";
import { SyncQueueRepository } from "./SyncQueueRepository";
import { EventStore } from "./EventStore";
import { getTerminalId } from "../../services/terminalIdentity";
import { getAuthContextSafe } from "../../services/authContext";
import { localDomainApi } from "../../services/localDbDomain";

export interface LocalPayment {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  order_local_uuid: string | null;
  order_cloud_id: string | null;
  // ADR-019: Link a bill específica (para split bill offline)
  // NULL para payments directos a order (sin bill/sin split)
  bill_local_uuid: string | null;
  payment_method?: "cash" | "card" | "transfer" | "gift_card"; // P0 Fix: Opcional si se usa payment_method_uuid
  payment_method_uuid: string | null;
  // ADR-011: Montos del pago
  amount: number;       // Total recibido (sale_amount + tip_amount)
  sale_amount: number;  // Porción que va a la venta
  tip_amount: number;   // Porción que es propina
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
  // ADR-019: UUID de bill específica (para split bill offline)
  bill_local_uuid?: string;
  payment_method?: "cash" | "card" | "transfer" | "gift_card"; // P0 Fix: Opcional si se usa payment_method_uuid
  payment_method_uuid?: string;
  amount: number;
  tip_amount?: number;
  reference_code?: string;
  notes?: string;
  // P0-02 FIX: Claves de idempotencia estables para reintentos
  local_uuid?: string;
  idempotency_key?: string;
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
  static async create(payload: CreatePaymentPayload, txDb?: any): Promise<LocalPayment> {
    // P2-003: Local Money Guard - Validar contrato monetario antes de insertar
    validateLocalMoneyPayload(payload as unknown as Record<string, unknown>, 'payment');
    
    // P0-02 FIX: Usar claves estables si se proporcionan (reintento), o generar nuevas (primer intento)
    const local_uuid = payload.local_uuid || uuidv4();
    const idempotency_key = payload.idempotency_key || uuidv4();

    // Resolver payment_method_uuid si no se pasó
    let paymentMethodUuid = payload.payment_method_uuid || null;
    if (!paymentMethodUuid && payload.payment_method) {
      paymentMethodUuid = await this.resolvePaymentMethodUuid(payload.payment_method);
    }

    // ADR-011: Calcular sale_amount (porción de venta)
    const tipAmount = payload.tip_amount || 0;
    const saleAmount = payload.amount - tipAmount;

    // ADR-019: Persistir bill_local_uuid si se especificó
    const billLocalUuid = payload.bill_local_uuid || null;

    // P0-02 / O-05 FIX: Usar comando de dominio seguro en entorno Tauri
    // AUD-03 FIX: Declarar 'payment' aquí para permitir retorno temprano en producción
    const payment: LocalPayment = {
      local_uuid,
      cloud_id: null,
      company_id: payload.company_id,
      branch_id: payload.branch_id,
      order_local_uuid: payload.order_local_uuid || null,
      order_cloud_id: payload.order_cloud_id || null,
      bill_local_uuid: billLocalUuid,
      payment_method: payload.payment_method,
      payment_method_uuid: paymentMethodUuid || "",
      amount: payload.amount,
      sale_amount: saleAmount,
      tip_amount: tipAmount,
      reference_code: payload.reference_code || null,
      status: 'pending',
      idempotency_key,
      sync_status: 'pending',
      created_at: new Date().toISOString(), // Aproximación, la BD usa CURRENT_TIMESTAMP
      notes: payload.notes || null,
    } as LocalPayment;

    const isTest = process.env.NODE_ENV === 'test' || typeof window === 'undefined';
    if (!isTest && !txDb) {
      try {
        // AUD-03 FIX: Rechazo explícito de operaciones interactivas sin operador autenticado.
        const operatorId = getAuthContextSafe()?.user_id;
        if (!operatorId) {
          throw new Error("AUD-03: Usuario autenticado requerido para registrar un pago interactivo.");
        }

        // P0 Fix: Alinear contrato con Rust. 
        // Rust espera: total_amount = amount (venta) + tip_amount
        // saleAmount ya fue calculado antes como: payload.amount - tipAmount
        await localDomainApi.registerPayment({
          local_uuid,
          idempotency_key,
          order_local_uuid: payload.order_local_uuid || "",
          payment_method_uuid: paymentMethodUuid || "", // P0 Fix: Rust requiere UUID, no nombre
          amount: saleAmount, // P0 Fix: Monto de la venta, no el total con propina
          tip_amount: tipAmount,
          total_amount: payload.amount, // P0 Fix: Total pagado (venta + propina)
          reference_code: payload.reference_code || null,
          notes: payload.notes || null,
          terminal_id: getTerminalId(),
          user_id: operatorId, // Fallback solo para procesos automáticos no interactivos
        });
        console.log("[PaymentRepository] 📤 Pago registrado atómicamente (Rust):", local_uuid);
        return payment;
      } catch (error) {
        console.error("[PaymentRepository] ❌ Error registrando pago en Rust:", error);
        throw error;
      }
    } else {
      // Fallback para tests o transacciones externas (txDb)
      const dbToUse = txDb || localDb;
      await (txDb ? (txDb as any) : localDb).execute(
        `INSERT INTO local_payments (
          local_uuid, company_id, branch_id, order_local_uuid, order_cloud_id,
          bill_local_uuid, payment_method, amount, sale_amount, tip_amount,
          reference_code, status, idempotency_key, sync_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending', CURRENT_TIMESTAMP)`,
        [
          local_uuid,
          payload.company_id,
          payload.branch_id,
          payload.order_local_uuid || null,
          payload.order_cloud_id || null,
          billLocalUuid,
          payload.payment_method_uuid || payload.payment_method || 'unknown', // P0 Fix: Preferir UUID
          payload.amount,
          saleAmount,
          tipAmount,
          payload.reference_code || null,
          idempotency_key,
        ]
      );
    }

    // Construir el objeto payment en memoria (más seguro y rápido dentro de transacción)

    // P1 Fix: El encolado en producción lo maneja Rust atómicamente (INSERT OR IGNORE).
    // En pruebas o transacciones externas (txDb), TypeScript debe encolar porque se salta Rust.
    if (isTest || txDb) {
      await SyncQueueRepository.enqueue({
        company_id: payment.company_id,
        branch_id: payment.branch_id,
        entity_type: "payment",
        entity_local_uuid: payment.local_uuid,
        action: "create",
        payload: {
          local_uuid: payment.local_uuid,
          order_local_uuid: payment.order_local_uuid,
          bill_local_uuid: payment.bill_local_uuid,
          payment_method_uuid: payment.payment_method_uuid,
          amount: payment.amount,
          sale_amount: payment.sale_amount,
          tip_amount: payment.tip_amount,
          total_amount: payment.amount,
          idempotency_key: payment.idempotency_key,
          reference_code: payment.reference_code,
          notes: payload.notes || null,
        },
      }, txDb); // P1-03 FIX: Propagar contexto transaccional
    }

    // AUD-03 FIX: Registrar evento CREATE_PAYMENT dentro de la misma transacción.
    const operatorId = getAuthContextSafe()?.user_id;
    if (!operatorId) {
      throw new Error("AUD-03: Usuario autenticado requerido para registrar un pago interactivo.");
    }
    await EventStore.record(
      {
        company_id: payload.company_id,
        branch_id: payload.branch_id,
        terminal_id: getTerminalId(),
        user_id: operatorId,
        entity_type: "payment",
        entity_uuid: local_uuid,
        event_type: "CREATE_PAYMENT",
        payload: {
          amount: payment.amount,
          payment_method: payment.payment_method,
          payment_method_uuid: paymentMethodUuid || "",
          tip_amount: payment.tip_amount,
          reference_code: payment.reference_code,
          notes: payload.notes || null,
          order_local_uuid: payload.order_local_uuid || null,
          order_cloud_id: payload.order_cloud_id || null,
        },
      },
      txDb // AUD-03 FIX: Propagar contexto transaccional para atomicidad
    );

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
   * Busca múltiples payments por local_uuid (batch query).
   * Útil para clasificar movimientos de caja por método de pago.
   */
  static async findByLocalUuids(localUuids: string[]): Promise<LocalPayment[]> {
    if (localUuids.length === 0) return [];
    
    const placeholders = localUuids.map(() => '?').join(',');
    const results = await localDb.select<LocalPayment>(
      `SELECT * FROM local_payments WHERE local_uuid IN (${placeholders})`,
      localUuids
    );
    return results;
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

  /**
   * Ajusta un pago existente (corrección de monto, método, etc.).
   * 
   * IMPORTANTE (Event Sourcing):
   * NO hace UPDATE en local_payments.
   * En su lugar, registra un evento ADJUST_PAYMENT con los cambios.
   * 
   * Para obtener el estado actual del pago:
   * EventStore.getEntityState("payment", paymentUuid)
   * 
   * @param localUuid UUID del pago a ajustar
   * @param adjustments Objeto con campos a ajustar (amount, payment_method, etc.)
   * @param reason Razón del ajuste
   */
  static async adjust(
    localUuid: string,
    adjustments: {
      amount?: number;
      payment_method?: "cash" | "card" | "transfer" | "gift_card";
      payment_method_uuid?: string;
      tip_amount?: number;
      reference_code?: string;
      notes?: string;
  // P0-02 FIX: Claves de idempotencia estables para reintentos
  local_uuid?: string;
  idempotency_key?: string;
    },
    reason: string
  ): Promise<void> {
    const payment = await this.findByLocalUuid(localUuid);
    if (!payment) {
      throw new Error(`Payment ${localUuid} not found`);
    }

    // AUD-03 FIX: Rechazo explícito de ajustes sin operador autenticado.
    const operatorId = getAuthContextSafe()?.user_id;
    if (!operatorId) {
      throw new Error("AUD-03: Usuario autenticado requerido para ajustar un pago.");
    }

    // Registrar evento ADJUST_PAYMENT (append-only)
    await EventStore.record({
      company_id: payment.company_id,
      branch_id: payment.branch_id,
      terminal_id: getTerminalId(),
      user_id: operatorId,
      entity_type: "payment",
      entity_uuid: localUuid,
      event_type: "ADJUST_PAYMENT",
      payload: {
        ...adjustments,
        reason,
        original_payment_uuid: localUuid,
      },
      notes: reason,
    });

    console.log(`[PaymentRepository] 📝 Ajuste registrado para payment ${localUuid}: ${reason}`);
  }
}
