import { useEffect, useRef } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

/**
 * Flag a nivel de módulo que sobrevive entre mount/unmount.
 * Garantiza que el worker solo se inicialice UNA VEZ por sesión.
 */
let workerInitialized = false;
let workerSessionKey: string | null = null;

/**
 * Hook que inicia el worker de sincronización.
 *
 * ESTRATEGIA ROBUSTA:
 * - Verifica periódicamente el estado de auth (polling cada 500ms)
 * - Cuando detecta autenticación real, inicializa el worker UNA sola vez
 * - El flag a nivel de módulo garantiza idempotencia frente a StrictMode
 * - No depende de re-renders del store (que pueden fallar por timing)
 */
export function useSyncWorker() {
  const checkIntervalRef = useRef<number | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;

    console.log("[SyncWorker] 🔍 Hook montado, iniciando polling de auth");

    // Polling del estado de autenticación (cada 500ms)
    const checkAuth = async () => {
      if (!mountedRef.current) return;

      const { isAuthenticated, user } = useAuthStore.getState();
      const userId = user?.id;

      console.log("[SyncWorker] 🔎 Checking auth:", {
        isAuthenticated,
        userId: userId || "(none)",
        workerInitialized,
      });

      // Si no está autenticado, esperar
      if (!isAuthenticated) {
        return;
      }

      // Si ya está inicializado para esta sesión, no hacer nada
      const sessionKey = `session-${userId || "unknown"}`;
      if (workerInitialized && workerSessionKey === sessionKey) {
        return;
      }

      // ✅ Autenticado y no inicializado: proceder
      workerInitialized = true;
      workerSessionKey = sessionKey;
      console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker (session:", sessionKey, ")");

      // Detener el polling (ya no necesitamos verificar)
      if (checkIntervalRef.current !== null) {
        window.clearInterval(checkIntervalRef.current);
        checkIntervalRef.current = null;
      }

      // Ejecutar inicialización en background
      try {
        const startWorker = useSyncStore.getState().startWorker;
        const refreshPendingCount = useSyncStore.getState().refreshPendingCount;
        const triggerFullSync = useSyncStore.getState().triggerFullSync;

        // FASE 1: Recuperación de syncing abandonados
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

        // Worker periódico: push cada 15s
        startWorker();
        await refreshPendingCount();

        console.log("[SyncWorker] ✅ Inicialización completa");
      } catch (error) {
        console.error("[SyncWorker] Error durante inicialización:", error);
        // Resetear flag para reintentar en el próximo check
        workerInitialized = false;
        workerSessionKey = null;
      }
    };

    // Verificación inmediata
    checkAuth();

    // Polling cada 500ms hasta que se inicialice
    checkIntervalRef.current = window.setInterval(checkAuth, 500);

    return () => {
      console.log("[SyncWorker] 🧹 Hook desmontado");
      mountedRef.current = false;
      if (checkIntervalRef.current !== null) {
        window.clearInterval(checkIntervalRef.current);
        checkIntervalRef.current = null;
      }

      // Detener worker si el usuario se desautenticó
      const currentAuth = useAuthStore.getState().isAuthenticated;
      if (!currentAuth && workerInitialized) {
        console.log("[SyncWorker] 👋 Usuario desautenticado, deteniendo worker");
        workerInitialized = false;
        workerSessionKey = null;
        useSyncStore.getState().stopWorker();
      }
    };
  }, []); // Array vacío: solo se ejecuta una vez al montar
}
