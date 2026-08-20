import { useEffect, useRef } from "react";
import { useSyncStore } from "../store/useSyncStore";

/**
 * Hook que dispara sincronización automática en momentos clave:
 * 1. Al cargar la app (una sola vez, al inicio de sesión)
 * 2. Al recuperar conectividad (ya está en useSyncStore)
 * 3. Cada N minutos (opcional, configurable)
 *
 * Uso: colocar en AppLayout o componente raíz autenticado.
 */
export function useAutoSync(options: {
  syncOnMount?: boolean;
  intervalMinutes?: number;
} = {}) {
  const {
    syncOnMount = true,
    intervalMinutes = 5,
  } = options;

  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);
  const status = useSyncStore((s) => s.status);
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const hasSyncedOnce = useRef(false);

  // Sync al montar (una vez por sesión)
  useEffect(() => {
    if (syncOnMount && !hasSyncedOnce.current && status === "online") {
      console.log("[AutoSync] Sincronización inicial al cargar app");
      triggerFullSync();
      hasSyncedOnce.current = true;
    }
  }, [syncOnMount, status, triggerFullSync]);

  // Sync periódico cada N minutos (mientras esté online)
  useEffect(() => {
    if (!intervalMinutes || intervalMinutes <= 0) return;

    const intervalId = window.setInterval(() => {
      const currentStatus = useSyncStore.getState().status;
      if (currentStatus === "online") {
        console.log(`[AutoSync] Sincronización periódica (${intervalMinutes}min)`);
        triggerFullSync();
      }
    }, intervalMinutes * 60 * 1000);

    return () => window.clearInterval(intervalId);
  }, [intervalMinutes, triggerFullSync]);

  return {
    lastSyncAt,
    status,
    triggerFullSync,
  };
}
