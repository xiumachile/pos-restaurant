import { useTranslation } from 'react-i18next';
import { ReportsPage } from "@/pages/ReportsPage";
import { FloorPlanPage } from "@/pages/FloorPlanPage";

import { createBrowserRouter, Navigate, Outlet, Link } from "react-router-dom";
import { lazy, Suspense } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuthStore } from "@/store/useAuthStore";
import { RoleProtectedRoute, type UserRole } from "@/components/auth/RoleProtectedRoute";

// Lazy load de páginas (code splitting)
const LoginPage = lazy(() => import("@/pages/LoginPage").then(m => ({ default: m.LoginPage })));
const TablesPage = lazy(() => import("@/pages/TablesPage").then(m => ({ default: m.TablesPage })));
const OrderTakingPage = lazy(() => import("@/pages/OrderTakingPage").then(m => ({ default: m.OrderTakingPage })));
const NewOrderPage = lazy(() => import("@/pages/NewOrderPage").then(m => ({ default: m.NewOrderPage })));
const TakeawayOrderPage = lazy(() => import("@/pages/TakeawayOrderPage").then(m => ({ default: m.TakeawayOrderPage })));
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
const GeneralSettingsPage = lazy(() => import("@/pages/settings/GeneralSettingsPage").then(m => ({ default: m.GeneralSettingsPage })));
const DefaultNotesPage = lazy(() => import("@/pages/catalog/DefaultNotesPage").then(m => ({ default: m.DefaultNotesPage })));
const InventoryPage = lazy(() => import("@/pages/InventoryPage").then(m => ({ default: m.InventoryPage })));

// Componente de carga
function LoadingFallback() {
  return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
    </div>
  );
}





function SettingsPage() {
  const { t } = useTranslation();
  
  const settingsItems = [
    {
      to: "/settings/general",
      icon: "⚙️",
      title: t("settings.general_title"),
      description: t("settings.general_desc"),
    },
    {
      to: "/settings/catalog",
      icon: "📦",
      title: t("settings.catalog_title"),
      description: t("settings.catalog_desc"),
    },
    {
      to: "/settings/tips",
      icon: "💰",
      title: t("settings.tips_title"),
      description: t("settings.tips_desc"),
    },
    {
      to: "/settings/capabilities",
      icon: "🎛️",
      title: t("settings.capabilities_title"),
      description: t("settings.capabilities_desc"),
    },
    {
      to: "/settings/printers",
      icon: "🖨️",
      title: t("settings.printers_title"),
      description: t("settings.printers_desc"),
    },
    {
      to: "/settings/users",
      icon: "👥",
      title: t("settings.users_title"),
      description: t("settings.users_desc"),
    },
    {
      to: "/settings/default-notes",
      icon: "📝",
      title: t("settings.default_notes_title"),
      description: t("settings.default_notes_desc"),
    },
    ];

  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">{t("settings.title")}</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {settingsItems.map((item) => (
          <Link
            key={item.to}
            to={item.to}
            className="bg-slate-800 hover:bg-slate-700 rounded-lg p-6 transition-colors border border-slate-700 hover:border-slate-600"
          >
            <div className="text-2xl mb-2">{item.icon}</div>
            <h2 className="font-bold text-lg mb-1">{item.title}</h2>
            <p className="text-sm text-slate-400">{item.description}</p>
          </Link>
        ))}
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
      // Pedido nuevo fuera de mesa: Front of house
      {
        path: "orders/new",
        element: <RoleProtectedRoute allowedRoles={ROLES.FRONT_OF_HOUSE} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <NewOrderPage />
              </Suspense>
            )
          }
        ]
      },
      // Pedido takeaway: Front of house
      {
        path: "orders/takeaway/:cartKey",
        element: <RoleProtectedRoute allowedRoles={ROLES.FRONT_OF_HOUSE} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <TakeawayOrderPage />
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
      // Inventario: Solo Management (Fase 3.2)
      {
        path: "inventory",
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <InventoryPage />
              </Suspense>
            )
          }
        ]
      },
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
      // Floor Plan (Sesión 1B - Desarrollo)
      {
        path: "floor-plan",
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          {
            index: true,
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <FloorPlanPage />
              </Suspense>
            )
          }
        ]
      },

      // Ajustes: Solo Management
      { 
        path: "settings", 
        element: <RoleProtectedRoute allowedRoles={ROLES.MANAGEMENT} />,
        children: [
          { 
            path: "general", 
            element: (
              <Suspense fallback={<LoadingFallback />}>
                <GeneralSettingsPage />
              </Suspense>
            )
          },
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
