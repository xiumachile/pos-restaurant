import { describe, it, expect, beforeEach, vi } from "vitest";
import { useAuthStore } from "@/store/useAuthStore";
import type { User } from "@/types/auth";

describe("useAuthStore", () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      token: null,
      isAuthenticated: false,
    });
    vi.clearAllMocks();
  });

  const mockUser: User = {
    id: 1,
    uuid: "test-uuid",
    name: "Test User",
    email: "test@example.com",
    role: "admin",
    company_id: 1,
    branch_id: 1,
  };

  it("debería inicializar con estado no autenticado", () => {
    const state = useAuthStore.getState();
    expect(state.isAuthenticated).toBe(false);
    expect(state.user).toBeNull();
    expect(state.token).toBeNull();
  });

  it("debería autenticar usuario correctamente", () => {
    const token = "test-token";
    useAuthStore.getState().setAuth(mockUser, token);

    const state = useAuthStore.getState();
    expect(state.isAuthenticated).toBe(true);
    expect(state.user).toEqual(mockUser);
    expect(state.token).toBe(token);
    expect(localStorage.setItem).toHaveBeenCalledWith("access_token", token);
  });

  it("debería limpiar autenticación correctamente", () => {
    // Autenticar primero
    useAuthStore.getState().setAuth(mockUser, "token");
    
    // Limpiar
    useAuthStore.getState().clearAuth();

    const state = useAuthStore.getState();
    expect(state.isAuthenticated).toBe(false);
    expect(state.user).toBeNull();
    expect(state.token).toBeNull();
    expect(localStorage.removeItem).toHaveBeenCalledWith("access_token");
  });

  it("debería actualizar usuario sin cambiar token", () => {
    const token = "test-token";
    useAuthStore.getState().setAuth(mockUser, token);

    const updatedUser = { ...mockUser, name: "Updated Name" };
    useAuthStore.getState().updateUser(updatedUser);

    const state = useAuthStore.getState();
    expect(state.user?.name).toBe("Updated Name");
    expect(state.token).toBe(token); // Token no cambia
  });
});
