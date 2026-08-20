import { create } from "zustand";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

export type ConnectionStatus = "online" | "offline" | "syncing" | "error";

interface SyncState {
  // Estado de conexión
  status: ConnectionStatus;
  lastSyncAt: string | null;
  pendingCount: number;
  lastError: string | null;

  // Estadísticas del último batch
  lastBatchStats: {
    processed: number;
    success: number;
    failed: number;
  } | null;

  // Worker state
  isWorkerRunning: boolean;
  workerIntervalId: number | null;

  // Actions
  setStatus: (status: ConnectionStatus) => void;
  refreshPendingCount: () => Promise<void>;
  setLastError: (error: string | null) => void;
  setLastSyncAt: (timestamp: string) => void;
  triggerSync: () => Promise<void>;
  triggerFullSync: () => Promise<void>;
  startWorker: (intervalMs?: number) => void;
  stopWorker: () => void;
}

export const useSyncStore = create<SyncState>((set, get) => ({
  status: "online",
  lastSyncAt: null,
  pendingCount: 0,
  lastError: null,
  lastBatchStats: null,
  isWorkerRunning: false,
  workerIntervalId: null,

  setStatus: (status) => set({ status }),

  setLastError: (error) => set({ lastError: error }),

  setLastSyncAt: (timestamp) => set({ lastSyncAt: timestamp }),

  refreshPendingCount: async () => {
    try {
      const count = await SyncQueueRepository.countPending();
      set({ pendingCount: count });
    } catch (error) {
      console.error("[SyncStore] Error refrescando contador:", error);
    }
  },

  /**
   * Dispara un ciclo de sincronización manual (solo push).
   */
  triggerSync: async () => {
    const { status } = get();
    if (status === "offline") {
      console.log("[SyncStore] Offline, saltando sync");
      return;
    }

    try {
      const { syncEngine } = await import("../services/sync/SyncEngine");
      const stats = await syncEngine.processBatch();
      set({ lastBatchStats: stats });
    } catch (error: any) {
      console.error("[SyncStore] Error en triggerSync:", error);
      set({ lastError: error?.message || "Error en sincronización" });
    }
  },

  /**
   * Dispara sincronización completa: push + pull.
   */
  triggerFullSync: async () => {
    const { status } = get();
    if (status === "offline") {
      console.log("[SyncStore] Offline, saltando full sync");
      return;
    }

    try {
      const { syncEngine } = await import("../services/sync/SyncEngine");
      await syncEngine.triggerFullSync();
    } catch (error: any) {
      console.error("[SyncStore] Error en triggerFullSync:", error);
      set({ lastError: error?.message || "Error en sincronización completa" });
    }
  },

  startWorker: (intervalMs = 15000) => {
    const { isWorkerRunning } = get();
    if (isWorkerRunning) return;

    console.log(`[SyncStore] Iniciando worker (cada ${intervalMs / 1000}s)`);

    const intervalId = window.setInterval(async () => {
      const { status } = get();
      if (status === "offline") return;
      await get().triggerSync();
    }, intervalMs);

    set({ isWorkerRunning: true, workerIntervalId: intervalId });
  },

  stopWorker: () => {
    const { workerIntervalId } = get();
    if (workerIntervalId !== null) {
      window.clearInterval(workerIntervalId);
      set({ isWorkerRunning: false, workerIntervalId: null });
      console.log("[SyncStore] Worker detenido");
    }
  },
}));

// Monitor de conectividad del navegador
if (typeof window !== "undefined") {
  const updateConnectivity = () => {
    const { setStatus, triggerSync } = useSyncStore.getState();
    const isOnline = navigator.onLine;
    setStatus(isOnline ? "online" : "offline");

    // Al recuperar conexión, disparar sync inmediato
    if (isOnline) {
      console.log("[SyncStore] Conectividad restaurada, disparando sync");
      triggerSync();
    }
  };

  window.addEventListener("online", updateConnectivity);
  window.addEventListener("offline", updateConnectivity);
  updateConnectivity();
}
