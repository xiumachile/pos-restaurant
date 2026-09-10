import { useSyncQueueStats } from "@/hooks/useSyncQueue";
import { Clock, RefreshCw, CheckCircle, AlertCircle } from "lucide-react";

export function SyncQueueStats() {
  const { data: stats, isLoading } = useSyncQueueStats();

  if (isLoading || !stats) {
    return (
      <div className="grid grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="bg-slate-800 rounded-lg p-4 animate-pulse">
            <div className="h-4 bg-slate-700 rounded w-1/2 mb-2"></div>
            <div className="h-8 bg-slate-700 rounded"></div>
          </div>
        ))}
      </div>
    );
  }

  const cards = [
    {
      label: "Pendientes",
      value: stats.pending,
      icon: Clock,
      color: "text-yellow-400",
      bgColor: "bg-yellow-500/10",
    },
    {
      label: "Sincronizando",
      value: stats.syncing,
      icon: RefreshCw,
      color: "text-blue-400",
      bgColor: "bg-blue-500/10",
    },
    {
      label: "Sincronizados",
      value: stats.synced,
      icon: CheckCircle,
      color: "text-green-400",
      bgColor: "bg-green-500/10",
    },
    {
      label: "Fallidos",
      value: stats.failed,
      icon: AlertCircle,
      color: "text-red-400",
      bgColor: "bg-red-500/10",
    },
  ];

  return (
    <div className="grid grid-cols-4 gap-4">
      {cards.map((card) => {
        const Icon = card.icon;
        return (
          <div
            key={card.label}
            className={`${card.bgColor} rounded-lg p-4 border border-slate-700`}
          >
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-slate-400">{card.label}</span>
              <Icon className={`w-5 h-5 ${card.color}`} />
            </div>
            <div className={`text-3xl font-bold ${card.color}`}>
              {card.value}
            </div>
          </div>
        );
      })}
    </div>
  );
}
