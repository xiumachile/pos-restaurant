import { useSyncStore } from '../../store/useSyncStore';
import { RefreshCw, Wifi, WifiOff, AlertCircle, CheckCircle2 } from 'lucide-react';

/**
 * Indicador de estado de sincronización offline-first.
 * 
 * UI/UX PRINCIPLES:
 * - 4 estados visuales claros con iconos + colores + mensajes
 * - Información contextual (pendientes, última sync, progreso)
 * - Acción de retry siempre disponible
 * - Accesibilidad: ARIA labels + contraste WCAG AA
 * 
 * ESTADOS:
 * 🟢 Online: "En línea" + pendiente count
 * 🟠 Offline: "Sin conexión · Los cambios se guardarán localmente"
 * ↻ Syncing: "Sincronizando..." + progreso %
 * ⚠ Error: "Error de sincronización" + botón "Reintentar"
 */
export function SyncStatusIndicator() {
  const status = useSyncStore((s) => s.status);
  const pendingCount = useSyncStore((s) => s.pendingCount);
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const progress = useSyncStore((s) => s.progress);
  const lastError = useSyncStore((s) => s.lastError);
  const triggerFullSync = useSyncStore((s) => s.triggerFullSync);

  const getStatusConfig = () => {
    // Syncing tiene prioridad
    if (status === 'syncing' || progress) {
      return {
        icon: <RefreshCw size={16} className="animate-spin text-blue-400" />,
        bgColor: 'bg-blue-500/20 border-blue-500/30',
        textColor: 'text-blue-100',
        message: progress?.message || 'Sincronizando...',
        ariaLabel: `Sincronizando: ${progress?.percentage || 0}% completado`,
      };
    }
    
    // Error
    if (status === 'error') {
      return {
        icon: <AlertCircle size={16} className="text-red-400" />,
        bgColor: 'bg-red-500/20 border-red-500/30',
        textColor: 'text-red-100',
        message: 'Error de sincronización',
        ariaLabel: `Error: ${lastError || 'Desconocido'}`,
      };
    }
    
    // Offline
    if (status === 'offline') {
      return {
        icon: <WifiOff size={16} className="text-amber-400" />,
        bgColor: 'bg-amber-500/20 border-amber-500/30',
        textColor: 'text-amber-100',
        message: 'Sin conexión · Los cambios se guardarán localmente',
        ariaLabel: 'Sin conexión, modo offline activo',
      };
    }
    
    // Online (default)
    return {
      icon: <CheckCircle2 size={16} className="text-green-400" />,
      bgColor: 'bg-green-500/20 border-green-500/30',
      textColor: 'text-green-100',
      message: 'En línea',
      ariaLabel: 'Conectado y sincronizado',
    };
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

  const config = getStatusConfig();
  const isSyncing = status === 'syncing' || !!progress;

  return (
    <div className="flex items-center gap-2">
      <div
        className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border ${config.bgColor} transition-all`}
        role="status"
        aria-live="polite"
        aria-label={config.ariaLabel}
      >
        {config.icon}
        <span className={`text-sm font-medium ${config.textColor}`}>
          {config.message}
        </span>
        
        {/* Badge de pendientes (solo si no está sincronizando) */}
        {pendingCount > 0 && !isSyncing && (
          <span 
            className="bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"
            title={`${pendingCount} cambios pendientes de sincronizar`}
          >
            {pendingCount}
          </span>
        )}
      </div>
      
      {/* Tiempo desde última sync (solo si no está sincronizando y hay timestamp) */}
      {lastSyncAt && !isSyncing && (
        <span className="text-xs text-slate-400">
          Última: {formatTime(lastSyncAt)}
        </span>
      )}

      {/* Botón de sync manual (siempre visible, deshabilitado durante sync) */}
      <button
        onClick={triggerFullSync}
        disabled={isSyncing}
        className="p-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
        title={isSyncing ? 'Sincronización en progreso' : 'Sincronizar ahora'}
        aria-label={isSyncing ? 'Sincronización en progreso' : 'Sincronizar ahora'}
      >
        <RefreshCw
          size={16}
          className={`text-blue-400 ${isSyncing ? 'animate-spin' : ''}`}
        />
      </button>
    </div>
  );
}
