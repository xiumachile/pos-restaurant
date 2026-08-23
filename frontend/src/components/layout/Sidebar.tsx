import { NavLink } from "react-router-dom";
import {
  LayoutGrid,
  UtensilsCrossed,
  ChefHat,
  CreditCard,
  ListOrdered,
  BarChart3,
  Settings,
} from "lucide-react";

interface NavItem {
  to: string;
  label: string;
  icon: React.ElementType;
  end?: boolean;
}

const NAV_ITEMS: NavItem[] = [
  { to: "/", label: "Mesas", icon: LayoutGrid, end: true },
  { to: "/catalog", label: "Catálogo", icon: UtensilsCrossed },
  { to: "/kitchen", label: "Cocina", icon: ChefHat },
  { to: "/orders", label: "Pedidos", icon: ListOrdered },
  { to: "/cashier", label: "Caja", icon: CreditCard },
  { to: "/reports", label: "Reportes", icon: BarChart3 },
  { to: "/settings", label: "Configuración", icon: Settings },
];

/**
 * Sidebar principal de navegación.
 * Muestra las secciones del POS: Mesas, Catálogo, Cocina, Caja, Reportes.
 */
export function Sidebar() {
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
        {NAV_ITEMS.map((item) => {
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

      {/* Footer */}
      <div className="p-4 border-t border-slate-800">
        <p className="text-xs text-slate-600 text-center">
          v0.1.0 · Tauri + React
        </p>
      </div>
    </aside>
  );
}
