import apiClient from "./apiClient";
import type { CashReport, SessionHistoryItem } from "@/types/cashier";

interface SingleResponse<T> {
  data: T;
}

export const cashierService = {
  /**
   * Genera reporte X (parcial) de la sesión abierta.
   */
  async getXReport(): Promise<CashReport> {
    const response = await apiClient.get<SingleResponse<CashReport>>(
      "/cashier/reports/x-report"
    );
    return (response.data as any).data;
  },

  /**
   * Genera reporte Z (final) de una sesión cerrada.
   */
  async getZReport(sessionUuid: string): Promise<CashReport> {
    const response = await apiClient.get<SingleResponse<CashReport>>(
      `/cashier/reports/z-report/${sessionUuid}`
    );
    return (response.data as any).data;
  },

  /**
   * Historial de sesiones cerradas.
   */
  async getSessionsHistory(limit: number = 50): Promise<SessionHistoryItem[]> {
    const response = await apiClient.get<SingleResponse<SessionHistoryItem[]>>(
      `/cashier/sessions/history?limit=${limit}`
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },
};
