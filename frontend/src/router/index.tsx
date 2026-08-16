import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { LoginPage } from "@/pages/LoginPage";
import { TablesPage } from "@/pages/TablesPage";
import { CatalogPage } from "@/pages/CatalogPage";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuthStore } from "@/store/useAuthStore";

function ProtectedRoute() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />;
}

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

function ReportsPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Reportes</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción</p>
      </div>
    </div>
  );
}

function SettingsPage() {
  return (
    <div>
      <h1 className="text-3xl font-bold mb-4">Configuración</h1>
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <p className="text-slate-400">🚧 En construcción</p>
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
          { index: true, element: <TablesPage /> },
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
