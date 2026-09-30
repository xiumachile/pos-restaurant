import { Sun, Moon } from "lucide-react";
import { SyncStatusIndicator } from "@/components/system/SyncStatusIndicator";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { useThemeStore } from "@/store/useThemeStore";

/**
 * Header con indicadores del sistema.
 *
 * UI/UX OFFLINE-FIRST:
 * - SyncStatusIndicator muestra estado de conexión + pendientes + progreso
 * - 4 estados visuales: 🟢 Online | 🟠 Offline | ↻ Syncing | ⚠ Error
 * - Botón de sync manual siempre disponible
 *
 * NOTA: El bloque de usuario y logout vive en el Sidebar (fuente única).
 */
export function Header() {
  const { theme, toggleTheme } = useThemeStore();

  return (
    <header className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border-b border-slate-200 dark:border-slate-700 px-6 py-4 transition-colors duration-200">
      <div className="flex items-center justify-end gap-4">
        <SyncStatusIndicator />
        <LanguageSwitcher />

        {/* Toggle de tema */}
        <button
          onClick={toggleTheme}
          className="p-2 rounded-lg text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700 dark:text-slate-300 transition-colors"
          aria-label={theme === "dark" ? "Modo claro" : "Modo oscuro"}
          title={theme === "dark" ? "Modo claro" : "Modo oscuro"}
        >
          {theme === "dark" ? <Sun size={16} /> : <Moon size={16} />}
        </button>
      </div>
    </header>
  );
}
