import { create } from "zustand";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

export type ConnectionStatus = "online" | "offline" | "syncing" | "error";

interface SyncState {
  // Estado de conexión
  status: ConnectionStatus;
  lastSyncAt: string | null;
  pendingCount: number;
  lastError: string | null;
  
  // Worker state
  isWorkerRunning: boolean;
  workerIntervalId: number | null;

  // Actions
  setStatus: (status: ConnectionStatus) => void;
  refreshPendingCount: () => Promise<void>;
  setLastError: (error: string | null) => void;
  setLastSyncAt: (timestamp: string) => void;
  startWorker: () => void;
  stopWorker: () => void;
}

export const useSyncStore = create<SyncState>((set, get) => ({
  status: "online",
  lastSyncAt: null,
  pendingCount: 0,
  lastError: null,
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

  startWorker: () => {
    const { isWorkerRunning } = get();
    if (isWorkerRunning) return;

    console.log("[SyncStore] Iniciando worker de sincronización (cada 15s)");
    
    const intervalId = window.setInterval(async () => {
      const { status } = get();
      if (status === "offline") return;
      
      await get().refreshPendingCount();
      // El procesamiento real del queue irá en el SyncEngine (Día 3)
    }, 15000);

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
    const { setStatus } = useSyncStore.getState();
    setStatus(navigator.onLine ? "online" : "offline");
  };

  window.addEventListener("online", updateConnectivity);
  window.addEventListener("offline", updateConnectivity);
  updateConnectivity();
}
