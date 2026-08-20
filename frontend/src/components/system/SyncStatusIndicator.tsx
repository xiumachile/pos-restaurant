import { useSyncStore } from '../../store/useSyncStore';
import { RefreshCw, Wifi, WifiOff, AlertCircle, CheckCircle2 } from 'lucide-react';

export function SyncStatusIndicator() {
  const status = useSyncStore((s) => s.status);
  const pendingCount = useSyncStore((s) => s.pendingCount);
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const progress = useSyncStore((s) => s.progress);
  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);

  const getStatusIcon = () => {
    if (status === 'syncing' || progress) {
      return <RefreshCw size={16} className="animate-spin text-blue-400" />;
    }
    if (status === 'error') {
      return <AlertCircle size={16} className="text-red-400" />;
    }
    if (status === 'offline') {
      return <WifiOff size={16} className="text-amber-400" />;
    }
    return <CheckCircle2 size={16} className="text-green-400" />;
  };

  const getStatusText = () => {
    if (progress) {
      return `${progress.message} (${progress.percentage}%)`;
    }
    if (status === 'error') {
      return 'Error de sincronización';
    }
    if (status === 'offline') {
      return 'Sin conexión';
    }
    return 'En línea';
  };

  const getBgColor = () => {
    if (progress || status === 'syncing') {
      return 'bg-blue-500/20 border-blue-500/30';
    }
    if (status === 'error') {
      return 'bg-red-500/20 border-red-500/30';
    }
    if (status === 'offline') {
      return 'bg-amber-500/20 border-amber-500/30';
    }
    return 'bg-green-500/20 border-green-500/30';
  };

  const formatTime = (timestamp: string | null) => {
    if (!timestamp) return 'Nunca';
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Justo ahora';
    if (diffMins < 60) return `hace ${diffMins}m`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `hace ${diffHours}h`;
    return date.toLocaleDateString();
  };

  return (
    <div className="flex items-center gap-2">
      <div
        className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border ${getBgColor()} transition-all`}
      >
        {getStatusIcon()}
        <span className="text-sm font-medium text-slate-200">
          {getStatusText()}
        </span>
        {pendingCount > 0 && !progress && (
          <span className="bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {pendingCount}
          </span>
        )}
      </div>
      
      {lastSyncAt && !progress && (
        <span className="text-xs text-slate-400">
          Última: {formatTime(lastSyncAt)}
        </span>
      )}

      <button
        onClick={triggerFullSync}
        disabled={status === 'syncing' || !!progress}
        className="p-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
        title="Sincronizar ahora"
      >
        <RefreshCw
          size={16}
          className={`text-blue-400 ${status === 'syncing' || progress ? 'animate-spin' : ''}`}
        />
      </button>
    </div>
  );
}
