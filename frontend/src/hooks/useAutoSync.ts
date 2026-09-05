import { useEffect } from "react";
import { useSyncStore } from "../store/useSyncStore";
import { useAuthStore } from "../store/useAuthStore";

/**
 * Hook que dispara sincronización periódica cada N minutos.
 *
 * NOTA: El triggerFullSync() inicial al autenticarse lo hace
 * useSyncWorker (montado en App.tsx). Este hook solo se encarga
 * del sync periódico.
 *
 * FLUJO:
 * - useSyncWorker (App.tsx): triggerFullSync al autenticarse
 * - useAutoSync (AppLayout.tsx): triggerFullSync cada N minutos
 * - useSyncStore (online event): triggerFullSync al recuperar red
 */
export function useAutoSync(options: {
  intervalMinutes?: number;
} = {}) {
  const { intervalMinutes = 5 } = options;

  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);

  // Sync periódico (cada N minutos)
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
    status: useSyncStore((s) => s.status),
    triggerFullSync,
  };
}
