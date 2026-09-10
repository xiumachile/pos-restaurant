import { useNavigate } from "react-router-dom";
import { LogOut } from "lucide-react";
import { useAuth } from "@/hooks/useAuth";
import { SyncStatusIndicator } from "@/components/system/SyncStatusIndicator";

/**
 * Header con información del usuario, indicador de sincronización y logout.
 * 
 * UI/UX OFFLINE-FIRST:
 * - SyncStatusIndicator muestra estado de conexión + pendientes + progreso
 * - 4 estados visuales: 🟢 Online | 🟠 Offline | ↻ Syncing | ⚠ Error
 * - Botón de sync manual siempre disponible
 */
export function Header() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  return (
    <header className="bg-slate-800/50 backdrop-blur-sm border-b border-slate-700 px-6 py-4">
      <div className="flex items-center justify-between">
        {/* Info del usuario */}
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold">
            {user?.name?.charAt(0).toUpperCase() || "U"}
          </div>
          <div>
            <p className="text-white font-medium">{user?.name}</p>
            <p className="text-xs text-slate-400 capitalize">{user?.role}</p>
          </div>
        </div>

        {/* SyncStatusIndicator + Logout */}
        <div className="flex items-center gap-4">
          <SyncStatusIndicator />

          {/* Botón de logout */}
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors"
          >
            <LogOut size={16} />
            <span className="text-sm font-medium">Cerrar sesión</span>
          </button>
        </div>
      </div>
    </header>
  );
}
