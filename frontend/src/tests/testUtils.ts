import { useAuthStore } from "@/store/useAuthStore";

/**
 * Mockea el contexto de autenticación para tests.
 * 
 * ADR-014: Fail-secure en validateContext()
 * Sin auth mockeado, validateContext() retorna false y bloquea operaciones.
 * 
 * NOTA: useAuthStore usa persist middleware de zustand, que puede interferir
 * en tests. Este helper fuerza el estado síncrono sin depender de persistencia.
 * 
 * Uso:
 *   beforeEach(() => {
 *     mockAuthContext();
 *   });
 * 
 * @param options - Opciones para customizar el contexto mockeado
 */
export function mockAuthContext(options?: {
  companyId?: string;
  branchId?: string;
  userId?: string;
  userName?: string;
}) {
  const mockUser = {
    id: 1,
    uuid: options?.userId || "test-user-123",
    name: options?.userName || "Test User",
    email: "test@example.com",
    role: "cashier" as const,
    // ADR-014: getAuthContext() requiere estos campos DIRECTOS (no anidados)
    company_id: options?.companyId || "company-1",
    branch_id: options?.branchId || "branch-1",
    company: {
      id: 1,
      uuid: options?.companyId || "company-1",
      trade_name: "Test Company",
    },
    branch: {
      id: 1,
      name: options?.branchId || "branch-1",
      code: "BR1",
    },
  };

  // Forzar estado síncrono, ignorando persistencia
  useAuthStore.setState({
    user: mockUser,
    isAuthenticated: true,
    token: "test-token",
  }, false, "mockAuthContext"); // false = no merge, reemplaza completamente
  
  // Verificar que el estado se aplicó correctamente
  const state = useAuthStore.getState();
  if (!state.user || state.user.uuid !== mockUser.uuid) {
    console.error("❌ mockAuthContext: setState falló", {
      expected: mockUser.uuid,
      actual: state.user?.uuid
    });
  }
}

/**
 * Limpia el auth context después de tests.
 * Forza estado null sin depender de persistencia.
 */
export function clearAuthContext() {
  useAuthStore.setState({
    user: null,
    isAuthenticated: false,
    token: null,
  }, false, "clearAuthContext"); // false = no merge, reemplaza completamente
}
