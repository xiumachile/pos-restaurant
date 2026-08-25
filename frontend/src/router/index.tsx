import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { LoginPage } from "@/pages/LoginPage";
import { TablesPage } from "@/pages/TablesPage";
import { OrderTakingPage } from "@/pages/OrderTakingPage";
import { CatalogPage } from "@/pages/CatalogPage";
import { KitchenPage } from "@/pages/KitchenPage";
import { CashierPage } from "@/pages/CashierPage";
import { OrdersPage } from "@/pages/OrdersPage";
import { TipSettingsPage } from "@/pages/settings/TipSettingsPage";
import { CatalogSettingsPage } from "@/pages/settings/CatalogSettingsPage";
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
      <h1 className="text-3xl font-bold mb-6">Configuración</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a
          href="/settings/catalog"
          className="bg-slate-800 hover:bg-slate-700 rounded-lg p-6 transition-colors border border-slate-700"
        >
          <div className="text-2xl mb-2">📦</div>
          <h2 className="font-bold text-lg mb-1">Catálogo</h2>
          <p className="text-sm text-slate-400">
            Categorías, productos, listas de precios y menús
          </p>
        </a>
        <a
          href="/settings/tips"
          className="bg-slate-800 hover:bg-slate-700 rounded-lg p-6 transition-colors border border-slate-700"
        >
          <div className="text-2xl mb-2">💰</div>
          <h2 className="font-bold text-lg mb-1">Propinas</h2>
          <p className="text-sm text-slate-400">
            Configura cómo se reparten las propinas
          </p>
        </a>
        <div className="bg-slate-800/50 rounded-lg p-6 border border-slate-700/50 opacity-50">
          <div className="text-2xl mb-2">🖨️</div>
          <h2 className="font-bold text-lg mb-1">Impresoras</h2>
          <p className="text-sm text-slate-400">🚧 Próximamente</p>
        </div>
        <div className="bg-slate-800/50 rounded-lg p-6 border border-slate-700/50 opacity-50">
          <div className="text-2xl mb-2">👥</div>
          <h2 className="font-bold text-lg mb-1">Usuarios</h2>
          <p className="text-sm text-slate-400">🚧 Próximamente</p>
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
        element: <AppLayout />,
        children: [
          { index: true, element: <TablesPage /> },
          { path: "tables/:tableUuid", element: <OrderTakingPage /> },
          { path: "catalog", element: <CatalogPage /> },
          { path: "kitchen", element: <KitchenPage /> },
          { path: "orders", element: <OrdersPage /> },
          { path: "cashier", element: <CashierPage /> },
          { path: "reports", element: <ReportsPage /> },
          { path: "settings", element: <SettingsPage /> },
          { path: "settings/tips", element: <TipSettingsPage /> },
          { path: "settings/catalog", element: <CatalogSettingsPage /> },
        ],
      },
    ],
  },
]);
