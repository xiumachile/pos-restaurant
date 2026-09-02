import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { lazy, Suspense } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuthStore } from "@/store/useAuthStore";

// Lazy load de páginas (code splitting)
const LoginPage = lazy(() => import("@/pages/LoginPage").then(m => ({ default: m.LoginPage })));
const TablesPage = lazy(() => import("@/pages/TablesPage").then(m => ({ default: m.TablesPage })));
const OrderTakingPage = lazy(() => import("@/pages/OrderTakingPage").then(m => ({ default: m.OrderTakingPage })));
const CatalogPage = lazy(() => import("@/pages/CatalogPage").then(m => ({ default: m.CatalogPage })));
const KitchenPage = lazy(() => import("@/pages/KitchenPage").then(m => ({ default: m.KitchenPage })));
const CashierPage = lazy(() => import("@/pages/CashierPage").then(m => ({ default: m.CashierPage })));
const OrdersPage = lazy(() => import("@/pages/OrdersPage").then(m => ({ default: m.OrdersPage })));
const TipSettingsPage = lazy(() => import("@/pages/settings/TipSettingsPage").then(m => ({ default: m.TipSettingsPage })));
const CatalogSettingsPage = lazy(() => import("@/pages/settings/CatalogSettingsPage").then(m => ({ default: m.CatalogSettingsPage })));
const CapabilitiesPage = lazy(() => import("@/pages/settings/CapabilitiesPage").then(m => ({ default: m.CapabilitiesPage })));

// Componente de carga
function LoadingFallback() {
  return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
    </div>
  );
}

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
        <a
          href="/settings/capabilities"
          className="bg-slate-800 hover:bg-slate-700 rounded-lg p-6 transition-colors border border-slate-700"
        >
          <div className="text-2xl mb-2">🎛️</div>
          <h2 className="font-bold text-lg mb-1">Capacidades</h2>
          <p className="text-sm text-slate-400">
            Habilita o deshabilita funcionalidades
          </p>
        </a>
      </div>
    </div>
  );
}

export const router = createBrowserRouter([
  {
    path: "/login",
    element: (
      <Suspense fallback={<LoadingFallback />}>
        <LoginPage />
      </Suspense>
    ),
  },
  {
    path: "/",
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { 
            index: true, 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <TablesPage />
              </Suspense>
            )
          },
          { 
            path: "tables/:tableUuid", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <OrderTakingPage />
              </Suspense>
            )
          },
          { 
            path: "catalog", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CatalogPage />
              </Suspense>
            )
          },
          { 
            path: "kitchen", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <KitchenPage />
              </Suspense>
            )
          },
          { 
            path: "orders", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <OrdersPage />
              </Suspense>
            )
          },
          { 
            path: "cashier", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CashierPage />
              </Suspense>
            )
          },
          { path: "reports", element: <ReportsPage /> },
          { path: "settings", element: <SettingsPage /> },
          { 
            path: "settings/tips", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <TipSettingsPage />
              </Suspense>
            )
          },
          { 
            path: "settings/catalog", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CatalogSettingsPage />
              </Suspense>
            )
          },
          { 
            path: "settings/capabilities", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CapabilitiesPage />
              </Suspense>
            )
          },
        ],
      },
    ],
  },
]);
