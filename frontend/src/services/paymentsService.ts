import apiClient from "./apiClient";
import { CashSessionRepository } from "@/db/repositories/CashSessionRepository";
import { useAuthStore } from "@/store/useAuthStore";
import { localPaymentsService } from "./localPaymentsService";
import { useSyncStore } from "@/store/useSyncStore";
import { getTerminalId } from "./terminalIdentity";
import { getCashierContextSafe } from "./authContext";
import type {
  PaymentMethod,
  CashierDashboard,
  CashSession,
  SessionPaymentsData,
} from "@/types/payments";
import type {
  TableBill,
  ChargeTablePayload,
  ChargeTableResponse,
} from "@/types/tableBill";

interface ListResponse<T> {
  data: T[];
}

interface SingleResponse<T> {
  data: T;
}

export const paymentsService = {
  async listPaymentMethods(): Promise<PaymentMethod[]> {
    const response = await apiClient.get<ListResponse<PaymentMethod>>(
      "/payment-methods"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  async getDashboard(): Promise<CashierDashboard> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    if (isOffline) {
      console.log("[paymentsService] ✈️ getDashboard en modo offline: leyendo sesión local de SQLite");

      // FIX OFFLINE: Leer la sesión activa de local_cash_sessions
      // para que CashierPage reconozca que la caja está abierta
      let currentSession: CashSession | null = null;
      try {
        const ctx = getCashierContextSafe();
        if (!ctx) {
          console.warn("[paymentsService] ⚠️ Sin usuario autenticado, no se puede leer sesión offline");
        } else {
          const localSession = await CashSessionRepository.findActive(
            ctx.company_id, ctx.branch_id, ctx.user_id, ctx.terminal_id
          );
          if (localSession) {
            currentSession = CashSessionRepository.toCashSession(localSession);
            console.log(`[paymentsService] ✅ Sesión activa encontrada offline: ${localSession.local_uuid} (cloud: ${localSession.cloud_id || 'N/A'})`);
          } else {
            console.log("[paymentsService] ⚠️ No hay sesión activa en SQLite (caja cerrada)");
          }
        }
      } catch (sessionErr) {
        console.warn("[paymentsService] ⚠️ Error leyendo sesión offline:", sessionErr);
      }

      return {
        current_session: currentSession,
        total_sales_today: 0,
        total_orders_today: 0,
        total_tips_today: 0,
        pending_orders_count: 0,
        registers: [],
        statistics_today: {
          total_sales: 0,
          total_orders: 0,
          total_tips: 0,
          avg_ticket: 0,
        },
      } as unknown as CashierDashboard;
    }

    try {
      const response = await apiClient.get<SingleResponse<CashierDashboard>>(
        "/cashier/dashboard"
      );
      const dashboard = (response.data as any).data;

      // FIX OFFLINE: Si hay sesión activa en el backend, sincronizarla
      // a local_cash_sessions para que esté disponible offline
      if (dashboard.current_session) {
        try {
          const ctx = getCashierContextSafe();
          if (!ctx) {
            console.warn("[paymentsService] ⚠️ Sin usuario autenticado, no se puede sincronizar sesión");
          } else {
            const existing = await CashSessionRepository.findActive(
              ctx.company_id, ctx.branch_id, ctx.user_id, ctx.terminal_id
            );
            const backendSession = dashboard.current_session;

            // Si no hay sesión local activa o el cloud_id no coincide, crear/actualizar
            if (!existing || existing.cloud_id !== backendSession.uuid) {

              // Si hay una sesión local activa pero diferente, cerrarla (fue cerrada en otro terminal)
              if (existing && existing.cloud_id !== backendSession.uuid) {
                await CashSessionRepository.close(existing.local_uuid, existing.opening_amount);
                console.log(`[paymentsService] 🔒 Sesión local anterior cerrada (reemplazada por backend)`);
              }

              await CashSessionRepository.create({
                company_id: ctx.company_id,
                branch_id: ctx.branch_id,
                terminal_id: ctx.terminal_id,
                user_id: ctx.user_id,  // ✅ UUID correcto
                user_name: backendSession.user?.name || ctx.user_name,
                opening_amount: backendSession.opening_amount,
                opened_at: backendSession.opened_at,
                cloud_id: backendSession.uuid,
                sync_status: "synced",
              });
              console.log(`[paymentsService] ✅ Sesión del backend sincronizada a SQLite: ${backendSession.uuid}`);
            }
          }
        } catch (syncErr) {
          console.warn("[paymentsService] ⚠️ Error sincronizando sesión a SQLite:", syncErr);
        }
      }

      return dashboard;
    } catch (error: any) {
      console.warn("[paymentsService] ⚠️ getDashboard falló:", error?.message);
      return {
        current_session: null,
        total_sales_today: 0,
        total_orders_today: 0,
        total_tips_today: 0,
        pending_orders_count: 0,
        registers: [],
        statistics_today: {
          total_sales: 0,
          total_orders: 0,
          total_tips: 0,
          avg_ticket: 0,
        },
      } as unknown as CashierDashboard;
    }
  },

  /**
   * Pagos de la sesión abierta con detalle + resumen.
   */
  async getSessionPayments(): Promise<SessionPaymentsData> {
    const response = await apiClient.get<SingleResponse<SessionPaymentsData>>(
      "/cashier/session-payments"
    );
    return (response.data as any).data;
  },

  async getCurrentSession(): Promise<CashSession | null> {
    const response = await apiClient.get<SingleResponse<CashSession | null>>(
      "/cash-sessions/current"
    );
    return (response.data as any).data;
  },

  async openSession(openingAmount: number, notes?: string): Promise<CashSession> {
    console.log(`[paymentsService] 📤 Abriendo sesión de caja con monto: ${openingAmount}`);

    const response = await apiClient.post<SingleResponse<CashSession>>(
      "/cash-sessions/open",
      { opening_amount: openingAmount, notes }
    );
    const session = (response.data as any).data;

    // FIX OFFLINE: Guardar la sesión localmente para que esté disponible
    // cuando no haya conexión. Esto permite que CashierPage muestre
    // "caja abierta" aunque el backend esté inaccesible.
    try {
      const ctx = getCashierContextSafe();
      if (!ctx) {
        console.warn("[paymentsService] ⚠️ Sin usuario autenticado, no se puede guardar sesión localmente");
      } else {
        await CashSessionRepository.create({
          company_id: ctx.company_id,
          branch_id: ctx.branch_id,
          terminal_id: ctx.terminal_id,
          user_id: ctx.user_id,  // ✅ UUID correcto
          user_name: ctx.user_name,
          opening_amount: openingAmount,
          opened_at: session.opened_at,
          cloud_id: session.uuid,
          sync_status: "synced",
        });
        console.log(`[paymentsService] ✅ Sesión guardada localmente: ${session.uuid}`);
      }
    } catch (localErr) {
      // No crítico: si falla guardar localmente, la sesión sigue funcionando online
      console.warn("[paymentsService] ⚠️ No se pudo guardar sesión localmente:", localErr);
    }

    return session;
  },

  async closeSession(
    sessionUuid: string,
    closingAmount: number,
    notes?: string
  ): Promise<CashSession> {
    console.log(`[paymentsService] 🔒 Cerrando sesión: ${sessionUuid}`);

    const response = await apiClient.post<SingleResponse<CashSession>>(
      `/cash-sessions/${sessionUuid}/close`,
      { closing_amount: closingAmount, notes }
    );
    const session = (response.data as any).data;

    // FIX OFFLINE: Actualizar la sesión localmente para que
    // en modo offline se reconozca que la caja está cerrada
    try {
      // Buscar la sesión local por cloud_id
      const db = await import("../db/localDb").then(m => m.localDb);
      const rows = await db.select<{ local_uuid: string }>(
        "SELECT local_uuid FROM local_cash_sessions WHERE cloud_id = ?",
        [sessionUuid]
      );
      if (rows.length > 0) {
        await CashSessionRepository.close(rows[0].local_uuid, closingAmount);
        console.log(`[paymentsService] ✅ Sesión cerrada localmente: ${rows[0].local_uuid}`);
      }
    } catch (localErr) {
      console.warn("[paymentsService] ⚠️ No se pudo cerrar sesión localmente:", localErr);
    }

    return session;
  },

  async listTablesWithBills(): Promise<TableBill[]> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    console.log(`[paymentsService] 📋 listTablesWithBills() - syncStatus: ${syncStatus}, isOffline: ${isOffline}`);

    // En offline: reconstruir desde SQLite directamente (sin fetch al backend)
    if (isOffline) {
      try {
        const result = await localPaymentsService.listTablesWithBillsOffline();
        console.log(`[paymentsService] ✅ Offline: ${result.length} mesas con cuenta`);
        return result;
      } catch (error: any) {
        console.error("[paymentsService] ❌ Error leyendo cuentas desde SQLite:", error?.message || error);
        return [];
      }
    }

    // En online: intentar backend, fallback a SQLite si falla
    try {
      const response = await apiClient.get<ListResponse<TableBill>>(
        "/cashier/tables-with-bills"
      );
      const data = response.data as any;
      const result = Array.isArray(data?.data) ? data.data : [];
      console.log(`[paymentsService] ✅ Online: ${result.length} mesas con cuenta desde backend`);
      return result;
    } catch (error: any) {
      console.warn("[paymentsService] ⚠️ Backend inaccesible, usando SQLite:", error?.message);
      try {
        const result = await localPaymentsService.listTablesWithBillsOffline();
        console.log(`[paymentsService] ✅ Fallback SQLite: ${result.length} mesas con cuenta`);
        return result;
      } catch (fallbackError: any) {
        console.error("[paymentsService] ❌ Error en fallback SQLite:", fallbackError?.message || fallbackError);
        return [];
      }
    }
  },

  async chargeTable(
    tableUuid: string,
    payload: ChargeTablePayload
  ): Promise<ChargeTableResponse> {
    const response = await apiClient.post<SingleResponse<ChargeTableResponse>>(
      `/cashier/tables/${tableUuid}/charge`,
      payload
    );
    return (response.data as any).data;
  },

  /**
   * Prepara bills únicas para todos los órdenes servidos de una mesa.
   * Si ya existen bills, las retorna sin crear nuevas.
   */
  async prepareTableBills(tableUuid: string): Promise<{
    bills: any[];
    total_amount: number;
    orders_count: number;
  }> {
    const response = await apiClient.post<SingleResponse<any>>(
      `/cashier/tables/${tableUuid}/prepare-bills`,
      {}
    );
    return (response.data as any).data;
  },
};
