import { useState, useEffect } from "react";
import { healthService, type HealthStatus } from "@/services/healthService";

/**
 * Hook que monitorea el estado de conexión con el backend.
 * Hace polling cada 30 segundos.
 */
export function useOnlineStatus(pollingInterval: number = 30000) {
  const [status, setStatus] = useState<HealthStatus>({
    online: navigator.onLine,
    timestamp: new Date().toISOString(),
  });

  useEffect(() => {
    const checkHealth = async () => {
      const result = await healthService.check();
      setStatus(result);
    };

    // Verificar inmediatamente
    checkHealth();

    // Configurar polling
    const interval = setInterval(checkHealth, pollingInterval);

    // Listeners de conexión del navegador
    const handleOnline = () => checkHealth();
    const handleOffline = () =>
      setStatus({ online: false, timestamp: new Date().toISOString() });

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      clearInterval(interval);
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, [pollingInterval]);

  return status;
}
