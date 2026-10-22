import { useTranslation } from 'react-i18next';
import { useSyncQueueStats } from "@/hooks/useSyncQueue";
import { useSyncStore } from "@/store/useSyncStore";
import { Clock, RefreshCw, CheckCircle, AlertCircle, History } from "lucide-react";

export function SyncQueueStats() {
  const { t } = useTranslation();
  const { data: stats, isLoading } = useSyncQueueStats();
  const lastSyncAt = useSyncStore((s) => s.lastSyncAt);

  // Función de tiempo relativo 100% traducida
  const formatTimeAgo = (timestamp: string | null) => {
    if (!timestamp) return t("sync.never");
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return t("sync.just_now");
    if (diffMins < 60) return t("sync.mins_ago", { count: diffMins });
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return t("sync.hours_ago", { count: diffHours });
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return t("sync.days_ago", { count: diffDays });
    return date.toLocaleDateString(); // Fallback seguro para fechas antiguas
  };

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

  const totalHistorical = stats.pending + stats.syncing + stats.synced + stats.failed;

  const cards = [
    {
      labelKey: "orders.pending",
      value: stats.pending,
      icon: Clock,
      color: "text-yellow-400",
      bgColor: "bg-yellow-500/10",
      borderColor: "border-yellow-500/30",
    },
    {
      labelKey: "orders.syncing",
      value: stats.syncing,
      icon: RefreshCw,
      color: "text-blue-400",
      bgColor: "bg-blue-500/10",
      borderColor: "border-blue-500/30",
    },
    {
      labelKey: "orders.synced",
      value: stats.synced,
      icon: CheckCircle,
      color: "text-green-400",
      bgColor: "bg-green-500/10",
      borderColor: "border-green-500/30",
    },
    {
      labelKey: "orders.failed",
      value: stats.failed,
      icon: AlertCircle,
      color: "text-red-400",
      bgColor: "bg-red-500/10",
      borderColor: "border-red-500/30",
    },
    {
      labelKey: "sync.last_sync",
      value: formatTimeAgo(lastSyncAt),
      icon: History,
      color: "text-purple-400",
      bgColor: "bg-purple-500/10",
      borderColor: "border-purple-500/30",
      isText: true,
    },
    {
      labelKey: "sync.total_historical",
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
            key={t(card.labelKey)}
            className={`${card.bgColor} rounded-lg p-4 border ${card.borderColor}`}
          >
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs text-slate-400 uppercase tracking-wide">
                {t(card.labelKey)}
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
