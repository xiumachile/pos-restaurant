import { useEffect, useRef } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";

/**
 * Hook que dispara sincronización automática en momentos clave:
 * 1. Cuando el usuario se autentica (primera sync tras login)
 * 2. Al recuperar conectividad (ya está en useSyncStore)
 * 3. Cada N minutos (configurable)
 */
export function useAutoSync(options: {
  syncOnAuth?: boolean;
  intervalMinutes?: number;
} = {}) {
  const {
    syncOnAuth = true,
    intervalMinutes = 5,
  } = options;

  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);
  const status = useSyncStore((s) => s.status);
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const userId = useAuthStore((s) => s.user?.id);
  const hasInitialSyncedRef = useRef(false);

  // 🔑 Sync al autenticarse (primera vez por sesión)
  useEffect(() => {
    if (syncOnAuth && isAuthenticated && userId && !hasInitialSyncedRef.current && status === "online") {
      console.log("[AutoSync] 🚀 Primera sync tras login, userId:", userId);
      hasInitialSyncedRef.current = true;
      triggerFullSync();
    }
  }, [isAuthenticated, userId, status, syncOnAuth, triggerFullSync]);

  // Reset flag al hacer logout
  useEffect(() => {
    if (!isAuthenticated) {
      hasInitialSyncedRef.current = false;
    }
  }, [isAuthenticated]);

  // Sync periódico
  useEffect(() => {
    if (!intervalMinutes || intervalMinutes <= 0) return;

    const intervalId = window.setInterval(() => {
      const currentStatus = useSyncStore.getState().status;
      const currentAuth = useAuthStore.getState().isAuthenticated;
      if (currentStatus === "online" && currentAuth) {
        console.log(`[AutoSync] ⏰ Sync periódico (${intervalMinutes}min)`);
        triggerFullSync();
      }
    }, intervalMinutes * 60 * 1000);

    return () => window.clearInterval(intervalId);
  }, [intervalMinutes, triggerFullSync]);

  return {
    lastSyncAt: useSyncStore((s) => s.lastSyncAt),
    status,
    triggerFullSync,
  };
}
