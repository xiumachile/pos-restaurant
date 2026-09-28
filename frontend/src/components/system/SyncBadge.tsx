import { useTranslation } from 'react-i18next';
import { Tooltip } from "@/components/ui/Tooltip";
import { Clock, CheckCircle, AlertCircle, RefreshCw } from "lucide-react";

export type SyncStatus = "pending" | "syncing" | "synced" | "failed";

interface SyncBadgeProps {
  status: SyncStatus;
  cloudId?: string | null;
  errorMessage?: string | null;
  variant?: "compact" | "normal";
  showCloudId?: boolean;
  className?: string;
}

const SYNC_CONFIG: Record<SyncStatus, {
  icon: typeof Clock;
  labelKey: string;
  color: string;
  bgColor: string;
  borderColor: string;
}> = {
  pending: {
    icon: Clock,
    labelKey: "Pendiente",
    color: "text-yellow-400",
    bgColor: "bg-yellow-900/20",
    borderColor: "border-yellow-800",
  },
  syncing: {
    icon: RefreshCw,
    labelKey: "Sincronizando",
    color: "text-blue-400",
    bgColor: "bg-blue-900/20",
    borderColor: "border-blue-800",
  },
  synced: {
    icon: CheckCircle,
    labelKey: "Sincronizado",
    color: "text-green-400",
    bgColor: "bg-green-900/20",
    borderColor: "border-green-800",
  },
  failed: {
    icon: AlertCircle,
    labelKey: "Error",
    color: "text-red-400",
    bgColor: "bg-red-900/20",
    borderColor: "border-red-800",
  },
};

export function SyncBadge({
  status,
  cloudId,
  errorMessage,
  variant = "normal",
  showCloudId = false,
  className = "",
}: SyncBadgeProps) {
  const { t } = useTranslation();
  const config = SYNC_CONFIG[status];
  const Icon = config.icon;
  const isSyncing = status === "syncing";

  const sizeClasses = variant === "compact" 
    ? "px-2 py-1 text-xs" 
    : "px-3 py-2 text-sm";

  const iconSize = variant === "compact" ? "w-3 h-3" : "w-4 h-4";

  const tooltipMessages = {
    pending: "Pedido guardado localmente. Se sincronizará automáticamente cuando haya conexión.",
    syncing: "Enviando datos al servidor en este momento...",
    synced: "Pedido sincronizado correctamente con la nube.",
    failed: "Error al sincronizar. Se reintentará automáticamente o puedes hacerlo manualmente.",
  };

  return (
    <Tooltip content={tooltipMessages[status]} position="top">
      <div
        className={`inline-flex items-center gap-2 rounded-lg border ${config.bgColor} ${config.borderColor} ${sizeClasses} ${className} cursor-help`}
        role="status"
        aria-label={`${t("sync.sync_status")}: ${t(config.labelKey)}`}
      >
      <Icon 
        className={`${iconSize} ${config.color} ${isSyncing && "animate-spin"}`}
        aria-hidden="true"
      />
      <span className={`font-medium ${config.color}`}>
        {config.labelKey}
      </span>
      {showCloudId && status === "synced" && cloudId && (
        <span className="text-xs text-gray-400 ml-auto" aria-label={`ID en la nube: ${cloudId}`}>
          ID: {cloudId.substring(0, 8)}...
        </span>
      )}
    </div>
    </Tooltip>
  );
}
