import apiClient from "./apiClient";
import type {
  PaymentMethod,
  CashierDashboard,
  CashSession,
  ServedOrder,
  CreatePaymentPayload,
} from "@/types/payments";

interface ListResponse<T> {
  data: T[];
}

interface SingleResponse<T> {
  data: T;
}

export const paymentsService = {
  /**
   * Lista métodos de pago disponibles para la sucursal.
   */
  async listPaymentMethods(): Promise<PaymentMethod[]> {
    const response = await apiClient.get<ListResponse<PaymentMethod>>(
      "/payment-methods"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Obtiene el dashboard del cajero (sesión activa + stats).
   */
  async getDashboard(): Promise<CashierDashboard> {
    const response = await apiClient.get<SingleResponse<CashierDashboard>>(
      "/cashier/dashboard"
    );
    return (response.data as any).data;
  },

  /**
   * Obtiene la sesión de caja actual (si está abierta).
   */
  async getCurrentSession(): Promise<CashSession | null> {
    const response = await apiClient.get<SingleResponse<CashSession | null>>(
      "/cash-sessions/current"
    );
    return (response.data as any).data;
  },

  /**
   * Abre una nueva sesión de caja.
   */
  async openSession(openingAmount: number, notes?: string): Promise<CashSession> {
    const response = await apiClient.post<SingleResponse<CashSession>>(
      "/cash-sessions/open",
      { opening_amount: openingAmount, notes }
    );
    return (response.data as any).data;
  },

  /**
   * Cierra la sesión de caja con arqueo.
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
   * Registra un pago para un pedido.
   */
  async createPayment(payload: CreatePaymentPayload): Promise<any> {
    const response = await apiClient.post("/billing/payments", payload);
    return (response.data as any).data;
  },

  /**
   * Lista pedidos servidos listos para cobrar.
   * Usa el endpoint de órdenes filtrado por status=served.
   */
  async listServedOrders(): Promise<ServedOrder[]> {
    const response = await apiClient.get<ListResponse<ServedOrder>>("/orders", {
      params: { status: "served" },
    });
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Transiciona un pedido de served → paid.
   */
  async markAsPaid(orderUuid: string): Promise<any> {
    const response = await apiClient.post(`/orders/${orderUuid}/pay`);
    return (response.data as any).data;
  },
};
