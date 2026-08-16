import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { LoginPage } from "@/pages/LoginPage";
import { TablesPage } from "@/pages/TablesPage";
import { OrderTakingPage } from "@/pages/OrderTakingPage";
import { CatalogPage } from "@/pages/CatalogPage";
import { KitchenPage } from "@/pages/KitchenPage";
import { CashierPage } from "@/pages/CashierPage";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuthStore } from "@/store/useAuthStore";

function ProtectedRoute() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />;
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
          { path: "tables/:tableUuid", element: <OrderTakingPage /> },
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
