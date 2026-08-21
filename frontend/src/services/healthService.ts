import { apiClient } from "./apiClient";

/**
 * Estado de salud del backend, tal como lo esperan
 * useOnlineStatus.ts y Header.tsx.
 */
export interface HealthStatus {
  online: boolean;
  timestamp: string;
  latency?: number;
}

export class HealthService {
  /**
   * Verifica que el backend esté operativo.
   * Endpoint correcto: GET /api/v1/sync/health
   */
  static async check(): Promise<HealthStatus> {
    const start = performance.now();
    try {
      await apiClient.get("/sync/health", { timeout: 5000 });
      return {
        online: true,
        timestamp: new Date().toISOString(),
        latency: Math.round(performance.now() - start),
      };
    } catch {
      return {
        online: false,
        timestamp: new Date().toISOString(),
      };
    }
  }
}

/**
 * Export singleton compatible con hooks existentes.
 */
export const healthService = {
  check: HealthService.check,
  checkHealth: HealthService.check,
  getStatus: HealthService.check,
};
