import apiClient from './apiClient';
import type { CapabilityResponse } from '@/types/capabilities';

/**
 * Servicio para gestionar capabilities de la empresa.
 * Mapea a endpoints:
 *   - GET  /api/v1/companies/{uuid}/capabilities
 *   - PUT  /api/v1/companies/{uuid}/capabilities
 * 
 * NOTA: El backend envuelve las respuestas en { data: [...] }
 * siguiendo el estándar JSON:API. Este service extrae el array
 * interno para que los consumidores reciban datos limpios.
 */
export const capabilitiesService = {
  /**
   * Obtiene todas las capabilities de la empresa autenticada.
   */
  async getAll(companyUuid: string): Promise<CapabilityResponse[]> {
    const response = await apiClient.get<{ data: CapabilityResponse[] }>(
      `/companies/${companyUuid}/capabilities`
    );
    // Extraer array interno (patrón consistente con catalogService)
    const payload = response.data as any;
    return Array.isArray(payload?.data) ? payload.data : [];
  },

  /**
   * Actualiza una o más capabilities (bulk update).
   */
  async update(
    companyUuid: string,
    capabilities: Record<string, boolean>
  ): Promise<CapabilityResponse[]> {
    const response = await apiClient.put<{ data: CapabilityResponse[] }>(
      `/companies/${companyUuid}/capabilities`,
      { capabilities }
    );
    const payload = response.data as any;
    return Array.isArray(payload?.data) ? payload.data : [];
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
