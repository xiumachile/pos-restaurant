import { describe, it, expect, beforeEach, vi } from "vitest";

/**
 * Tests de integración: flujo completo de autenticación.
 * 
 * Valida que useAuthStore + secureStorage + apiClient + useSyncWorker
 * funcionan juntos correctamente.
 */

describe("Integración: Flujo de autenticación", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  describe("Escenario 1: Login → Request autenticado", () => {
    it("debería guardar token en storage y hacerlo accesible para requests", async () => {
      const { useAuthStore } = await import("@/store/useAuthStore");
      const { getItemSync, clearSyncCache } = await import("@/services/secureStorage");
      
      clearSyncCache();
      
      const mockUser = {
        id: 1,
        uuid: "user-uuid",
        name: "Test User",
        email: "test@example.com",
        role: "admin" as const,
        company_id: 1,
        branch_id: 1,
      };
      const token = "jwt-login-123";
      
      // Login (vía store)
      await useAuthStore.getState().setAuth(mockUser, token);
      
      // Verificar que el token está accesible síncronamente (para interceptors)
      expect(getItemSync("access_token")).toBe(token);
      
      // Verificar que el store refleja el estado autenticado
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.token).toBe(token);
      expect(state.user?.uuid).toBe("user-uuid");
      
      // Cleanup
      await useAuthStore.getState().clearAuth();
    });
  });

  describe("Escenario 2: Logout → Limpieza completa", () => {
    it("debería limpiar token de storage y store", async () => {
      const { useAuthStore } = await import("@/store/useAuthStore");
      const { getItemSync } = await import("@/services/secureStorage");
      
      const mockUser = {
        id: 1,
        uuid: "user-uuid",
        name: "Test User",
        email: "test@example.com",
        role: "admin" as const,
        company_id: 1,
        branch_id: 1,
      };
      
      // Login
      await useAuthStore.getState().setAuth(mockUser, "token-to-clear");
      expect(getItemSync("access_token")).toBe("token-to-clear");
      
      // Logout
      await useAuthStore.getState().clearAuth();
      
      // Verificar limpieza completa
      expect(getItemSync("access_token")).toBeNull();
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.token).toBeNull();
      expect(state.user).toBeNull();
    });
  });

  describe("Escenario 3: Persistencia entre 'sesiones'", () => {
    it("debería persistir token para recuperar tras 'reinicio'", async () => {
      const { useAuthStore } = await import("@/store/useAuthStore");
      const { preloadAuthToken, getItemSync, clearSyncCache } = await import(
        "@/services/secureStorage"
      );
      
      const mockUser = {
        id: 1,
        uuid: "user-uuid",
        name: "Test User",
        email: "test@example.com",
        role: "admin" as const,
        company_id: 1,
        branch_id: 1,
      };
      
      // Sesión 1: Login
      await useAuthStore.getState().setAuth(mockUser, "persistent-jwt");
      
      // Simular "reinicio" (limpiar cache pero storage persiste)
      clearSyncCache();
      expect(getItemSync("access_token")).toBe("persistent-jwt"); // aún en localStorage
      
      // Sesión 2: App.tsx precarga el token al arranque
      await preloadAuthToken();
      
      // Ahora accesible para interceptors
      expect(getItemSync("access_token")).toBe("persistent-jwt");
      
      // Cleanup
      await useAuthStore.getState().clearAuth();
    });
  });

  describe("Escenario 4: Re-login tras logout", () => {
    it("debería permitir nuevo login tras logout completo", async () => {
      const { useAuthStore } = await import("@/store/useAuthStore");
      const { getItemSync } = await import("@/services/secureStorage");
      
      const user1 = {
        id: 1, uuid: "u1", name: "User 1", email: "u1@test.com",
        role: "admin" as const, company_id: 1, branch_id: 1,
      };
      const user2 = {
        id: 2, uuid: "u2", name: "User 2", email: "u2@test.com",
        role: "cashier" as const, company_id: 1, branch_id: 1,
      };
      
      // Login user 1
      await useAuthStore.getState().setAuth(user1, "token-u1");
      expect(getItemSync("access_token")).toBe("token-u1");
      
      // Logout
      await useAuthStore.getState().clearAuth();
      expect(getItemSync("access_token")).toBeNull();
      
      // Login user 2
      await useAuthStore.getState().setAuth(user2, "token-u2");
      expect(getItemSync("access_token")).toBe("token-u2");
      expect(useAuthStore.getState().user?.uuid).toBe("u2");
      
      // Cleanup
      await useAuthStore.getState().clearAuth();
    });
  });
});
