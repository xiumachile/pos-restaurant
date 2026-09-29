import { Navigate, Outlet } from "react-router-dom";
import { useAuthStore } from "@/store/useAuthStore";

export type UserRole = 'admin' | 'manager' | 'waiter' | 'cashier' | 'kitchen';

interface RoleProtectedRouteProps {
  allowedRoles: UserRole[];
}

/**
 * Protege rutas basadas en el rol del usuario.
 * Si el usuario no está autenticado, redirige a /login.
 * Si el usuario no tiene el rol permitido, redirige a / (Mesas).
 */
export function RoleProtectedRoute({ allowedRoles }: RoleProtectedRouteProps) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const user = useAuthStore((state) => state.user);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (!user || !allowedRoles.includes(user.role as UserRole)) {
    console.warn(`[RoleProtectedRoute] Acceso denegado: rol '${user?.role}' no permitido.`);
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}
