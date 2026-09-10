/**
 * authContext.ts
 * 
 * Helper centralizado para obtener el contexto de autorización
 * multi-tenant en cualquier punto de la aplicación.
 * 
 * Garantiza que todas las operaciones offline incluyan los 4 IDs:
 *   - company_id
 *   - branch_id
 *   - terminal_id
 *   - user_id
 * 
 * USO:
 *   const ctx = getAuthContext();
 *   await OrderRepository.create({ ...ctx, order_type: "dine_in" });
 * 
 * PRINCIPIO: "Fail-safe"
 *   - Si no hay usuario autenticado → retorna null (caller decide)
 *   - Si falta algún ID requerido → lanza error (no silenciar)
 */

import { useAuthStore } from "@/store/useAuthStore";
import { getTerminalId } from "@/services/terminalIdentity";

export interface AuthContext {
  company_id: string;
  branch_id: string;
  terminal_id: string;
  user_id: string;
  user_name: string;
  user_role: string;
}

/**
 * Obtiene el contexto completo de autorización.
 * 
 * @throws Error si no hay usuario autenticado o faltan IDs críticos
 */
export function getAuthContext(): AuthContext {
  const state = useAuthStore.getState();
  const user = state.user;

  if (!user) {
    throw new Error(
      "[authContext] No hay usuario autenticado. " +
      "Operación multi-tenant rechazada."
    );
  }

  // Validar IDs críticos del usuario
  if (!user.company_id || !user.branch_id || !user.uuid) {
    throw new Error(
      "[authContext] Usuario autenticado sin IDs críticos. " +
      `company_id=${user.company_id}, branch_id=${user.branch_id}, uuid=${user.uuid}`
    );
  }

  return {
    company_id: String(user.company_id),
    branch_id: String(user.branch_id),
    terminal_id: getTerminalId(),
    user_id: user.uuid,
    user_name: user.name,
    user_role: user.role,
  };
}

/**
 * Versión "segura" que retorna null si no hay auth.
 * Útil para checks opcionales (UI, logs).
 */
export function getAuthContextSafe(): AuthContext | null {
  try {
    return getAuthContext();
  } catch {
    return null;
  }
}

/**
 * Valida que un contexto coincide con el usuario actual.
 * Usado por SyncEngine para rechazar items maliciosos.
 */
export function validateContext(ctx: {
  company_id?: string;
  branch_id?: string;
  user_id?: string;
}): boolean {
  const current = getAuthContextSafe();
  if (!current) return false;

  if (ctx.company_id && ctx.company_id !== current.company_id) return false;
  if (ctx.branch_id && ctx.branch_id !== current.branch_id) return false;
  if (ctx.user_id && ctx.user_id !== current.user_id) return false;

  return true;
}
