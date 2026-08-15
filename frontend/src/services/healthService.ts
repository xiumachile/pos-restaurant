import apiClient from "./apiClient";

export interface HealthStatus {
  online: boolean;
  latency?: number;
  timestamp: string;
}

export const healthService = {
  /**
   * Verifica si el backend está disponible
   */
  async check(): Promise<HealthStatus> {
    const start = Date.now();
    try {
      await apiClient.get("/health", { timeout: 5000 });
      return {
        online: true,
        latency: Date.now() - start,
        timestamp: new Date().toISOString(),
      };
    } catch {
      return {
        online: false,
        timestamp: new Date().toISOString(),
      };
    }
  },
};
