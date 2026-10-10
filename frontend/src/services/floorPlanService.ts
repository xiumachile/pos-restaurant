import apiClient from '@/services/apiClient';
import type {
  FloorPlan,
  FloorPlanWithObjects,
  CreateFloorPlanPayload,
  UpdateFloorPlanPayload,
  FloorPlanObject,
} from '@/types/floor-plan/floorPlan.types';

/**
 * Mesa operativa del módulo Tables (entidad real, no gráfica)
 */
export interface OperationalTable {
  uuid: string;
  table_number: string;
  capacity: number;
  status: 'available' | 'occupied' | 'reserved' | 'maintenance';
  area_code?: string;
  area_name_translations?: Record<string, string>;
  current_order_id?: number | null;
}

/**
 * Servicio API para gestión de planos de mesa
 */
export const floorPlanService = {
  /**
   * Listar todos los planos de la sucursal
   */
  async listFloorPlans(): Promise<FloorPlan[]> {
    const response = await apiClient.get('/floor-plans');
    return response.data?.data ?? response.data ?? [];
  },

  /**
   * Obtener un plano completo con sus objetos
   */
  async getFloorPlan(uuid: string): Promise<FloorPlanWithObjects> {
    const response = await apiClient.get(`/floor-plans/${uuid}`);
    return response.data?.data ?? response.data;
  },

  /**
   * Crear un nuevo plano
   */
  async createFloorPlan(payload: CreateFloorPlanPayload): Promise<FloorPlan> {
    const response = await apiClient.post('/floor-plans', payload);
    return response.data?.data ?? response.data;
  },

  /**
   * Actualizar un plano existente
   */
  async updateFloorPlan(uuid: string, payload: UpdateFloorPlanPayload): Promise<FloorPlan> {
    const response = await apiClient.put(`/floor-plans/${uuid}`, payload);
    return response.data?.data ?? response.data;
  },

  /**
   * Publicar un plano (cambiar estado a published)
   */
  async publishFloorPlan(uuid: string): Promise<FloorPlan> {
    const response = await apiClient.post(`/floor-plans/${uuid}/publish`);
    return response.data?.data ?? response.data;
  },

  /**
   * Eliminar un plano
   */
  async deleteFloorPlan(uuid: string): Promise<void> {
    await apiClient.delete(`/floor-plans/${uuid}`);
  },

  /**
   * Actualizar todos los objetos de un plano (bulk update)
   */
  async updateFloorPlanObjects(
    floorPlanUuid: string,
    objects: FloorPlanObject[]
  ): Promise<FloorPlanObject[]> {
    const response = await apiClient.put(`/floor-plans/${floorPlanUuid}/objects`, {
      objects,
    });
    return response.data?.data ?? response.data;
  },

  /**
   * Agregar un objeto al plano
   */
  async addFloorPlanObject(
    floorPlanUuid: string,
    object: Partial<FloorPlanObject>
  ): Promise<FloorPlanObject> {
    const response = await apiClient.post(`/floor-plans/${floorPlanUuid}/objects`, object);
    return response.data?.data ?? response.data;
  },

  /**
   * Actualizar un objeto específico
   */
  async updateFloorPlanObject(
    floorPlanUuid: string,
    objectUuid: string,
    object: Partial<FloorPlanObject>
  ): Promise<FloorPlanObject> {
    const response = await apiClient.put(
      `/floor-plans/${floorPlanUuid}/objects/${objectUuid}`,
      object
    );
    return response.data?.data ?? response.data;
  },

  /**
   * Eliminar un objeto del plano
   */
  async deleteFloorPlanObject(floorPlanUuid: string, objectUuid: string): Promise<void> {
    await apiClient.delete(`/floor-plans/${floorPlanUuid}/objects/${objectUuid}`);
  },

  /**
   * Obtiene las mesas operativas del módulo Tables (Sección 8.2)
   * Estas son las mesas REALES con pedidos, no los objetos gráficos
   * 
   * NOTA: El endpoint /tables devuelve áreas agrupadas con mesas anidadas:
   * [{area_code, area_name, tables: [...]}, ...]
   * Esta función aplana la estructura para devolver solo las mesas.
   */
  async getOperationalTables(): Promise<OperationalTable[]> {
    try {
      const response = await apiClient.get('/tables');
      const data = response.data?.data ?? response.data ?? [];
      
      // Si es un array vacío, retornar vacío
      if (!Array.isArray(data) || data.length === 0) {
        return [];
      }
      
      // Verificar si es estructura agrupada (tiene 'area_code' y 'tables')
      const isGrouped = data[0]?.area_code !== undefined && Array.isArray(data[0]?.tables);
      
      if (isGrouped) {
        // Aplanar: extraer todas las mesas de todas las áreas
        const allTables: OperationalTable[] = [];
        
        for (const area of data) {
          if (area.tables && Array.isArray(area.tables)) {
            for (const table of area.tables) {
              // Validar que la mesa tenga uuid
              if (table.uuid) {
                allTables.push({
                  uuid: table.uuid,
                  table_number: table.table_number,
                  capacity: table.capacity,
                  status: table.status,
                  area_code: area.area_code,
                  area_name_translations: { es: area.area_name },
                  current_order_id: table.current_order_id ?? null,
                });
              } else {
                console.warn('[floorPlanService] Mesa sin uuid encontrada:', table);
              }
            }
          }
        }
        
        console.log('[floorPlanService] Mesas operativas cargadas:', allTables.length);
        return allTables;
      } else {
        // Estructura ya es plana (fallback)
        console.log('[floorPlanService] Estructura de mesas ya es plana');
        return data;
      }
    } catch (error) {
      console.error('[floorPlanService] Error al cargar mesas operativas:', error);
      throw error;
    }
  },
};
