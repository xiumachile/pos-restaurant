import { describe, it, expect, beforeEach, vi } from "vitest";
import { useAuthStore } from "@/store/useAuthStore";
import {
  getAuthContext,
  getAuthContextSafe,
  validateContext,
} from "@/services/authContext";

describe("authContext", () => {
  const mockUser = {
    id: 1,
    uuid: "user-uuid-123",
    name: "Test User",
    email: "test@example.com",
    role: "cashier" as const,
    company_id: 42,
    branch_id: 7,
  };

  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      token: null,
      isAuthenticated: false,
    });
    vi.clearAllMocks();
  });

  describe("getAuthContext", () => {
    it("debería lanzar error si no hay usuario autenticado", () => {
      expect(() => getAuthContext()).toThrow(
        "No hay usuario autenticado"
      );
    });

    it("debería retornar contexto completo cuando hay usuario", async () => {
      await useAuthStore.getState().setAuth(mockUser, "token-123");

      const ctx = getAuthContext();

      expect(ctx.company_id).toBe("42");
      expect(ctx.branch_id).toBe("7");
      expect(ctx.user_id).toBe("user-uuid-123");
      expect(ctx.user_name).toBe("Test User");
      expect(ctx.user_role).toBe("cashier");
      expect(ctx.terminal_id).toBeTruthy(); // UUID generado por getTerminalId()

      await useAuthStore.getState().clearAuth();
    });

    it("debería convertir IDs numéricos a string (compatibilidad con payloads)", async () => {
      await useAuthStore.getState().setAuth(mockUser, "token");

      const ctx = getAuthContext();

      expect(typeof ctx.company_id).toBe("string");
      expect(typeof ctx.branch_id).toBe("string");
      expect(typeof ctx.user_id).toBe("string");

      await useAuthStore.getState().clearAuth();
    });

    it("debería lanzar error si usuario tiene company_id ausente", async () => {
      const incompleteUser = { ...mockUser, company_id: 0 };
      await useAuthStore.getState().setAuth(incompleteUser, "token");

      expect(() => getAuthContext()).toThrow("IDs críticos");

      await useAuthStore.getState().clearAuth();
    });
  });

  describe("getAuthContextSafe", () => {
    it("debería retornar null si no hay usuario autenticado", () => {
      expect(getAuthContextSafe()).toBeNull();
    });

    it("debería retornar contexto si hay usuario", async () => {
      await useAuthStore.getState().setAuth(mockUser, "token");

      const ctx = getAuthContextSafe();

      expect(ctx).not.toBeNull();
      expect(ctx?.user_id).toBe("user-uuid-123");

      await useAuthStore.getState().clearAuth();
    });
  });

  describe("validateContext", () => {
    beforeEach(async () => {
      await useAuthStore.getState().setAuth(mockUser, "token");
    });

    afterEach(async () => {
      await useAuthStore.getState().clearAuth();
    });

    it("debería validar contexto correcto", () => {
      expect(
        validateContext({
          company_id: "42",
          branch_id: "7",
          user_id: "user-uuid-123",
        })
      ).toBe(true);
    });

    it("debería rechazar company_id diferente", () => {
      expect(
        validateContext({
          company_id: "999", // Otra empresa
          branch_id: "7",
        })
      ).toBe(false);
    });

    it("debería rechazar branch_id diferente", () => {
      expect(
        validateContext({
          company_id: "42",
          branch_id: "999", // Otra sucursal
        })
      ).toBe(false);
    });

    it("debería rechazar user_id diferente", () => {
      expect(
        validateContext({
          company_id: "42",
          branch_id: "7",
          user_id: "other-user-uuid", // Otro usuario
        })
      ).toBe(false);
    });

    it("debería validar si el contexto parcial coincide", () => {
      expect(validateContext({ company_id: "42" })).toBe(true);
      expect(validateContext({ branch_id: "7" })).toBe(true);
      expect(validateContext({})).toBe(true);
    });

    it("debería retornar true si no hay usuario autenticado (modo permisivo)", async () => {
      // Sin usuario autenticado, validateContext es permisivo
      // para no romper tests legacy que no configuran auth.
      // En producción, SyncEngine SIEMPRE corre con usuario autenticado.
      await useAuthStore.getState().clearAuth();
      expect(validateContext({ company_id: "42" })).toBe(true);
    });
  });
});
