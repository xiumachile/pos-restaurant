import apiClient from "./apiClient";
import type {
  FloorPlan,
  SaveFloorPlanPayload,
  DiningZone,
  CreateDiningZonePayload,
  UpdateDiningZonePayload,
} from "@/types/floorPlan";

/**
 * Cliente API para el Floor Plan (Fase 4.1)
 *
 * Endpoints:
 * - GET    /floor-plan              → getFloorPlan()
 * - PUT    /floor-plan              → saveFloorPlan()
 * - POST   /dining-zones            → createZone()
 * - PATCH  /dining-zones/{uuid}     → updateZone()
 * - DELETE /dining-zones/{uuid}     → deleteZone()
 */
export const floorPlanService = {
  /**
   * GET /api/v1/floor-plan
   * Carga el layout completo (zonas + mesas con posiciones)
   */
  async getFloorPlan(): Promise<FloorPlan> {
    const response = await apiClient.get<{ data: FloorPlan }>("/floor-plan");
    return response.data.data;
  },

  /**
   * PUT /api/v1/floor-plan
   * Guarda posiciones de todas las mesas (transacción atómica)
   */
  async saveFloorPlan(payload: SaveFloorPlanPayload): Promise<{ tables_updated: number }> {
    const response = await apiClient.put<{
      message: string;
      data: { tables_updated: number };
    }>("/floor-plan", payload);
    return response.data.data;
  },

  /**
   * POST /api/v1/dining-zones
   * Crea una nueva zona en el branch actual
   */
  async createZone(payload: CreateDiningZonePayload): Promise<DiningZone> {
    const response = await apiClient.post<{ data: DiningZone }>(
      "/dining-zones",
      payload
    );
    return response.data.data;
  },

  /**
   * PATCH /api/v1/dining-zones/{uuid}
   * Actualiza una zona existente (solo campos modificables)
   */
  async updateZone(uuid: string, payload: UpdateDiningZonePayload): Promise<DiningZone> {
    const response = await apiClient.patch<{ data: DiningZone }>(
      `/dining-zones/${uuid}`,
      payload
    );
    return response.data.data;
  },

  /**
   * DELETE /api/v1/dining-zones/{uuid}
   * Elimina una zona (solo si no tiene mesas asignadas)
   */
  async deleteZone(uuid: string): Promise<void> {
    await apiClient.delete(`/dining-zones/${uuid}`);
  },
};
