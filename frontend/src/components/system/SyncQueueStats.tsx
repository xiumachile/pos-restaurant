import { useSyncQueueStats } from "@/hooks/useSyncQueue";
import { useSyncStore } from "@/store/useSyncStore";
import { Clock, RefreshCw, CheckCircle, AlertCircle, History } from "lucide-react";

/**
 * Formato de tiempo relativo desde un timestamp.
 */
function formatTimeAgo(timestamp: string | null): string {
  if (!timestamp) return "Nunca";
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  
  if (diffMins < 1) return "Hace instantes";
  if (diffMins < 60) return `Hace ${diffMins}m`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `Hace ${diffHours}h`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `Hace ${diffDays}d`;
  return date.toLocaleDateString("es-CL");
}

export function SyncQueueStats() {
  const { data: stats, isLoading } = useSyncQueueStats();
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);

  if (isLoading || !stats) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="bg-slate-800 rounded-lg p-4 animate-pulse">
            <div className="h-4 bg-slate-700 rounded w-1/2 mb-2"></div>
            <div className="h-8 bg-slate-700 rounded"></div>
          </div>
        ))}
      </div>
    );
  }

  // Total histórico = todos los items en la cola visible
  const totalHistorical = stats.pending + stats.syncing + stats.synced + stats.failed;

  const cards = [
    {
      label: "Pendientes",
      value: stats.pending,
      icon: Clock,
      color: "text-yellow-400",
      bgColor: "bg-yellow-500/10",
      borderColor: "border-yellow-500/30",
    },
    {
      label: "Sincronizando",
      value: stats.syncing,
      icon: RefreshCw,
      color: "text-blue-400",
      bgColor: "bg-blue-500/10",
      borderColor: "border-blue-500/30",
    },
    {
      label: "Sincronizados",
      value: stats.synced,
      icon: CheckCircle,
      color: "text-green-400",
      bgColor: "bg-green-500/10",
      borderColor: "border-green-500/30",
    },
    {
      label: "Errores",
      value: stats.failed,
      icon: AlertCircle,
      color: "text-red-400",
      bgColor: "bg-red-500/10",
      borderColor: "border-red-500/30",
    },
    {
      label: "Última Sync",
      value: formatTimeAgo(lastSyncAt),
      icon: History,
      color: "text-purple-400",
      bgColor: "bg-purple-500/10",
      borderColor: "border-purple-500/30",
      isText: true,
    },
    {
      label: "Total Histórico",
      value: totalHistorical,
      icon: RefreshCw,
      color: "text-slate-400",
      bgColor: "bg-slate-700/50",
      borderColor: "border-slate-600",
    },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      {cards.map((card) => {
        const Icon = card.icon;
        return (
          <div
            key={card.label}
            className={`${card.bgColor} rounded-lg p-4 border ${card.borderColor}`}
          >
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs text-slate-400 uppercase tracking-wide">
                {card.label}
              </span>
              <Icon className={`w-4 h-4 ${card.color}`} />
            </div>
            <div className={`${card.isText ? "text-lg" : "text-2xl"} font-bold ${card.color}`}>
              {card.value}
            </div>
          </div>
        );
      })}
    </div>
  );
}
