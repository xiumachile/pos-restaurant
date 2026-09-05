import apiClient from "./apiClient";
import { localPaymentsService } from "./localPaymentsService";
import { useSyncStore } from "@/store/useSyncStore";
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
    const response = await apiClient.get<SingleResponse<CashierDashboard>>(
      "/cashier/dashboard"
    );
    return (response.data as any).data;
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
    const response = await apiClient.post<SingleResponse<CashSession>>(
      "/cash-sessions/open",
      { opening_amount: openingAmount, notes }
    );
    return (response.data as any).data;
  },

  async closeSession(
    sessionUuid: string,
    closingAmount: number,
    notes?: string
  ): Promise<CashSession> {
    const response = await apiClient.post<SingleResponse<CashSession>>(
      `/cash-sessions/${sessionUuid}/close`,
      { closing_amount: closingAmount, notes }
    );
    return (response.data as any).data;
  },

  async listTablesWithBills(): Promise<TableBill[]> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    // En offline: reconstruir desde SQLite directamente (sin fetch al backend)
    if (isOffline) {
      try {
        return await localPaymentsService.listTablesWithBillsOffline();
      } catch (error) {
        console.warn("[paymentsService] Error leyendo cuentas desde SQLite:", error);
        return [];
      }
    }

    // En online: intentar backend, fallback a SQLite si falla
    try {
      const response = await apiClient.get<ListResponse<TableBill>>(
        "/cashier/tables-with-bills"
      );
      const data = response.data as any;
      return Array.isArray(data?.data) ? data.data : [];
    } catch (error: any) {
      console.warn("[paymentsService] Backend inaccesible, usando SQLite:", error?.message);
      try {
        return await localPaymentsService.listTablesWithBillsOffline();
      } catch (fallbackError) {
        console.error("[paymentsService] Error en fallback SQLite:", fallbackError);
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
