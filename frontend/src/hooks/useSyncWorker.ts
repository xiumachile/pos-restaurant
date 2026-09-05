import { useEffect, useRef } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

/**
 * Flags a nivel de módulo que sobreviven entre mount/unmount.
 * Garantizan que el worker solo se inicialice UNA VEZ por sesión.
 */
let workerInitialized = false;
let workerSessionKey: string | null = null;
let globalCheckInterval: number | null = null;

/**
 * Hook que inicia el worker de sincronización.
 *
 * ESTRATEGIA:
 * - Al montar, verifica si el worker ya está inicializado
 * - Si ya está inicializado → NO hace nada (ni polling)
 * - Si no → inicia polling hasta detectar autenticación
 * - Polling es global (nivel de módulo) para sobrevivir StrictMode
 * - Cleanup solo en logout real
 */
export function useSyncWorker() {
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;

    // ✅ Si ya está inicializado, no iniciar polling
    // Esto previene el bug del intervalo duplicado en StrictMode
    if (workerInitialized) {
      console.log("[SyncWorker] ⏭️  Worker ya inicializado, saltando");
      return;
    }

    console.log("[SyncWorker] 🔍 Hook montado, iniciando polling de auth");

    const checkAuth = async () => {
      if (!mountedRef.current && globalCheckInterval === null) return;

      const state = useAuthStore.getState();
      const isAuthenticated = state.isAuthenticated;
      const token = state.token || localStorage.getItem('auth_token');
      const userId = state.user?.id;

      console.log("[SyncWorker] 🔎 Checking auth:", {
        isAuthenticated,
        hasToken: !!token,
        userId: userId || "(none)",
        workerInitialized,
      });

      // Si no está autenticado (y no hay token), esperar
      if (!isAuthenticated && !token) {
        return;
      }

      // Si ya está inicializado, detener polling y salir
      if (workerInitialized) {
        console.log("[SyncWorker] ⏭️  Worker ya inicializado, deteniendo polling");
        stopPolling();
        return;
      }

      // ✅ Autenticado y no inicializado: proceder
      // Usar userId si existe, sino generar uno temporal basado en token
      const sessionId = userId || (token ? token.slice(0, 8) : "unknown");
      const sessionKey = `session-${sessionId}`;

      workerInitialized = true;
      workerSessionKey = sessionKey;
      console.log("[SyncWorker] 🚀 Usuario autenticado, iniciando worker (session:", sessionKey, ")");

      // Detener el polling inmediatamente
      stopPolling();

      // Ejecutar inicialización en background
      try {
        const syncState = useSyncStore.getState();
        const startWorker = syncState.startWorker;
        const refreshPendingCount = syncState.refreshPendingCount;
        const triggerFullSync = syncState.triggerFullSync;

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
        // Resetear flag para reintentar en el próximo mount
        workerInitialized = false;
        workerSessionKey = null;
      }
    };

    const stopPolling = () => {
      if (globalCheckInterval !== null) {
        window.clearInterval(globalCheckInterval);
        globalCheckInterval = null;
        console.log("[SyncWorker] 🛑 Polling detenido");
      }
    };

    // Verificación inmediata
    checkAuth();

    // Solo iniciar polling si no está ya corriendo
    if (globalCheckInterval === null) {
      globalCheckInterval = window.setInterval(checkAuth, 500);
      console.log("[SyncWorker] ⏱️  Polling iniciado (500ms)");
    }

    return () => {
      console.log("[SyncWorker] 🧹 Hook desmontado");
      mountedRef.current = false;

      // Detener worker si el usuario se desautenticó
      const currentAuth = useAuthStore.getState().isAuthenticated;
      const currentToken = useAuthStore.getState().token || localStorage.getItem('auth_token');

      if (!currentAuth && !currentToken && workerInitialized) {
        console.log("[SyncWorker] 👋 Usuario desautenticado, deteniendo worker");
        workerInitialized = false;
        workerSessionKey = null;
        useSyncStore.getState().stopWorker();
        stopPolling();
      }
      // NOTA: NO detenemos el polling si el usuario sigue autenticado
      // Esto permite que el polling sobreviva al unmount de StrictMode
    };
  }, []); // Array vacío: solo se ejecuta al montar
}
