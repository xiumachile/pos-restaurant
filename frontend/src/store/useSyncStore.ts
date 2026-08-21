import { create } from "zustand";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";
import { syncEngine } from "../services/sync/SyncEngine";

export type ConnectionStatus = "online" | "offline" | "syncing" | "error";

export type SyncPhase =
  | "idle"
  | "push-processing"
  | "push-completing"
  | "pull-downloading"
  | "pull-applying"
  | "completed";

export interface SyncProgress {
  phase: SyncPhase;
  message: string;
  current: number;
  total: number;
  percentage: number;
}

interface SyncState {
  status: ConnectionStatus;
  lastSyncAt: string | null;
  pendingCount: number;
  lastError: string | null;
  lastBatchStats: {
    processed: number;
    success: number;
    failed: number;
  } | null;
  progress: SyncProgress | null;
  isWorkerRunning: boolean;
  workerIntervalId: number | null;

  setStatus: (status: ConnectionStatus) => void;
  refreshPendingCount: () => Promise<void>;
  setLastError: (error: string | null) => void;
  setLastSyncAt: (timestamp: string) => void;
  setProgress: (progress: SyncProgress | null) => void;
  updateProgress: (updates: Partial<SyncProgress>) => void;
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
  progress: null,
  isWorkerRunning: false,
  workerIntervalId: null,

  setStatus: (status) => {
    console.log(`[SyncStore] Estado cambiado: ${get().status} → ${status}`);
    set({ status });
  },

  setLastError: (error) => set({ lastError: error }),

  setLastSyncAt: (timestamp) => set({ lastSyncAt: timestamp }),

  setProgress: (progress) => set({ progress }),

  updateProgress: (updates) => {
    const current = get().progress;
    if (current) {
      set({ progress: { ...current, ...updates } });
    }
  },

  refreshPendingCount: async () => {
    try {
      const count = await SyncQueueRepository.countPending();
      set({ pendingCount: count });
    } catch (error) {
      console.error("[SyncStore] Error refrescando contador:", error);
    }
  },

  triggerSync: async () => {
    const { status } = get();
    if (status === "offline") {
      console.log("[SyncStore] Offline, saltando sync");
      return;
    }

    try {
      const stats = await syncEngine.processBatch();
      set({ lastBatchStats: stats });
    } catch (error: any) {
      console.error("[SyncStore] Error en triggerSync:", error);
      set({ lastError: error?.message || "Error en sincronización" });
    }
  },

  triggerFullSync: async () => {
    const { status } = get();
    if (status === "offline") {
      console.log("[SyncStore] Offline, saltando full sync");
      return;
    }

    try {
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
      if (status === "offline") {
        console.log("[SyncStore] Worker saltado: offline");
        return;
      }
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

// Monitor de conectividad del navegador con polling
if (typeof window !== "undefined") {
  let lastOnlineStatus = navigator.onLine;

  const updateConnectivity = () => {
    const isOnline = navigator.onLine;
    const { setStatus, triggerSync } = useSyncStore.getState();

    if (isOnline !== lastOnlineStatus) {
      console.log(`[SyncStore] Conectividad cambió: ${lastOnlineStatus} → ${isOnline}`);
      lastOnlineStatus = isOnline;

      if (isOnline) {
        setStatus("online");
        console.log("[SyncStore] 🌐 Conectividad restaurada, disparando sync");
        triggerSync();
      } else {
        setStatus("offline");
        console.log("[SyncStore] ✈️  Sin conexión, modo offline");
      }
    }
  };

  window.addEventListener("online", () => {
    console.log("[SyncStore] Evento 'online' disparado");
    updateConnectivity();
  });

  window.addEventListener("offline", () => {
    console.log("[SyncStore] Evento 'offline' disparado");
    updateConnectivity();
  });

  setInterval(updateConnectivity, 2000);

  updateConnectivity();
}
