import apiClient from "./apiClient";
import type {
  PaymentMethod,
  CashierDashboard,
  CashSession,
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
  /**
   * Lista métodos de pago disponibles.
   */
  async listPaymentMethods(): Promise<PaymentMethod[]> {
    const response = await apiClient.get<ListResponse<PaymentMethod>>(
      "/payment-methods"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Dashboard del cajero (sesión + stats).
   */
  async getDashboard(): Promise<CashierDashboard> {
    const response = await apiClient.get<SingleResponse<CashierDashboard>>(
      "/cashier/dashboard"
    );
    return (response.data as any).data;
  },

  /**
   * Sesión de caja actual.
   */
  async getCurrentSession(): Promise<CashSession | null> {
    const response = await apiClient.get<SingleResponse<CashSession | null>>(
      "/cash-sessions/current"
    );
    return (response.data as any).data;
  },

  /**
   * Abrir caja.
   */
  async openSession(openingAmount: number, notes?: string): Promise<CashSession> {
    const response = await apiClient.post<SingleResponse<CashSession>>(
      "/cash-sessions/open",
      { opening_amount: openingAmount, notes }
    );
    return (response.data as any).data;
  },

  /**
   * Cerrar caja con arqueo.
   */
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

  /**
   * Lista mesas con pedidos served esperando cobro.
   * Cada mesa incluye todos sus pedidos y totales acumulados.
   */
  async listTablesWithBills(): Promise<TableBill[]> {
    const response = await apiClient.get<ListResponse<TableBill>>(
      "/cashier/tables-with-bills"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Cobra la cuenta completa de una mesa (todos sus pedidos served).
   * Crea pagos individuales por cada order, transiciona a paid y libera la mesa.
   */
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
};
