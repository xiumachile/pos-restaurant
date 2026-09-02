import { create } from 'zustand';
import { CapabilityKey, CAPABILITY_META, type CapabilityInfo } from '@/types/capabilities';
import { capabilitiesService } from '@/services/capabilitiesService';

interface CapabilitiesState {
  capabilities: Record<CapabilityKey, CapabilityInfo>;
  isLoading: boolean;
  error: string | null;
  lastFetchedAt: number | null;

  // Actions
  fetchCapabilities: (companyUuid: string) => Promise<void>;
  toggleCapability: (companyUuid: string, key: CapabilityKey) => Promise<void>;
  isCapabilityEnabled: (key: CapabilityKey) => boolean;
  getCapability: (key: CapabilityKey) => CapabilityInfo | undefined;
  reset: () => void;
}

const initialState = {
  capabilities: {} as Record<CapabilityKey, CapabilityInfo>,
  isLoading: false,
  error: null as string | null,
  lastFetchedAt: null as number | null,
};

/**
 * Store global de capabilities de la empresa.
 * Uso: const isEnabled = useCapabilitiesStore(state => state.isCapabilityEnabled(CapabilityKey.CAN_ACCEPT_TIPS))
 */
export const useCapabilitiesStore = create<CapabilitiesState>((set, get) => ({
  ...initialState,

  fetchCapabilities: async (companyUuid: string) => {
    set({ isLoading: true, error: null });
    try {
      const data = await capabilitiesService.getAll(companyUuid);

      // Construir mapa de capabilities con metadata
      const capabilitiesMap = {} as Record<CapabilityKey, CapabilityInfo>;
      
      Object.values(CapabilityKey).forEach((key) => {
        const serverCap = data.find((c) => c.key === key);
        capabilitiesMap[key] = {
          ...CAPABILITY_META[key],
          is_enabled: serverCap?.is_enabled ?? false,
          settings: serverCap?.settings,
        };
      });

      set({
        capabilities: capabilitiesMap,
        isLoading: false,
        lastFetchedAt: Date.now(),
      });
    } catch (err) {
      console.error('Failed to fetch capabilities:', err);
      set({
        isLoading: false,
        error: err instanceof Error ? err.message : 'Error desconocido',
      });
    }
  },

  toggleCapability: async (companyUuid: string, key: CapabilityKey) => {
    const current = get().capabilities[key];
    if (!current) return;

    const newValue = !current.is_enabled;

    // Optimistic update
    set((state) => ({
      capabilities: {
        ...state.capabilities,
        [key]: { ...current, is_enabled: newValue },
      },
    }));

    try {
      const data = await capabilitiesService.toggle(companyUuid, key, newValue);
      const serverCap = data.find((c) => c.key === key);
      
      set((state) => ({
        capabilities: {
          ...state.capabilities,
          [key]: {
            ...current,
            is_enabled: serverCap?.is_enabled ?? newValue,
            settings: serverCap?.settings,
          },
        },
      }));
    } catch (err) {
      // Rollback
      set((state) => ({
        capabilities: {
          ...state.capabilities,
          [key]: current,
        },
        error: err instanceof Error ? err.message : 'Error al actualizar',
      }));
    }
  },

  isCapabilityEnabled: (key: CapabilityKey) => {
    const caps = get().capabilities;
    return caps[key]?.is_enabled ?? false;
  },

  getCapability: (key: CapabilityKey) => {
    return get().capabilities[key];
  },

  reset: () => set(initialState),
}));
