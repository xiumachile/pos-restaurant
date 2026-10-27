// src/services/floorPlanService.ts
import apiClient from "@/services/apiClient";

import type { 
  FloorPlanResponse, 
  SaveFloorPlanPayload, 
  CreateDiningZonePayload, 
  UpdateDiningZonePayload 
} from "@/types/floorPlan";

export const floorPlanService = {
  async getFloorPlan(): Promise<FloorPlanResponse> {
    const response = await apiClient.get<{ data: FloorPlanResponse }>('/floor-plan');
    return response.data.data;
  },

  async saveFloorPlan(payload: SaveFloorPlanPayload): Promise<FloorPlanResponse> {
    const response = await apiClient.put<{ data: FloorPlanResponse }>('/floor-plan', payload);
    return response.data.data;
  },

  async createZone(payload: CreateDiningZonePayload) {
    const response = await apiClient.post('/dining-zones', payload);
    return response.data.data;
  },

  async updateZone(uuid: string, payload: UpdateDiningZonePayload) {
    const response = await apiClient.patch(`/dining-zones/${uuid}`, payload);
    return response.data.data;
  },

  async deleteZone(uuid: string) {
    await apiClient.delete(`/dining-zones/${uuid}`);
  },
};
