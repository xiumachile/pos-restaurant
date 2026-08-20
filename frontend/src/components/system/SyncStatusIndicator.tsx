import { Wifi, WifiOff, Loader2, AlertCircle, RefreshCw } from "lucide-react";
import { useSyncStore } from "../../store/useSyncStore";

/**
 * Indicador visual del estado de sincronización.
 * Se coloca en el header/navbar de la app.
 */
export function SyncStatusIndicator() {
  const status = useSyncStore((s) => s.status);
  const pendingCount = useSyncStore((s) => s.pendingCount);
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const lastError = useSyncStore((s) => s.lastError);
  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);

  const formatTime = (iso: string | null) => {
    if (!iso) return "nunca";
    const date = new Date(iso);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    if (diffSec < 60) return `hace ${diffSec}s`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `hace ${diffMin}m`;
    const diffHour = Math.floor(diffMin / 60);
    return `hace ${diffHour}h`;
  };

  const config = {
    online: {
      icon: Wifi,
      color: "text-green-400",
      bg: "bg-green-500/10",
      border: "border-green-500/30",
      label: "En línea",
    },
    offline: {
      icon: WifiOff,
      color: "text-red-400",
      bg: "bg-red-500/10",
      border: "border-red-500/30",
      label: "Sin conexión",
    },
    syncing: {
      icon: Loader2,
      color: "text-blue-400",
      bg: "bg-blue-500/10",
      border: "border-blue-500/30",
      label: "Sincronizando",
    },
    error: {
      icon: AlertCircle,
      color: "text-orange-400",
      bg: "bg-orange-500/10",
      border: "border-orange-500/30",
      label: "Error",
    },
  }[status];

  const Icon = config.icon;

  return (
    <div className="flex items-center gap-2">
      <div
        className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border ${config.bg} ${config.border}`}
        title={lastError ? `Error: ${lastError}` : `Última sync: ${formatTime(lastSyncAt)}`}
      >
        <Icon size={16} className={`${config.color} ${status === "syncing" ? "animate-spin" : ""}`} />
        <span className={`text-xs font-medium ${config.color}`}>{config.label}</span>
        {pendingCount > 0 && (
          <span className="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[20px] text-center">
            {pendingCount}
          </span>
        )}
      </div>
      
      {/* Botón de sincronización manual */}
      <button
        onClick={triggerFullSync}
        disabled={status === "syncing" || status === "offline"}
        className="p-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 text-blue-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        title="Sincronizar ahora (push + pull)"
      >
        <RefreshCw size={16} className={status === "syncing" ? "animate-spin" : ""} />
      </button>
    </div>
  );
}
