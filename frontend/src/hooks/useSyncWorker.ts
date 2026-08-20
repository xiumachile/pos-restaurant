import { useEffect } from "react";
import { useSyncStore } from "../store/useSyncStore";

/**
 * Hook que inicia el worker de sincronización al cargar la app.
 * Debe usarse en el componente raíz o layout principal.
 */
export function useSyncWorker() {
  const startWorker = useSyncStore((state) => state.startWorker);
  const stopWorker = useSyncStore((state) => state.stopWorker);
  const refreshPendingCount = useSyncStore((state) => state.refreshPendingCount);

  useEffect(() => {
    // Iniciar worker al montar
    startWorker();
    // Refrescar contador inicial
    refreshPendingCount();

    // Detener worker al desmontar
    return () => {
      stopWorker();
    };
  }, [startWorker, stopWorker, refreshPendingCount]);
}
