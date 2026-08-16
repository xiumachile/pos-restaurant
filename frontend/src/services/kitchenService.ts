import apiClient from "./apiClient";
import type { KitchenZone, KitchenStats, KitchenOrder } from "@/types/kitchen";

interface KitchenQueueResponse {
  data: KitchenZone[];
}

interface KitchenStatsResponse {
  data: KitchenStats;
}

interface KitchenOrderResponse {
  data: KitchenOrder;
}

export interface TableHistoryResponse {
  table: {
    uuid: string;
    table_number: string;
    area_code: string;
    capacity: number;
  };
  orders: KitchenOrder[];
  summary: {
    total_orders: number;
    total_items: number;
    total_amount: number;
    first_order_at: string | null;
    last_order_at: string | null;
  };
}

export interface TableTodaySummary {
  uuid: string;
  table_number: string;
  area_code: string;
  capacity: number;
  orders_count: number;
  total_items: number;
  total_amount: number;
  last_order_status: string | null;
  first_order_at: string | null;
  last_order_at: string | null;
}

export const kitchenService = {
  async getQueue(): Promise<KitchenZone[]> {
    const response = await apiClient.get<KitchenQueueResponse>("/kitchen/queue");
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  async getStats(): Promise<KitchenStats> {
    const response = await apiClient.get<KitchenStatsResponse>("/kitchen/stats");
    return (response.data as any).data;
  },

  async getTableHistory(tableUuid: string): Promise<TableHistoryResponse> {
    const response = await apiClient.get<{ data: TableHistoryResponse }>(
      `/kitchen/table-history/${tableUuid}`
    );
    return (response.data as any).data;
  },

  async getTablesToday(): Promise<TableTodaySummary[]> {
    const response = await apiClient.get<{ data: TableTodaySummary[] }>(
      "/kitchen/tables-today"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  async prepare(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/prepare`
    );
    return (response.data as any).data;
  },

  async ready(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/ready`
    );
    return (response.data as any).data;
  },

  async serve(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/serve`
    );
    return (response.data as any).data;
  },
};
