import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import type { User } from "@/types/auth";

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  setAuth: (user: User, token: string) => void;
  clearAuth: () => void;
  updateUser: (user: User) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isAuthenticated: false,

      setAuth: (user, token) => {
        localStorage.setItem("access_token", token);
        set({ user, token, isAuthenticated: true });
      },

      clearAuth: () => {
        localStorage.removeItem("access_token");
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
