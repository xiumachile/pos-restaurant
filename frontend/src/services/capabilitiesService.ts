import apiClient from './apiClient';
import type { CapabilityResponse } from '@/types/capabilities';

/**
 * Servicio para gestionar capabilities de la empresa.
 * Mapea a endpoints:
 *   - GET  /api/v1/companies/{uuid}/capabilities
 *   - PUT  /api/v1/companies/{uuid}/capabilities
 */
export const capabilitiesService = {
  /**
   * Obtiene todas las capabilities de la empresa autenticada.
   */
  async getAll(companyUuid: string): Promise<CapabilityResponse[]> {
    const response = await apiClient.get<CapabilityResponse[]>(
      `/companies/${companyUuid}/capabilities`
    );
    return response.data;
  },

  /**
   * Actualiza una o más capabilities (bulk update).
   */
  async update(
    companyUuid: string,
    capabilities: Record<string, boolean>
  ): Promise<CapabilityResponse[]> {
    const response = await apiClient.put<CapabilityResponse[]>(
      `/companies/${companyUuid}/capabilities`,
      { capabilities }
    );
    return response.data;
  },

  /**
   * Toggle individual: cambia el estado de una capability.
   */
  async toggle(
    companyUuid: string,
    key: string,
    enabled: boolean
  ): Promise<CapabilityResponse[]> {
    return this.update(companyUuid, { [key]: enabled });
  },
};
