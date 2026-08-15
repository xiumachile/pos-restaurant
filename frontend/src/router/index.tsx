import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { LoginPage } from "@/pages/LoginPage";
import { useAuthStore } from "@/store/useAuthStore";

/**
 * Componente de ruta protegida.
 * Si no está autenticado, redirige a /login.
 */
function ProtectedRoute() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />;
}

/**
 * Página principal temporal (Dashboard).
 * Se reemplazará en F13.3 con la vista de mesas.
 */
function DashboardPage() {
  const { user, clearAuth } = useAuthStore();
  return (
    <div className="min-h-screen bg-slate-900 text-white p-8">
      <div className="max-w-4xl mx-auto">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold">Dashboard</h1>
          <button
            onClick={() => {
              clearAuth();
              window.location.href = "/login";
            }}
            className="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white"
          >
            Cerrar sesión
          </button>
        </div>
        <div className="bg-slate-800 rounded-lg p-6">
          <h2 className="text-xl font-semibold mb-4">Bienvenido, {user?.name}!</h2>
          <pre className="text-sm text-slate-400 overflow-x-auto">
            {JSON.stringify(user, null, 2)}
          </pre>
        </div>
      </div>
    </div>
  );
}

export const router = createBrowserRouter([
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/",
    element: <ProtectedRoute />,
    children: [
      {
        index: true,
        element: <DashboardPage />,
      },
      // Aquí se agregarán las rutas de mesas, pedidos, cocina, etc.
    ],
  },
]);
