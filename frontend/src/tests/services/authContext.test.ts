import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
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

describe("mergeAuthContext", () => {
  const mockUser = {
    id: 1,
    uuid: "user-merge-test",
    name: "Merge Test User",
    email: "merge@test.com",
    role: "cashier" as const,
    company_id: 42,
    branch_id: 7,
  };

  beforeEach(async () => {
    await useAuthStore.getState().setAuth(mockUser, "token");
  });

  afterEach(async () => {
    await useAuthStore.getState().clearAuth();
  });

  it("debería inyectar contexto cuando payload no tiene IDs", async () => {
    const { mergeAuthContext } = await import("@/services/authContext");
    
    const result = mergeAuthContext({
      order_type: "dine_in",
      table_id: "table-uuid",
    });

    expect(result.company_id).toBe("42");
    expect(result.branch_id).toBe("7");
    expect(result.user_id).toBe("user-merge-test");
    expect(result.user_name).toBe("Merge Test User");
    expect(result.order_type).toBe("dine_in");
    expect(result.table_id).toBe("table-uuid");
  });

  it("debería respetar IDs del caller cuando están presentes (override)", async () => {
    const { mergeAuthContext } = await import("@/services/authContext");
    
    // Caller explícitamente provee otros IDs (ej: sync desde otro terminal)
    const result = mergeAuthContext({
      company_id: "999",
      branch_id: "888",
      order_type: "take_out",
    });

    expect(result.company_id).toBe("999"); // Respeta override
    expect(result.branch_id).toBe("888");   // Respeta override
    expect(result.user_id).toBe("user-merge-test"); // Inyecta el faltante
    expect(result.order_type).toBe("take_out");
  });

  it("debería preservar todos los campos del payload original", async () => {
    const { mergeAuthContext } = await import("@/services/authContext");
    
    const result = mergeAuthContext({
      order_type: "dine_in",
      guest_count: 4,
      notes: "Mesa especial",
      custom_field: "custom_value",
    });

    expect(result.order_type).toBe("dine_in");
    expect(result.guest_count).toBe(4);
    expect(result.notes).toBe("Mesa especial");
    expect(result.custom_field).toBe("custom_value");
    expect(result.company_id).toBe("42");
  });
});

describe("getCashierContextSafe", () => {
  const mockUser = {
    id: 42,  // number
    uuid: "user-uuid-cashier",  // string UUID (lo correcto)
    name: "Cashier Test User",
    email: "cashier@test.com",
    role: "cashier" as const,
    company_id: 10,
    branch_id: 5,
    company: {
      id: 10,
      uuid: "company-uuid-abc",
      trade_name: "Test Company",
    },
    branch: {
      id: 5,
      name: "Test Branch",
      code: "TB01",
    },
  };

  beforeEach(async () => {
    await useAuthStore.getState().setAuth(mockUser, "token");
  });

  afterEach(async () => {
    await useAuthStore.getState().clearAuth();
  });

  it("debería retornar contexto completo cuando hay usuario", async () => {
    const { getCashierContextSafe } = await import("@/services/authContext");
    
    const ctx = getCashierContextSafe();

    expect(ctx).not.toBeNull();
    expect(ctx?.company_id).toBe("company-uuid-abc");  // UUID de company
    expect(ctx?.branch_id).toBe("5");
    expect(ctx?.user_id).toBe("user-uuid-cashier");  // ✅ UUID, NO "42"
    expect(ctx?.user_name).toBe("Cashier Test User");
    expect(ctx?.terminal_id).toBeTruthy();
  });

  it("debería retornar null si no hay usuario autenticado", async () => {
    await useAuthStore.getState().clearAuth();
    
    const { getCashierContextSafe } = await import("@/services/authContext");
    const ctx = getCashierContextSafe();

    expect(ctx).toBeNull();
  });

  it("debería usar user.uuid (no user.id) para user_id", async () => {
    // Validación crítica del FIX de bug
    const { getCashierContextSafe } = await import("@/services/authContext");
    const ctx = getCashierContextSafe();

    // user.id es 42 (number), pero user_id debe ser el UUID
    expect(ctx?.user_id).toBe("user-uuid-cashier");
    expect(ctx?.user_id).not.toBe("42");
    expect(ctx?.user_id).not.toBe(42);
  });

  it("debería usar company.uuid cuando está disponible", async () => {
    const { getCashierContextSafe } = await import("@/services/authContext");
    const ctx = getCashierContextSafe();

    // company_id debe ser el UUID de company, no el número
    expect(ctx?.company_id).toBe("company-uuid-abc");
    expect(ctx?.company_id).not.toBe("10");
  });

  it("debería hacer fallback a company_id numérico si company.uuid no está", async () => {
    // Usuario sin company.uuid (edge case)
    const userWithoutCompanyUuid = {
      ...mockUser,
      company: {
        id: 10,
        uuid: "",  // vacío
        trade_name: "Test Company",
      },
    };
    await useAuthStore.getState().setAuth(userWithoutCompanyUuid, "token");
    
    const { getCashierContextSafe } = await import("@/services/authContext");
    const ctx = getCashierContextSafe();

    // Fallback al company_id numérico
    expect(ctx?.company_id).toBe("10");
  });
});
