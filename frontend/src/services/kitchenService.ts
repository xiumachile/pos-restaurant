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

export const kitchenService = {
  /**
   * Obtiene la cola de cocina agrupada por zona.
   */
  async getQueue(): Promise<KitchenZone[]> {
    const response = await apiClient.get<KitchenQueueResponse>("/kitchen/queue");
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Obtiene estadísticas de la cocina.
   */
  async getStats(): Promise<KitchenStats> {
    const response = await apiClient.get<KitchenStatsResponse>("/kitchen/stats");
    return (response.data as any).data;
  },

  /**
   * Marca un pedido como "En preparación" (confirmed → preparing).
   */
  async prepare(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/prepare`
    );
    return (response.data as any).data;
  },

  /**
   * Marca un pedido como "Listo" (preparing → ready).
   */
  async ready(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/ready`
    );
    return (response.data as any).data;
  },

  /**
   * Marca un pedido como "Servido" (ready → served).
   */
  async serve(orderUuid: string): Promise<KitchenOrder> {
    const response = await apiClient.post<KitchenOrderResponse>(
      `/orders/${orderUuid}/serve`
    );
    return (response.data as any).data;
  },
};
