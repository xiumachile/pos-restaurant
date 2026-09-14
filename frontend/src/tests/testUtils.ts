import { useAuthStore } from "@/store/useAuthStore";
import type { User } from "@/types/auth";

/**
 * Mockea el contexto de autenticación para tests.
 * 
 * ADR-014: Fail-secure en validateContext()
 * Sin auth mockeado, validateContext() retorna false y bloquea operaciones.
 * 
 * NOTA: Acepta strings para company_id/branch_id (como usan los tests),
 * pero internamente convierte a numbers para satisfacer el tipo User.
 * getAuthContext() luego convierte de vuelta a strings para las queries.
 * 
 * Uso:
 *   beforeEach(() => {
 *     mockAuthContext({ companyId: "company-1", branchId: "branch-1" });
 *   });
 * 
 * @param options - Opciones para customizar el contexto mockeado
 */
export function mockAuthContext(options?: {
  companyId?: string | number;
  branchId?: string | number;
  userId?: string;
  userName?: string;
}) {
  // Extraer valores (aceptar string o number)
  const companyId = options?.companyId || "company-1";
  const branchId = options?.branchId || "branch-1";
  
  // Convertir a number para el tipo User (si es string, parsear)
  const companyIdNum = typeof companyId === "string" ? parseInt(companyId.replace(/\D/g, "")) || 1 : companyId;
  const branchIdNum = typeof branchId === "string" ? parseInt(branchId.replace(/\D/g, "")) || 1 : branchId;
  
  // Crear usuario compatible con el tipo User
  const mockUser: User = {
    id: 1,
    uuid: options?.userId || "test-user-123",
    name: options?.userName || "Test User",
    email: "test@example.com",
    role: "cashier",
    company_id: companyIdNum,
    branch_id: branchIdNum,
    company: {
      id: companyIdNum,
      uuid: String(companyId),
      trade_name: "Test Company",
    },
    branch: {
      id: branchIdNum,
      uuid: String(branchId),  // ← Preservar string original del backend
      name: String(branchId),
      code: `BR${branchIdNum}`,
    },
  };

  useAuthStore.setState({
    user: mockUser,
    isAuthenticated: true,
    token: "test-token",
  });
  
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
 */
export function clearAuthContext() {
  useAuthStore.setState({
    user: null,
    isAuthenticated: false,
    token: null,
  });
}
