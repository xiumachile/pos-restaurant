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
  label: string;
  color: string;
  bgColor: string;
  borderColor: string;
}> = {
  pending: {
    icon: Clock,
    label: "Pendiente",
    color: "text-yellow-600",
    bgColor: "bg-yellow-50",
    borderColor: "border-yellow-200",
  },
  syncing: {
    icon: RefreshCw,
    label: "Sincronizando",
    color: "text-blue-600",
    bgColor: "bg-blue-50",
    borderColor: "border-blue-200",
  },
  synced: {
    icon: CheckCircle,
    label: "Sincronizado",
    color: "text-green-600",
    bgColor: "bg-green-50",
    borderColor: "border-green-200",
  },
  failed: {
    icon: AlertCircle,
    label: "Error",
    color: "text-red-600",
    bgColor: "bg-red-50",
    borderColor: "border-red-200",
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
  const config = SYNC_CONFIG[status];
  const Icon = config.icon;
  const isSyncing = status === "syncing";

  const sizeClasses = variant === "compact" 
    ? "px-2 py-1 text-xs" 
    : "px-3 py-2 text-sm";

  const iconSize = variant === "compact" ? "w-3 h-3" : "w-4 h-4";

  return (
    <div
      className={`inline-flex items-center gap-2 rounded-lg border ${config.bgColor} ${config.borderColor} ${sizeClasses} ${className}`}
      role="status"
      aria-label={`Estado de sincronización: ${config.label}`}
    >
      <Icon 
        className={`${iconSize} ${config.color} ${isSyncing && "animate-spin"}`}
        aria-hidden="true"
      />
      <span className={`font-medium ${config.color}`}>
        {config.label}
      </span>
      {showCloudId && status === "synced" && cloudId && (
        <span className="text-xs text-gray-500 ml-auto" aria-label={`ID en la nube: ${cloudId}`}>
          ID: {cloudId.substring(0, 8)}...
        </span>
      )}
    </div>
  );
}
