import { create } from 'zustand';
import { SyncQueueRepository } from '../db/repositories/SyncQueueRepository';

export type SyncStatus = 'online' | 'offline' | 'syncing' | 'error';

export type SyncPhase = 
  | 'idle'
  | 'push-processing'
  | 'push-completing'
  | 'pull-downloading'
  | 'pull-applying'
  | 'completed';

export interface SyncProgress {
  phase: SyncPhase;
  message: string;
  current: number;
  total: number;
  percentage: number;
}

interface SyncState {
  status: SyncStatus;
  lastSyncAt: string | null;
  pendingCount: number;
  lastError: string | null;
  progress: SyncProgress | null;
  isWorkerRunning: boolean;
  workerIntervalId: number | null;

  setStatus: (status: SyncStatus) => void;
  setLastError: (error: string | null) => void;
  setLastSyncAt: (timestamp: string) => void;
  setProgress: (progress: SyncProgress | null) => void;
  updateProgress: (updates: Partial<SyncProgress>) => void;
  refreshPendingCount: () => Promise<void>;
  triggerSync: () => Promise<void>;
  triggerFullSync: () => Promise<void>;
  startWorker: (intervalMs?: number) => void;
  stopWorker: () => void;
}

export const useSyncStore = create<SyncState>((set, get) => ({
  status: 'online',
  lastSyncAt: null,
  pendingCount: 0,
  lastError: null,
  progress: null,
  isWorkerRunning: false,
  workerIntervalId: null,

  setStatus: (status) => set({ status }),
  
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
      console.error('[SyncStore] Error counting pending:', error);
    }
  },

  triggerSync: async () => {
    const { syncEngine } = await import('../services/sync/SyncEngine');
    await syncEngine.processBatch();
    await get().refreshPendingCount();
  },

  triggerFullSync: async () => {
    const { syncEngine } = await import('../services/sync/SyncEngine');
    await syncEngine.triggerFullSync();
    await get().refreshPendingCount();
  },

  startWorker: (intervalMs = 15000) => {
    const state = get();
    if (state.isWorkerRunning) return;

    const intervalId = window.setInterval(async () => {
      const { status } = get();
      if (status === 'offline') return;
      
      await get().triggerSync();
    }, intervalMs);

    set({ isWorkerRunning: true, workerIntervalId: intervalId });
  },

  stopWorker: () => {
    const state = get();
    if (state.workerIntervalId !== null) {
      window.clearInterval(state.workerIntervalId);
      set({ isWorkerRunning: false, workerIntervalId: null });
    }
  },
}));
