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
  Wifi,
  Database,
  WifiOff,
} from "lucide-react";
import { useTranslation } from 'react-i18next';
import { useAuthStore } from "@/store/useAuthStore";
import { useCapabilitiesStore } from "@/store/useCapabilitiesStore";
import { CapabilityKey } from "@/types/capabilities";
import type { UserRole } from "@/components/auth/RoleProtectedRoute";
import { useOnlineStatus } from "@/hooks/useOnlineStatus";

interface NavItem {
  to: string;
  label: string;
  icon: React.ElementType;
  end?: boolean;
  requiresCapability?: CapabilityKey;
  allowedRoles?: UserRole[];
}

/**
 * Sidebar principal de navegación.
 * Muestra las secciones del POS según capabilities de la empresa.
 */
export function Sidebar() {
  const { t } = useTranslation();
  const user = useAuthStore((state) => state.user);
  const clearAuth = useAuthStore((state) => state.clearAuth);
  const isEnabled = useCapabilitiesStore((state) => state.isCapabilityEnabled);
  const { online: isOnline } = useOnlineStatus();

  // Definición de roles para reutilizar
  const ROLES = {
    ALL: ['admin', 'manager', 'waiter', 'cashier', 'kitchen'] as UserRole[],
    FRONT_OF_HOUSE: ['admin', 'manager', 'waiter', 'cashier'] as UserRole[],
    BACK_OF_HOUSE: ['admin', 'manager', 'kitchen'] as UserRole[],
    MANAGEMENT: ['admin', 'manager'] as UserRole[],
    CASHIER_ONLY: ['admin', 'manager', 'cashier'] as UserRole[],
  };

  // NAV_ITEMS dentro del componente para que t() esté disponible
  const NAV_ITEMS: NavItem[] = [
    { to: "/", label: t("tables.title"), icon: LayoutGrid, end: true, allowedRoles: ROLES.ALL },
    { to: "/catalog", label: t("catalog.title"), icon: UtensilsCrossed, allowedRoles: ROLES.FRONT_OF_HOUSE },
    { 
      to: "/kitchen", 
      label: t("kitchen.title"), 
      icon: ChefHat,
      allowedRoles: ROLES.BACK_OF_HOUSE,
    },
    { to: "/orders", label: t("orders.title"), icon: ListOrdered, allowedRoles: [...ROLES.FRONT_OF_HOUSE, ...ROLES.BACK_OF_HOUSE] },
    { to: "/cashier", label: t("cashier.title"), icon: CreditCard, allowedRoles: ROLES.CASHIER_ONLY },
    { to: "/reports", label: t("reports.title"), icon: BarChart3, allowedRoles: ROLES.MANAGEMENT },
    { to: "/settings", label: t("settings.title"), icon: Settings, allowedRoles: ROLES.MANAGEMENT },
    { 
      to: "/sync-queue", 
      label: t("sync.queue"), 
      icon: Database,
      allowedRoles: ROLES.MANAGEMENT
    },
  ];

  const visibleItems = NAV_ITEMS.filter((item) => {
    // 1. Verificar rol del usuario
    if (item.allowedRoles && user && !item.allowedRoles.includes(user.role as UserRole)) {
      return false;
    }
    // 2. Verificar capability de la empresa (si aplica)
    if (item.requiresCapability && !isEnabled(item.requiresCapability)) {
      return false;
    }
    return true;
  });

  return (
    <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col h-full">
      {/* Logo */}
      <div className="p-6 border-b border-slate-800">
        <h1 className="text-xl font-bold bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
          🍜 Wok & Mesa
        </h1>
        <p className="text-xs text-slate-500 mt-1">{t("common.pos_system", "Sistema POS")}</p>
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
                    : "text-slate-400 hover:bg-slate-800 hover:text-white"
                }`
              }
            >
              <Icon size={20} />
              <span className="font-medium">{item.label}</span>
            </NavLink>
          );
        })}
      </nav>

      {/* Footer: User + Status */}
      <div className="p-4 border-t border-slate-800 space-y-3">
        {/* Estado de conexión */}
        <div className="flex items-center gap-2 px-2">
          {isOnline ? (
            <>
              <Wifi size={14} className="text-green-400" />
              <span className="text-xs text-green-400">{t("sync.online")}</span>
            </>
          ) : (
            <>
              <WifiOff size={14} className="text-amber-400" />
              <span className="text-xs text-amber-400">{t("sync.offline")}</span>
            </>
          )}
        </div>

        {/* Usuario y logout */}
        {user && (
          <div className="flex items-center justify-between px-2">
            <div className="min-w-0">
              <p className="text-sm font-medium text-slate-200 truncate">
                {user.name}
              </p>
              <p className="text-xs text-slate-500 truncate capitalize">
                {t(`roles.${user.role.toLowerCase()}`, user.role)}
              </p>
            </div>
            <button
              onClick={clearAuth}
              className="p-2 text-slate-500 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors"
              title={t("auth.logout")}
              aria-label={t("auth.logout")}
            >
              <LogOut size={16} />
            </button>
          </div>
        )}

        <p className="text-[10px] text-slate-600 text-center">
          v0.1.0 · Wok & Mesa POS
        </p>
      </div>
    </aside>
  );
}
