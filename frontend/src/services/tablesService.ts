import apiClient from "./apiClient";
import { localTablesService } from "./localTablesService";
import { useSyncStore } from "@/store/useSyncStore";
import type { TablesArea, RestaurantTable, TableStatus } from "@/types/tables";

interface ListTablesResponse {
  data: TablesArea[];
}

/**
 * Aplica overlay optimista de SQLite sobre los datos del backend.
 * SOLO se aplica en modo offline para garantizar que el estado de las mesas
 * se refleje inmediatamente cuando el usuario crea un pedido sin red.
 * 
 * En modo online: el backend es la fuente de verdad (no se aplica overlay).
 * En modo offline: SQLite es la fuente de verdad (se aplica overlay).
 * 
 * @param areas Datos del backend (o cache de React Query)
 * @param overrides Mapa uuid -> status de localTablesService
 */
function applyOfflineOverlay(
  areas: TablesArea[],
  overrides: Map<string, string>
): TablesArea[] {
  if (overrides.size === 0) return areas;

  return areas.map(area => ({
    ...area,
    tables: area.tables.map((table: RestaurantTable) => {
      const overrideStatus = overrides.get(table.uuid);
      if (!overrideStatus) return table;
      
      return {
        ...table,
        status: overrideStatus as TableStatus,
        _isOfflineOverride: true,
      };
    }),
  }));
}

export const tablesService = {
  /**
   * Lista todas las mesas agrupadas por área.
   * 
   * ESTRATEGIA OFFLINE-FIRST:
   * - Online: retorna datos del backend (fuente de verdad)
   * - Offline: aplica overlay de SQLite sobre cache de React Query
   * 
   * El overlay se limpia automáticamente tras sync exitoso (PullEngine).
   */
  async list(): Promise<TablesArea[]> {
    const response = await apiClient.get<ListTablesResponse>("/tables");
    const data = response.data as any;
    const areas: TablesArea[] = Array.isArray(data?.data) ? data.data : [];

    // Solo aplicar overlay en modo offline
    const syncStatus = useSyncStore.getState().status;
    if (syncStatus === "offline") {
      const overrides = await localTablesService.getStatusOverrides();
      return applyOfflineOverlay(areas, overrides);
    }

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
