import apiClient from "./apiClient";
import type { RestaurantTable, TableArea } from "@/types/tables";

interface ListTablesResponse {
  data: RestaurantTable[];
  meta?: any;
}

interface ListAreasResponse {
  data: TableArea[];
}

export const tablesService = {
  /**
   * Lista todas las mesas de la sucursal actual
   */
  async list(): Promise<RestaurantTable[]> {
    const response = await apiClient.get<ListTablesResponse>("/tables");
    const data = response.data as any;
    return Array.isArray(data) ? data : data.data || [];
  },

  /**
   * Lista las áreas/zonas del restaurante
   */
  async listAreas(): Promise<TableArea[]> {
    const response = await apiClient.get<ListAreasResponse>("/tables/areas");
    const data = response.data as any;
    return Array.isArray(data) ? data : data.data || [];
  },

  /**
   * Obtiene detalle de una mesa
   */
  async show(uuid: string): Promise<RestaurantTable> {
    const response = await apiClient.get<{ data: RestaurantTable }>(
      `/tables/${uuid}`
    );
    return response.data.data;
  },

  /**
   * Cambia el estado de una mesa
   */
  async updateStatus(
    uuid: string,
    status: string
  ): Promise<RestaurantTable> {
    const response = await apiClient.put<{ data: RestaurantTable }>(
      `/tables/${uuid}/status`,
      { status }
    );
    return response.data.data;
  },
};
