import apiClient from "./apiClient";

export interface DashboardKPIs {
  today_sales: number;
  today_orders_count: number;
  average_ticket: number;
  today_tips: number;
}

export interface TopProduct {
  name: string;
  quantity: number;
  revenue: number;
}

export interface SalesByHour {
  hour: number;
  orders_count: number;
  total: number;
}

export interface PaymentMethodDistribution {
  method_code: string;
  count: number;
  total_amount: number;
}

export const reportsService = {
  async getDashboardKPIs(): Promise<DashboardKPIs> {
    const response = await apiClient.get<{ data: DashboardKPIs }>("/reports/dashboard");
    return response.data.data;
  },

  async getTopProducts(days = 7, limit = 10): Promise<TopProduct[]> {
    const response = await apiClient.get<{ data: TopProduct[] }>(
      `/reports/top-products?days=${days}&limit=${limit}`
    );
    return response.data.data;
  },

  async getSalesByHour(days = 7): Promise<SalesByHour[]> {
    const response = await apiClient.get<{ data: SalesByHour[] }>(
      `/reports/sales-by-hour?days=${days}`
    );
    return response.data.data;
  },

  async getPaymentMethods(days = 30): Promise<PaymentMethodDistribution[]> {
    const response = await apiClient.get<{ data: PaymentMethodDistribution[] }>(
      `/reports/payment-methods?days=${days}`
    );
    return response.data.data;
  },
};
