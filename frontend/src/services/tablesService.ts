import apiClient from "./apiClient";
import { localTablesService } from "./localTablesService";
import { useSyncStore } from "@/store/useSyncStore";
import type { TablesArea, RestaurantTable, TableStatus } from "@/types/tables";

interface ListTablesResponse {
  data: TablesArea[];
}

const CACHE_KEY = "pos_tables_cache_v1";
const CACHE_TTL_MS = 24 * 60 * 60 * 1000; // 24 horas

interface CacheEntry {
  data: TablesArea[];
  timestamp: number;
}

/**
 * Guarda las áreas en localStorage como caché de respaldo offline.
 */
function saveToCache(areas: TablesArea[]): void {
  try {
    const entry: CacheEntry = {
      data: areas,
      timestamp: Date.now(),
    };
    localStorage.setItem(CACHE_KEY, JSON.stringify(entry));
  } catch (error) {
    console.warn("[tablesService] Error guardando caché:", error);
  }
}

/**
 * Lee las áreas desde caché si existe y no ha expirado.
 */
function readFromCache(): TablesArea[] | null {
  try {
    const raw = localStorage.getItem(CACHE_KEY);
    if (!raw) return null;

    const entry: CacheEntry = JSON.parse(raw);
    const age = Date.now() - entry.timestamp;

    if (age > CACHE_TTL_MS) {
      localStorage.removeItem(CACHE_KEY);
      return null;
    }

    return entry.data;
  } catch (error) {
    console.warn("[tablesService] Error leyendo caché:", error);
    return null;
  }
}

/**
 * Aplica overlay optimista de SQLite sobre las áreas.
 * Reemplaza el status de mesas que tienen override en local_tables.
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
      } as RestaurantTable & { _isOfflineOverride: boolean };
    }),
  }));
}

/**
 * Reconstruye la estructura de áreas desde SQLite cuando no hay caché disponible.
 * Agrupa mesas por area_name (ya que local_tables no tiene area_code).
 */
async function rebuildFromSQLite(): Promise<TablesArea[]> {
  const allTables = await localTablesService.getAllTables();
  if (allTables.length === 0) return [];

  // Agrupar por area_name
  const areaMap = new Map<string, RestaurantTable[]>();
  for (const table of allTables) {
    const key = table.area_name || "Sin área";
    if (!areaMap.has(key)) {
      areaMap.set(key, []);
    }
    areaMap.get(key)!.push(table);
  }

  // Convertir a TablesArea[]
  return Array.from(areaMap.entries()).map(([areaName, tables]) => ({
    area_code: areaName.toLowerCase().replace(/\s+/g, "_"),
    area_name: areaName,
    tables,
  }));
}

export const tablesService = {
  /**
   * Lista todas las mesas agrupadas por área.
   *
   * ESTRATEGIA OFFLINE-FIRST:
   * 1. Intenta fetch del backend
   * 2. Si éxito: guarda en caché + retorna datos (con overlay si offline)
   * 3. Si falla (red): usa caché + overlay de SQLite
   * 4. Si no hay caché: reconstruye desde SQLite (fallback final)
   *
   * Esto garantiza que la vista de mesas funcione 100% offline.
   */
  async list(): Promise<TablesArea[]> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    try {
      // 1. Intentar fetch del backend
      const response = await apiClient.get<ListTablesResponse>("/tables");
      const data = response.data as any;
      const areas: TablesArea[] = Array.isArray(data?.data) ? data.data : [];

      // Guardar en caché para uso offline futuro
      saveToCache(areas);

      // En modo online, retornar datos del backend sin overlay
      if (!isOffline) {
        return areas;
      }

      // En modo offline pero con backend accesible (raro), aplicar overlay
      const overrides = await localTablesService.getStatusOverrides();
      return applyOfflineOverlay(areas, overrides);

    } catch (error) {
      // 2. Si falla (típicamente offline), usar caché + overlay
      console.warn("[tablesService] Backend inaccesible, usando caché local");

      const cached = readFromCache();
      const overrides = await localTablesService.getStatusOverrides();

      if (cached) {
        return applyOfflineOverlay(cached, overrides);
      }

      // 3. Sin caché: reconstruir desde SQLite (fallback final)
      console.warn("[tablesService] Sin caché, reconstruyendo desde SQLite");
      return rebuildFromSQLite();
    }
  },

  /**
   * Cambia el estado de una mesa.
   */
  async updateStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.put(`/tables/${uuid}/status`, { status });
    return response.data;
  },

  /**
   * Limpia el caché de mesas (útil para testing/debugging).
   */
  clearCache(): void {
    localStorage.removeItem(CACHE_KEY);
  },
};
