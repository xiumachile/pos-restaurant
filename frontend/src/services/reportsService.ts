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

export interface DateFilter {
  preset: "today" | "yesterday" | "last_7_days" | "last_30_days" | "this_month" | "last_month" | "this_year" | "custom";
  from_date?: string; // YYYY-MM-DD
  to_date?: string;   // YYYY-MM-DD
}

/**
 * Resuelve un DateFilter a parámetros concretos para la API.
 * Convierte presets a from_date/to_date concretos.
 */
export function resolveDateFilter(filter: DateFilter): { from_date: string; to_date: string } {
  const today = new Date();
  const formatDate = (d: Date): string => d.toISOString().split("T")[0];

  if (filter.preset === "custom" && filter.from_date && filter.to_date) {
    return { from_date: filter.from_date, to_date: filter.to_date };
  }

  let from: Date;
  let to: Date = today;

  switch (filter.preset) {
    case "today":
      from = new Date(today);
      break;
    case "yesterday":
      from = new Date(today);
      from.setDate(from.getDate() - 1);
      to = new Date(from);
      break;
    case "last_7_days":
      from = new Date(today);
      from.setDate(from.getDate() - 6);
      break;
    case "last_30_days":
      from = new Date(today);
      from.setDate(from.getDate() - 29);
      break;
    case "this_month":
      from = new Date(today.getFullYear(), today.getMonth(), 1);
      break;
    case "last_month":
      from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      to = new Date(today.getFullYear(), today.getMonth(), 0);
      break;
    case "this_year":
      from = new Date(today.getFullYear(), 0, 1);
      break;
    default: // last_7_days como fallback
      from = new Date(today);
      from.setDate(from.getDate() - 6);
  }

  return { from_date: formatDate(from), to_date: formatDate(to) };
}

export const reportsService = {
  async getDashboardKPIs(filter: DateFilter): Promise<DashboardKPIs> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: DashboardKPIs }>(
      `/reports/dashboard?from_date=${from_date}&to_date=${to_date}`
    );
    return response.data.data;
  },

  async getTopProducts(filter: DateFilter, limit = 10): Promise<TopProduct[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: TopProduct[] }>(
      `/reports/top-products?from_date=${from_date}&to_date=${to_date}&limit=${limit}`
    );
    return response.data.data;
  },

  async getSalesByHour(filter: DateFilter): Promise<SalesByHour[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: SalesByHour[] }>(
      `/reports/sales-by-hour?from_date=${from_date}&to_date=${to_date}`
    );
    return response.data.data;
  },

  async getPaymentMethods(filter: DateFilter): Promise<PaymentMethodDistribution[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: PaymentMethodDistribution[] }>(
      `/reports/payment-methods?from_date=${from_date}&to_date=${to_date}`
    );
    return response.data.data;
  },
};
