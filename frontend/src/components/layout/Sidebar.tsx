import { useMemo } from "react";
import { NavLink } from "react-router-dom";
import {
  LayoutGrid,
  UtensilsCrossed,
  ChefHat,
  CreditCard,
  ListOrdered,
  BarChart3,
  Settings,
  LogOut,
  Database,
  Package,
  Map,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { useAuthStore } from "@/store/useAuthStore";
import { useAuth } from "@/hooks/useAuth";
import { useCapabilitiesStore } from "@/store/useCapabilitiesStore";
import { CapabilityKey } from "@/types/capabilities";
import type { UserRole } from "@/components/auth/RoleProtectedRoute";

interface NavItem {
  to: string;
  label: string;
  icon: React.ElementType;
  end?: boolean;
  requiresCapability?: CapabilityKey;
  allowedRoles?: UserRole[];
}

// Definición de roles
const ROLES = {
  ALL: ["admin", "manager", "waiter", "cashier", "kitchen"] as UserRole[],
  FRONT_OF_HOUSE: ["admin", "manager", "waiter", "cashier"] as UserRole[],
  BACK_OF_HOUSE: ["admin", "manager", "kitchen"] as UserRole[],
  MANAGEMENT: ["admin", "manager"] as UserRole[],
  CASHIER_ONLY: ["admin", "manager", "cashier"] as UserRole[],
};

/**
 * Sidebar principal de navegación.
 */
export function Sidebar() {
  const { t } = useTranslation();
  const user = useAuthStore((state) => state.user);
  const { logout } = useAuth();
  const isEnabled = useCapabilitiesStore((state) => state.isCapabilityEnabled);

  const NAV_ITEMS: NavItem[] = useMemo(
    () => [
      { to: "/", label: t("tables.title"), icon: LayoutGrid, end: true, allowedRoles: ROLES.ALL },
      {
        to: "/orders/new",
        label: t("orders.new_order", "Nuevo Pedido"),
        icon: UtensilsCrossed,
        allowedRoles: ROLES.FRONT_OF_HOUSE,
      },
      {
        to: "/kitchen",
        label: t("kitchen.title"),
        icon: ChefHat,
        allowedRoles: ROLES.BACK_OF_HOUSE,
      },
      {
        to: "/cashier",
        label: t("cashier.title"),
        icon: CreditCard,
        allowedRoles: ROLES.CASHIER_ONLY,
      },
      {
        to: "/inventory",
        label: t("inventory.title", "Inventario"),
        icon: Package,
        allowedRoles: ROLES.MANAGEMENT,
        requiresCapability: CapabilityKey.CAN_MANAGE_RECIPES,
      },
      { to: "/reports", label: t("reports.title"), icon: BarChart3, allowedRoles: ROLES.MANAGEMENT },
      {
        to: "/floor-plan",
        label: t("navigation.floor_plan", "Plano del Restaurante"),
        icon: Map,
        allowedRoles: ROLES.MANAGEMENT,
      },
      {
        to: "/sync-queue",
        label: t("sidebar.sync"),
        icon: Database,
        allowedRoles: ROLES.MANAGEMENT,
      },
      { to: "/settings", label: t("settings.title"), icon: Settings, allowedRoles: ROLES.MANAGEMENT },
    ],
    [t]
  );

  const visibleItems = NAV_ITEMS.filter((item) => {
    if (item.allowedRoles && user && !item.allowedRoles.includes(user.role as UserRole)) {
      return false;
    }
    if (item.requiresCapability && !isEnabled(item.requiresCapability)) {
      return false;
    }
    return true;
  });

  const handleLogout = async () => {
    try {
      await logout();
    } catch (error) {
      console.error("Error al cerrar sesión:", error);
    }
  };

  return (
    <aside className="w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 flex flex-col h-full transition-colors duration-200">
      {/* Logo */}
      <div className="p-6 border-b border-gray-200 dark:border-slate-800">
        <h1 className="text-xl font-bold bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
          🍜 Wok & Mesa
        </h1>
        <p className="text-xs text-gray-600 dark:text-slate-400 mt-1">
          {t("common.pos_system", "Sistema POS")}
        </p>
      </div>

      {/* Navegación */}
      <nav className="flex-1 p-4 space-y-1">
        {visibleItems.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                  isActive
                    ? "bg-orange-500 text-white"
                    : "text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white"
                }`
              }
            >
              <Icon size={22} />
              <span className="font-medium">{item.label}</span>
            </NavLink>
          );
        })}
      </nav>

      {/* Footer: usuario + logout */}
      <div className="p-4 border-t border-gray-200 dark:border-slate-800 space-y-3">
        {user && (
          <div className="flex items-center justify-between px-2">
            <div className="min-w-0">
              <p className="text-sm font-medium text-gray-900 dark:text-slate-200 truncate">
                {user.name}
              </p>
              <p className="text-xs text-gray-600 dark:text-slate-400 truncate capitalize">
                {t(`roles.${user.role.toLowerCase()}`, user.role)}
              </p>
            </div>
            <button
              onClick={handleLogout}
              className="p-2 text-gray-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
              title={t("auth.logout", "Cerrar sesión")}
            >
              <LogOut size={18} />
            </button>
          </div>
        )}
      </div>
    </aside>
  );
}
