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
   */
  async getOperationalTables(): Promise<OperationalTable[]> {
    const response = await apiClient.get('/tables');
    return response.data?.data ?? response.data ?? [];
  },
};
