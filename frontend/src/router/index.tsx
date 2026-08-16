import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { LoginPage } from "@/pages/LoginPage";
import { AppLayout } from "@/components/layout/AppLayout";
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
 * Página temporal de Dashboard (Mesas).
 * Se reemplazará en F13.3.3 con la vista real de mesas.
 */
function DashboardPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Mesas</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">
          🚧 Vista de mesas en construcción (F13.3.3)
        </p>
      </div>
    </div>
  );
}

/**
 * Página temporal de Catálogo.
 * Se reemplazará en F13.3.4 con la vista real.
 */
function CatalogPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Catálogo</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">
          🚧 Vista de catálogo en construcción (F13.3.4)
        </p>
      </div>
    </div>
  );
}

/**
 * Página temporal de Cocina.
 */
function KitchenPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Cocina (KDS)</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción (F13.5)</p>
      </div>
    </div>
  );
}

/**
 * Página temporal de Caja.
 */
function CashierPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Caja</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción (F13.6)</p>
      </div>
    </div>
  );
}

/**
 * Página temporal de Reportes.
 */
function ReportsPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Reportes</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción (F13.7+)</p>
      </div>
    </div>
  );
}

/**
 * Página temporal de Configuración.
 */
function SettingsPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Configuración</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción (F13.8)</p>
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
        element: <AppLayout />,
        children: [
          { index: true, element: <DashboardPage /> },
          { path: "catalog", element: <CatalogPage /> },
          { path: "kitchen", element: <KitchenPage /> },
          { path: "cashier", element: <CashierPage /> },
          { path: "reports", element: <ReportsPage /> },
          { path: "settings", element: <SettingsPage /> },
        ],
      },
    ],
  },
]);
