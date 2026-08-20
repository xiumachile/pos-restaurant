import { Outlet } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Header } from "./Header";
import { useAutoSync } from "../../hooks/useAutoSync";

/**
 * Layout principal de la aplicación.
 * Estructura: Sidebar (izquierda) + Header (arriba) + Contenido (Outlet).
 *
 * Incluye auto-sync:
 * - Sincronización al cargar (una vez por sesión)
 * - Sincronización periódica cada 5 minutos
 */
export function AppLayout() {
  // Auto-sync: al montar + cada 5 minutos
  useAutoSync({ syncOnMount: true, intervalMinutes: 5 });

  return (
    <div className="flex h-screen bg-slate-900 text-white overflow-hidden">
      {/* Sidebar */}
      <Sidebar />

      {/* Contenido principal */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <Header />

        {/* Outlet renderiza las rutas hijas */}
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
