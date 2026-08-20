import { RefreshCw, Wifi, WifiOff, AlertCircle } from "lucide-react";
import { useSyncStore } from "../../store/useSyncStore";

export function SyncStatusIndicator() {
  const status = useSyncStore((s) => s.status);
  const pendingCount = useSyncStore((s) => s.pendingCount);
  const progress = useSyncStore((s) => s.progress);
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);

  const getStatusIcon = () => {
    if (status === "syncing") {
      return <RefreshCw size={16} className="animate-spin text-blue-400" />;
    }
    if (status === "error") {
      return <AlertCircle size={16} className="text-red-400" />;
    }
    if (status === "offline") {
      return <WifiOff size={16} className="text-gray-400" />;
    }
    return <Wifi size={16} className="text-green-400" />;
  };

  const getStatusText = () => {
    if (status === "syncing" && progress) {
      return progress.message;
    }
    if (status === "error") {
      return "Error de sincronización";
    }
    if (status === "offline") {
      return "Sin conexión";
    }
    return "En línea";
  };

  const getStatusColor = () => {
    if (status === "syncing") return "border-blue-500 bg-blue-900/20";
    if (status === "error") return "border-red-500 bg-red-900/20";
    if (status === "offline") return "border-gray-500 bg-gray-900/20";
    return "border-green-500 bg-green-900/20";
  };

  const formatLastSync = () => {
    if (!lastSyncAt) return "Nunca";
    const diff = Date.now() - new Date(lastSyncAt).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return "Justo ahora";
    if (minutes < 60) return `Hace ${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours}h`;
    return `Hace ${Math.floor(hours / 24)}d`;
  };

  return (
    <div className="flex items-center gap-2">
      <div className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border ${getStatusColor()}`}>
        {getStatusIcon()}
        <span className="text-sm text-white">{getStatusText()}</span>
        {status === "syncing" && progress && progress.percentage > 0 && (
          <span className="text-xs text-blue-300">({progress.percentage}%)</span>
        )}
        {pendingCount > 0 && status !== "syncing" && (
          <span className="bg-amber-500 text-white text-xs px-1.5 py-0.5 rounded-full">
            {pendingCount}
          </span>
        )}
      </div>

      <button
        onClick={triggerFullSync}
        disabled={status === "syncing" || status === "offline"}
        className="p-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:cursor-not-allowed transition-colors"
        title={status === "offline" ? "Sin conexión" : "Sincronizar ahora"}
      >
        <RefreshCw size={16} className={status === "syncing" ? "animate-spin" : ""} />
      </button>

      {lastSyncAt && (
        <span className="text-xs text-gray-400">{formatLastSync()}</span>
      )}
    </div>
  );
}
