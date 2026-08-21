import { useEffect } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";

/**
 * Hook que inicia el worker de sincronización.
 * Solo se activa cuando el usuario está autenticado.
 *
 * Al autenticarse: dispara una sincronización completa (push + pull)
 * para traer el catálogo/mesas/métodos de pago, y luego arranca
 * el worker periódico que solo hace push de la sync_queue.
 */
export function useSyncWorker() {
  const startWorker = useSyncStore((state) => state.startWorker);
  const stopWorker = useSyncStore((state) => state.stopWorker);
  const refreshPendingCount = useSyncStore((state) => state.refreshPendingCount);
  const triggerFullSync = useSyncStore((state) => state.triggerFullSync);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  useEffect(() => {
    if (!isAuthenticated) {
      console.log("[SyncWorker] ⏸️  Esperando autenticación...");
      return;
    }

    console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker");

    // Sync inicial completa (push pendientes + pull catálogo/mesas)
    triggerFullSync();

    // Worker periódico: solo push de sync_queue cada 15s
    startWorker();
    refreshPendingCount();

    return () => {
      stopWorker();
    };
  }, [isAuthenticated, startWorker, stopWorker, refreshPendingCount, triggerFullSync]);
}
