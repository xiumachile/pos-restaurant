import apiClient from "./apiClient";
import type { Bill, SplitPayload, PayBillPayload, PayBillResponse } from "@/types/bills";

interface ListResponse<T> {
  data: T[];
}

interface SingleResponse<T> {
  data: T;
}

export const billsService = {
  /**
   * Genera sub-cuentas (bills) para un order según el tipo de split.
   */
  async splitOrder(orderUuid: string, payload: SplitPayload): Promise<Bill[]> {
    const response = await apiClient.post<ListResponse<Bill>>(
      `/orders/${orderUuid}/split`,
      payload
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Lista bills de un order.
   */
  async listBills(orderUuid: string): Promise<Bill[]> {
    const response = await apiClient.get<ListResponse<Bill>>(
      `/orders/${orderUuid}/bills`
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Cobra una bill específica.
   */
  async payBill(
    billUuid: string,
    payload: PayBillPayload
  ): Promise<PayBillResponse> {
    const response = await apiClient.post<SingleResponse<PayBillResponse>>(
      `/cashier/bills/${billUuid}/pay`,
      payload
    );
    return (response.data as any).data;
  },
};
