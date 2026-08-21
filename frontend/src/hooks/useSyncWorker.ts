import { useEffect } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";

/**
 * Hook que inicia el worker de sincronización.
 * Solo se activa cuando el usuario está autenticado.
 */
export function useSyncWorker() {
  const startWorker = useSyncStore((state) => state.startWorker);
  const stopWorker = useSyncStore((state) => state.stopWorker);
  const refreshPendingCount = useSyncStore((state) => state.refreshPendingCount);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  useEffect(() => {
    if (!isAuthenticated) {
      // No iniciar worker sin autenticación
      console.log("[SyncWorker] ⏸️  Esperando autenticación...");
      return;
    }

    console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker");
    startWorker();
    refreshPendingCount();

    return () => {
      stopWorker();
    };
  }, [isAuthenticated, startWorker, stopWorker, refreshPendingCount]);
}
