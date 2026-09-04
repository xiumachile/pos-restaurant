import apiClient from "./apiClient";
import type { TablesArea } from "@/types/tables";

interface ListTablesResponse {
  data: TablesArea[];
}

export const tablesService = {
  /**
   * Lista todas las mesas agrupadas por área (el backend ya las agrupa).
   * 
   * NOTA: Eliminamos el overlay optimista de SQLite porque causaba bugs
   * donde las mesas permanecían 'occupied' en la UI después de que el
   * backend las liberara tras el pago. El backend es la fuente de verdad
   * y maneja correctamente las transiciones de estado.
   */
  async list(): Promise<TablesArea[]> {
    const response = await apiClient.get<ListTablesResponse>("/tables");
    const data = response.data as any;
    const areas: TablesArea[] = Array.isArray(data?.data) ? data.data : [];
    
    
    // Retornar directamente los datos del backend sin aplicar overrides
    return areas;
  },

  /**
   * Cambia el estado de una mesa.
   */
  async updateStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.put(`/tables/${uuid}/status`, { status });
    return response.data;
  },
};
