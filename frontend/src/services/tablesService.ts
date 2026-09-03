import apiClient from "./apiClient";
import { localTablesService } from "./localTablesService";
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
    const areas: TablesArea[] = Array.isArray(data?.data) ? data.data : [];
    
    console.log('[tablesService] Backend retornó', areas.length, 'áreas');
    
    // Obtener status actualizado desde SQLite local (overlay optimista)
    const overrides = await localTablesService.getStatusOverrides();
    console.log('[tablesService] Overrides de SQLite:', overrides.size, 'mesas');
    
    // Aplicar overrides: el status local tiene prioridad sobre el cloud
    // Esto asegura que cambios recientes (ej: mesa ocupada tras crear pedido)
    // se reflejen inmediatamente sin esperar al próximo pull del backend
    return areas.map(area => ({
      ...area,
      tables: area.tables.map(table => ({
        ...table,
        status: (overrides.get(table.uuid) || table.status) as typeof table.status
      }))
    }));
  },

  /**
   * Cambia el estado de una mesa.
   */
  async updateStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.put(`/tables/${uuid}/status`, { status });
    return response.data;
  },
};
