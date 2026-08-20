import { Clock } from "lucide-react";
import { useSyncStore } from "../../store/useSyncStore";

/**
 * Indicador compacto de última sincronización.
 * Para colocar en sidebar o footer.
 */
export function LastSyncIndicator() {
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);
  const pendingCount = useSyncStore((s) => s.pendingCount);

  const formatRelativeTime = (iso: string | null) => {
    if (!iso) return "Nunca";
    const date = new Date(iso);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 30) return "Justo ahora";
    if (diffSec < 60) return `hace ${diffSec}s`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `hace ${diffMin}m`;
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return `hace ${diffHour}h`;
    const diffDay = Math.floor(diffHour / 24);
    return `hace ${diffDay}d`;
  };

  return (
    <div className="flex items-center gap-2 px-3 py-2 text-xs text-slate-400">
      <Clock size={12} />
      <span>Última sync: {formatRelativeTime(lastSyncAt)}</span>
      {pendingCount > 0 && (
        <span className="bg-orange-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
          {pendingCount}
        </span>
      )}
    </div>
  );
}
