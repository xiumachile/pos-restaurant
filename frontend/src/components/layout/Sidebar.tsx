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
  WifiOff,
} from "lucide-react";
import { useAuthStore } from "@/store/useAuthStore";
import { useCapabilitiesStore } from "@/store/useCapabilitiesStore";
import { CapabilityKey } from "@/types/capabilities";
import { useOnlineStatus } from "@/hooks/useOnlineStatus";

interface NavItem {
  to: string;
  label: string;
  icon: React.ElementType;
  end?: boolean;
  requiresCapability?: CapabilityKey;
}

const NAV_ITEMS: NavItem[] = [
  { to: "/", label: "Mesas", icon: LayoutGrid, end: true },
  { to: "/catalog", label: "Catálogo", icon: UtensilsCrossed },
  { 
    to: "/kitchen", 
    label: "Cocina", 
    icon: ChefHat,
    requiresCapability: CapabilityKey.HAS_KITCHEN_DISPLAY,
  },
  { to: "/orders", label: "Pedidos", icon: ListOrdered },
  { to: "/cashier", label: "Caja", icon: CreditCard },
  { to: "/reports", label: "Reportes", icon: BarChart3 },
  { to: "/settings", label: "Configuración", icon: Settings },
];

/**
 * Sidebar principal de navegación.
 * Muestra las secciones del POS según capabilities de la empresa.
 */
export function Sidebar() {
  const user = useAuthStore((state) => state.user);
  const clearAuth = useAuthStore((state) => state.clearAuth);
  const isEnabled = useCapabilitiesStore((state) => state.isCapabilityEnabled);
  const { online: isOnline } = useOnlineStatus();

  const visibleItems = NAV_ITEMS.filter((item) => {
    if (!item.requiresCapability) return true;
    return isEnabled(item.requiresCapability);
  });

  return (
    <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col h-full">
      {/* Logo */}
      <div className="p-6 border-b border-slate-800">
        <h1 className="text-xl font-bold bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
          🍜 Wok & Mesa
        </h1>
        <p className="text-xs text-slate-500 mt-1">Sistema POS</p>
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
              <span className="text-xs text-green-400">En línea</span>
            </>
          ) : (
            <>
              <WifiOff size={14} className="text-amber-400" />
              <span className="text-xs text-amber-400">Offline</span>
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
                {user.role}
              </p>
            </div>
            <button
              onClick={clearAuth}
              className="p-2 text-slate-500 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors"
              title="Cerrar sesión"
              aria-label="Cerrar sesión"
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
