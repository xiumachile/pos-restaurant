import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { User } from "@/types/auth";
import { 
  getItem, 
  setItem, 
  removeItem, 
  updateSyncCache, 
  clearSyncCache 
} from "@/services/secureStorage";

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  setAuth: (user: User, token: string) => Promise<void>;
  clearAuth: () => Promise<void>;
  updateUser: (user: User) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,

      setAuth: async (user, token) => {
        // Guardar en storage seguro (Tauri Store encriptado)
        await setItem("access_token", token);
        
        // Actualizar cache síncrona para interceptors
        updateSyncCache("access_token", token);
        
        set({ user, token, isAuthenticated: true });
      },

      clearAuth: async () => {
        await removeItem("access_token");
        clearSyncCache();
        set({ user: null, token: null, isAuthenticated: false });
      },

      updateUser: (user) => set({ user }),
    }),
    {
      name: "auth-storage",
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
);

// Auto-fetch capabilities cuando el usuario hace login
useAuthStore.subscribe((state, prevState) => {
  if (state.user?.company?.uuid && !prevState.user?.company?.uuid) {
    // Login exitoso → cargar capabilities
    import('./useCapabilitiesStore').then(({ useCapabilitiesStore }) => {
      useCapabilitiesStore.getState().fetchCapabilities(state.user!.company!.uuid);
    });
  } else if (!state.user && prevState.user) {
    // Logout → limpiar capabilities
    import('./useCapabilitiesStore').then(({ useCapabilitiesStore }) => {
      useCapabilitiesStore.getState().reset();
    });
  }
});
