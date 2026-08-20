import { useCallback } from "react";
import { useSyncStore } from "../store/useSyncStore";

/**
 * Hook que permite sincronizar antes de navegar a páginas críticas.
 * Útil para asegurar datos frescos antes de mostrar catálogo,
 * tomar pedidos o procesar pagos.
 *
 * Uso:
 * const navigateWithSync = useSyncBeforeNavigation();
 * navigateWithSync("/orders");
 */
export function useSyncBeforeNavigation() {
  const triggerSync = useSyncStore((s) => s.triggerSync);
  const status = useSyncStore((s) => s.status);
  const pendingCount = useSyncStore((s) => s.pendingCount);

  const navigateWithSync = useCallback(
    async (navigateFn: () => void, options: { forceSync?: boolean } = {}) => {
      const { forceSync = false } = options;

      // Solo sincronizar si hay eventos pendientes o se fuerza
      if ((pendingCount > 0 || forceSync) && status === "online") {
        console.log("[Navigation] Sincronizando antes de navegar...");
        await triggerSync();
      }

      navigateFn();
    },
    [triggerSync, status, pendingCount]
  );

  return { navigateWithSync };
}
