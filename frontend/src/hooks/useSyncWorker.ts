import { useEffect } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

/**
 * Flag a nivel de módulo que sobrevive entre mount/unmount de StrictMode.
 * Garantiza que el worker solo se inicialice UNA VEZ por sesión.
 */
let workerInitialized = false;
let workerSessionKey: string | null = null;

/**
 * Hook que inicia el worker de sincronización.
 * Solo se activa cuando el usuario está autenticado.
 *
 * FLUJO:
 * 1. Al autenticarse: recupera operaciones syncing abandonadas (FASE 1)
 * 2. Dispara sincronización completa (push + pull)
 * 3. Arranca worker periódico (solo push de sync_queue)
 *
 * IDEMPOTENTE: React StrictMode no puede ejecutar el efecto dos veces
 * gracias al flag `workerInitialized` a nivel de módulo.
 */
export function useSyncWorker() {
  const startWorker = useSyncStore((state) => state.startWorker);
  const stopWorker = useSyncStore((state) => state.stopWorker);
  const refreshPendingCount = useSyncStore((state) => state.refreshPendingCount);
  const triggerFullSync = useSyncStore((state) => state.triggerFullSync);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const userId = useAuthStore((state) => state.user?.id);

  useEffect(() => {
    if (!isAuthenticated || !userId) {
      // Reset flag cuando el usuario cierra sesión
      if (workerInitialized) {
        console.log("[SyncWorker] 👋 Usuario desautenticado, reseteando flag");
        workerInitialized = false;
        workerSessionKey = null;
        stopWorker();
      }
      return;
    }

    // Clave única por sesión (evita re-inicialización si userId cambia)
    const sessionKey = `session-${userId}`;
    if (workerInitialized && workerSessionKey === sessionKey) {
      // Ya inicializado para esta sesión - saltar
      return;
    }

    workerInitialized = true;
    workerSessionKey = sessionKey;
    console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker (session:", sessionKey, ")");

    // FASE 1: Recuperación de syncing abandonados al iniciar
    (async () => {
      try {
        const recovered = await SyncQueueRepository.recoverAbandonedSyncing();
        if (recovered > 0) {
          console.log(`[SyncWorker] 🔄 Recuperadas ${recovered} operaciones syncing al iniciar`);
        }
        const forceReset = await SyncQueueRepository.forceResetAllSyncing();
        if (forceReset > 0) {
          console.log(`[SyncWorker] ⚠️  Reset forzoso de ${forceReset} syncing restantes`);
        }
      } catch (error) {
        console.error("[SyncWorker] Error en recovery:", error);
      }

      // Sync completa (push pendientes + pull catálogo)
      await triggerFullSync();

      // Worker periódico: solo push de sync_queue cada 15s
      startWorker();
      await refreshPendingCount();
    })();

    // NOTA: NO reseteamos workerInitialized en cleanup.
    // Esto garantiza idempotencia frente a React StrictMode.
    // El cleanup solo detiene el worker cuando el componente realmente se desmonta
    // (ej: logout), pero no en el desmontaje temporal de StrictMode.
    return () => {
      // Solo detener si el usuario realmente se desautenticó
      const currentAuth = useAuthStore.getState().isAuthenticated;
      if (!currentAuth) {
        stopWorker();
      }
    };
  }, [isAuthenticated, userId, startWorker, stopWorker, refreshPendingCount, triggerFullSync]);
}
