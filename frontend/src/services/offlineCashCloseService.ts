import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { CashMovementRepository } from "@/db/repositories/CashMovementRepository";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";
import { SyncQueueRepository } from "@/db/repositories/SyncQueueRepository";
import { ticketToBase64, type CashCopyData } from "@/services/printing/ticketFormatters";
import { getCashierContextSafe } from "@/services/authContext";
import { localDb } from "@/db/localDb";

export class OfflineCashCloseError extends Error {
  constructor(public code: string, message: string) {
    super(`${code}: ${message}`);
    this.name = "OfflineCashCloseError";
  }
}

export interface CloseSessionResult {
  sessionUuid: string;
  expectedAmount: number;
  actualAmount: number;
  difference: number;
  printJobUuid: string;
}

/**
 * offlineCashCloseService: Cierra sesión de caja 100% offline.
 * 
 * USO:
 *   const result = await offlineCashCloseService.closeSession({
 *     sessionUuid: "...",
 *     closingAmount: 150000,
 *     notes: "Cierre normal",
 *   });
 * 
 * FLUJO:
 * 1. Valida que la sesión existe y está abierta
 * 2. Calcula balance esperado (CashSessionRepository.getBalance)
 * 3. Obtiene movimientos de la sesión
 * 4. Marca sesión como 'closed' en SQLite
 * 5. Encola evento de sincronización
 * 6. Genera copia de caja con ticketToBase64.cashCopy()
 * 7. Encola LocalPrintJob con bytes ESC/POS
 * 8. OfflinePrintEngine imprime automáticamente
 * 
 * OFFLINE-FIRST:
 * - Todo se hace en SQLite (sin API/WebSocket/Internet)
 * - Sincronización diferida vía SyncQueue
 * - Impresión automática (no depende de red)
 */
export const offlineCashCloseService = {
  /**
   * Cierra sesión de caja offline y genera copia impresa.
   */
  async closeSession(params: {
    sessionUuid: string;
    closingAmount: number;
    notes?: string;
  }): Promise<CloseSessionResult> {
    const { sessionUuid, closingAmount, notes } = params;

    if (closingAmount < 0) {
      throw new OfflineCashCloseError(
        "INVALID_AMOUNT",
        `closingAmount must be non-negative, got ${closingAmount}`
      );
    }

    return localDb.transaction(async () => {
      // 1. Obtener sesión
      let session = await CashSessionRepository.findByCloudId(sessionUuid);
      if (!session) {
        session = await CashSessionRepository.findByLocalUuid(sessionUuid);
      }
      if (!session) {
        throw new OfflineCashCloseError(
          "SESSION_NOT_FOUND",
          `Session ${sessionUuid} not found`
        );
      }

      if (session.status === "closed") {
        throw new OfflineCashCloseError(
          "SESSION_ALREADY_CLOSED",
          `Session ${sessionUuid} is already closed`
        );
      }

      // 2. Calcular balance esperado
      const balance = await CashSessionRepository.getBalance(session.local_uuid);
      const expectedAmount = balance.current_balance;

      // 3. Calcular diferencia
      const difference = closingAmount - expectedAmount;

      // 4. Marcar sesión como cerrada
      // NOTA: expected_amount y difference no son columnas de la tabla,
      // se incluyen solo en el payload del print job y SyncQueue
      await localDb.execute(
        `UPDATE local_cash_sessions 
         SET status = 'closed',
             closed_at = datetime('now'),
             closing_amount = ?,
             sync_status = 'pending'
         WHERE local_uuid = ?`,
        [closingAmount, session.local_uuid]
      );

      console.log(`[offlineCashCloseService] 🔒 Sesión cerrada: ${session.local_uuid}`);
      console.log(`   Esperado: $${expectedAmount}`);
      console.log(`   Contado: $${closingAmount}`);
      console.log(`   Diferencia: $${difference}`);

      // 5. Obtener movimientos para la copia
      const movements = await CashMovementRepository.findBySession(session.local_uuid);

      // 6. Calcular ventas por método
      const salesByMethod = {
        cash: 0,
        card: 0,
        transfer: 0,
      };

      for (const mov of movements) {
        if (mov.reference_type === "payment" && mov.type === "payment") {
          // Inferir método de pago desde reference_uuid o metadata
          // Por ahora, asumimos que todos los sales son cash
          // TODO: mejorar esto cuando tengamos payment_method en movements
          salesByMethod.cash += mov.amount;
        }
      }

      const totalSales = salesByMethod.cash + salesByMethod.card + salesByMethod.transfer;

      // 7. Construir CashCopyData
      const cashCopyData: CashCopyData = {
        cashierName: session.user_name || "Cajero",
        sessionOpenedAt: new Date(session.opened_at),
        sessionClosedAt: new Date(),
        totalSales,
        cashSales: salesByMethod.cash,
        cardSales: salesByMethod.card,
        transferSales: salesByMethod.transfer,
        movements: movements
          .filter((m) => m.type === "withdrawal" || m.type === "deposit")
          .map((m) => ({
            type: m.type as "withdrawal" | "deposit",
            amount: m.amount,
            reason: m.reason || undefined,
            createdAt: new Date(m.created_at),
          })),
        expectedAmount,
        actualAmount: closingAmount,
        difference,
      };

      // 8. Generar bytes ESC/POS (100% offline)
      const escposBase64 = ticketToBase64.cashCopy(cashCopyData);

      // 9. Obtener contexto del cajero
      const ctx = getCashierContextSafe();
      if (!ctx) {
        throw new OfflineCashCloseError(
          "NO_CASHIER_CONTEXT",
          "No hay contexto de cajero (usuario no autenticado)"
        );
      }

      // 10. Encolar print job
      const printJobUuid = await LocalPrintJobRepository.create({
        job_type: "receipt",
        entity_type: "cash_session",
        entity_uuid: session.local_uuid,
        payload: cashCopyData,
        escpos_base64: escposBase64,
        printer_name: "receipt-printer",
        printer_type: "receipt",
        company_id: ctx.company_id,
        branch_id: ctx.branch_id,
        terminal_id: ctx.terminal_id,
        user_id: ctx.user_id,
        user_name: ctx.user_name || undefined,
        reference_number: `Cierre de caja`,
        idempotency_key: `cash-close-${session.local_uuid}-${Date.now()}`,
      });

      console.log(`[offlineCashCloseService] 🖨️  Copia de caja encolada (${escposBase64.length} bytes)`);

      // 11. Encolar evento de sincronización
      await SyncQueueRepository.enqueue({
        company_id: ctx.company_id,
        branch_id: ctx.branch_id,
        entity_type: "cash_session",
        entity_local_uuid: session.local_uuid,
        action: "update",  // close es un update del estado de la sesión
        payload: {
          session_uuid: session.local_uuid,
          closing_amount: closingAmount,
          expected_amount: expectedAmount,
          difference,
          notes: notes || null,
          closed_at: new Date().toISOString(),
        },
      });

      return {
        sessionUuid: session.local_uuid,
        expectedAmount,
        actualAmount: closingAmount,
        difference,
        printJobUuid,
      };
    });
  },
};
