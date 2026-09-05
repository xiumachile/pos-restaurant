import { useEffect, useRef } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

/**
 * Hook que inicia el worker de sincronización.
 * Solo se activa cuando el usuario está autenticado.
 *
 * FLUJO:
 * 1. Al autenticarse: recupera operaciones syncing abandonadas (FASE 1)
 * 2. Dispara sincronización completa (push + pull)
 * 3. Arranca worker periódico (solo push de sync_queue)
 *
 * NOTA: Este hook solo debe montarse UNA VEZ en la aplicación.
 * Actualmente se monta en App.tsx. No debe existir en AppLayout.
 */
export function useSyncWorker() {
  const startWorker = useSyncStore((state) => state.startWorker);
  const stopWorker = useSyncStore((state) => state.stopWorker);
  const refreshPendingCount = useSyncStore((state) => state.refreshPendingCount);
  const triggerFullSync = useSyncStore((state) => state.triggerFullSync);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  // Flag para evitar ejecución doble (React StrictMode en dev)
  const initializedRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated) {
      console.log("[SyncWorker] ⏸️  Esperando autenticación...");
      return;
    }

    if (initializedRef.current) {
      console.log("[SyncWorker] ⏭️  Ya inicializado, saltando");
      return;
    }

    initializedRef.current = true;
    console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker");

    // FASE 1: Recuperación de syncing abandonados al iniciar
    // Esto corrige operaciones que quedaron pegadas por crash/corte de energía
    (async () => {
      try {
        const recovered = await SyncQueueRepository.recoverAbandonedSyncing();
        if (recovered > 0) {
          console.log(`[SyncWorker] 🔄 Recuperadas ${recovered} operaciones syncing al iniciar`);
        }
        // También recuperar cualquier syncing restante con force reset
        // (solo al primer inicio después de un posible crash)
        const forceReset = await SyncQueueRepository.forceResetAllSyncing();
        if (forceReset > 0) {
          console.log(`[SyncWorker] ⚠️  Reset forzoso de ${forceReset} syncing restantes`);
        }
      } catch (error) {
        console.error("[SyncWorker] Error en recovery:", error);
      }

      // Después de recovery: sync completa (push pendientes + pull catálogo)
      await triggerFullSync();

      // Worker periódico: solo push de sync_queue cada 15s
      startWorker();
      await refreshPendingCount();
    })();

    return () => {
      stopWorker();
      initializedRef.current = false;
    };
  }, [isAuthenticated, startWorker, stopWorker, refreshPendingCount, triggerFullSync]);
}
