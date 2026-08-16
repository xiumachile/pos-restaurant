import { useNavigate } from "react-router-dom";
import { LogOut, Wifi, WifiOff } from "lucide-react";
import { useAuth } from "@/hooks/useAuth";
import { useOnlineStatus } from "@/hooks/useOnlineStatus";

/**
 * Header con información del usuario, indicador de conexión y logout.
 */
export function Header() {
  const { user, logout } = useAuth();
  const { online, latency } = useOnlineStatus();
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

        {/* Estado de conexión + Logout */}
        <div className="flex items-center gap-4">
          {/* Indicador de conexión */}
          <div
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg ${
              online
                ? "bg-green-900/30 text-green-400"
                : "bg-red-900/30 text-red-400"
            }`}
            title={
              online
                ? `Conectado (${latency}ms)`
                : "Sin conexión con el servidor"
            }
          >
            {online ? <Wifi size={16} /> : <WifiOff size={16} />}
            <span className="text-sm font-medium">
              {online ? "En línea" : "Offline"}
            </span>
          </div>

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
