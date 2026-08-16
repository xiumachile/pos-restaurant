import apiClient from "./apiClient";
import type { TablesArea } from "@/types/tables";

interface ListTablesResponse {
  data: TablesArea[];
}

export const tablesService = {
  /**
   * Lista todas las mesas agrupadas por área (el backend ya las agrupa).
   */
  async list(): Promise<TablesArea[]> {
    const response = await apiClient.get<ListTablesResponse>("/tables");
    const data = response.data as any;
    // El backend retorna { data: [{area_code, area_name, tables: [...]}, ...] }
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Cambia el estado de una mesa.
   */
  async updateStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.put(`/tables/${uuid}/status`, { status });
    return response.data;
  },
};
