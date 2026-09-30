import { useTranslation } from 'react-i18next';
import { createBrowserRouter, Navigate, Outlet } from "react-router-dom";
import { lazy, Suspense } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuthStore } from "@/store/useAuthStore";
import { RoleProtectedRoute, type UserRole } from "@/components/auth/RoleProtectedRoute";

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
const SyncQueuePage = lazy(() => import("@/pages/SyncQueuePage").then(m => ({ default: m.SyncQueuePage })));
const PrinterSettingsPage = lazy(() => import("@/pages/settings/PrinterSettingsPage").then(m => ({ default: m.PrinterSettingsPage })));
const UsersPage = lazy(() => import("@/pages/settings/UsersPage").then(m => ({ default: m.UsersPage })));
const DefaultNotesPage = lazy(() => import("@/pages/catalog/DefaultNotesPage").then(m => ({ default: m.DefaultNotesPage })));

// Componente de carga
function LoadingFallback() {
  return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
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
  const { t } = useTranslation();
  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">{t("settings.title")}</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link to="" /settings/catalog>
          <div className="text-2xl mb-2">📦</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.catalog_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.catalog_desc")}
          </p>
        </Link>
        <Link to="" /settings/tips>
          <div className="text-2xl mb-2">💰</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.tips_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.tips_desc")}
          </p>
        </Link>
        <Link to="" /settings/capabilities>
          <div className="text-2xl mb-2">🎛️</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.capabilities_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.capabilities_desc")}
          </p>
        </Link>
        <Link to="" /settings/printers>
          <div className="text-2xl mb-2">🖨️</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.printers_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.printers_desc")}
          </p>
        </Link>
        <Link to="" /settings/users>
          <div className="text-2xl mb-2">👥</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.users_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.users_desc")}
          </p>
        </Link>
        <Link to="" /settings/default-notes>
          <div className="text-2xl mb-2">📝</div>
          <h2 className="font-bold text-lg mb-1">{t("settings.default_notes_title")}</h2>
          <p className="text-sm text-slate-400">
            {t("settings.default_notes_desc")}
          </p>
        </Link>
      </div>
    </div>
  );
}

// Definición de roles permitidos por sección
const ROLES = {
  ALL: ['admin', 'manager', 'waiter', 'cashier', 'kitchen'] as UserRole[],
  FRONT_OF_HOUSE: ['admin', 'manager', 'waiter', 'cashier'] as UserRole[],
  BACK_OF_HOUSE: ['admin', 'manager', 'kitchen'] as UserRole[],
  MANAGEMENT: ['admin', 'manager'] as UserRole[],
  CASHIER_ONLY: ['admin', 'manager', 'cashier'] as UserRole[],
};

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
    element: <AppLayout />,
    children: [
      // Mesas: todos los roles
      { 
        path: "",
        element: <RoleProtectedRoute allowedRoles={ROLES.ALL} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <TablesPage />
              </Suspense>
            )
          }
        ]
      },
      // Toma de pedidos: Front of house
      { 
        path: "tables/:tableUuid", 
        element: <RoleProtectedRoute allowedRoles={ROLES.FRONT_OF_HOUSE} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <OrderTakingPage />
              </Suspense>
            )
          }
        ]
      },
      // Catálogo: Front of house
      { 
        path: "catalog", 
        element: <RoleProtectedRoute allowedRoles={ROLES.FRONT_OF_HOUSE} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CatalogPage />
              </Suspense>
            )
          }
        ]
      },
      // Cocina: Back of house + admin/manager
      { 
        path: "kitchen", 
        element: <RoleProtectedRoute allowedRoles={ROLES.BACK_OF_HOUSE} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <KitchenPage />
              </Suspense>
            )
          }
        ]
      },
      // Pedidos: Front y Back of house
      { 
        path: "orders", 
        element: <RoleProtectedRoute allowedRoles={[...ROLES.FRONT_OF_HOUSE, ...ROLES.BACK_OF_HOUSE]} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <OrdersPage />
              </Suspense>
            )
          }
        ]
      },
      // Caja: Cashier y Management
      { 
        path: "cashier", 
        element: <RoleProtectedRoute allowedRoles={ROLES.CASHIER_ONLY} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CashierPage />
              </Suspense>
            )
          }
        ]
      },
      // Reportes: Solo Management
      { 
        path: "reports", 
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          {
            index: true,
            element: <ReportsPage />
          }
        ]
      },
      // Ajustes: Solo Management
      { 
        path: "settings", 
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          {
            index: true,
            element: <SettingsPage />
          },
          { 
            path: "tips", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <TipSettingsPage />
              </Suspense>
            )
          },
          { 
            path: "catalog", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CatalogSettingsPage />
              </Suspense>
            )
          },
          { 
            path: "capabilities", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <CapabilitiesPage />
              </Suspense>
            )
          },
          { 
            path: "printers", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <PrinterSettingsPage />
              </Suspense>
            )
          },
          { 
            path: "users", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <UsersPage />
              </Suspense>
            )
          },
          { 
            path: "default-notes", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <DefaultNotesPage />
              </Suspense>
            )
          }
        ]
      },
      // Cola de Sync: Solo Management
      { 
        path: "sync-queue", 
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <SyncQueuePage />
              </Suspense>
            )
          }
        ]
      },
    ],
  },
]);
