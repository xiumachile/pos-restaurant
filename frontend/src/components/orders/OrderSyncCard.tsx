import { Clock, CheckCircle, AlertCircle, RefreshCw, Cloud, CloudOff } from "lucide-react";

interface OrderSyncCardProps {
  order: {
    local_uuid: string;
    cloud_id: string | null;
    order_number: string;
    order_type: string;
    status: string;
    grand_total: number;
    sync_status: "pending" | "syncing" | "synced" | "failed";
    sync_error: string | null;
    created_at: string;
    waiter_name: string | null;
  };
  onRetry?: (orderId: string) => void;
}

export function OrderSyncCard({ order, onRetry }: OrderSyncCardProps) {
  const syncConfig = {
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

  const config = syncConfig[order.sync_status];
  const Icon = config.icon;

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat("es-CL", {
      style: "currency",
      currency: "CLP",
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <div className={`bg-white rounded-lg border-2 p-4 ${config.borderColor}`}>
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 space-y-2">
          {/* Header */}
          <div className="flex items-center gap-2">
            <h3 className="font-semibold text-lg">#{order.order_number}</h3>
            <span className="px-2 py-1 text-xs font-medium border border-gray-300 rounded">
              {order.order_type === "dine_in" ? "Mesa" : "Para llevar"}
            </span>
            {order.cloud_id ? (
              <Cloud className="w-4 h-4 text-blue-500" />
            ) : (
              <CloudOff className="w-4 h-4 text-gray-400" />
            )}
          </div>

          {/* Info */}
          <div className="text-sm text-gray-600 space-y-1">
            <div className="flex items-center gap-2">
              <span className="font-medium">Total:</span>
              <span className="font-semibold text-gray-900">
                {formatCurrency(order.grand_total)}
              </span>
            </div>
            {order.waiter_name && (
              <div className="flex items-center gap-2">
                <span className="font-medium">Mesero:</span>
                <span>{order.waiter_name}</span>
              </div>
            )}
            <div className="flex items-center gap-2">
              <span className="font-medium">Creado:</span>
              <span>{formatDate(order.created_at)}</span>
            </div>
          </div>

          {/* Sync Status */}
          <div className={`flex items-center gap-2 px-3 py-2 rounded-lg ${config.bgColor}`}>
            <Icon className={`w-5 h-5 ${config.color} ${order.sync_status === "syncing" && "animate-spin"}`} />
            <span className={`font-medium text-sm ${config.color}`}>
              {config.label}
            </span>
            {order.sync_status === "synced" && order.cloud_id && (
              <span className="text-xs text-gray-500 ml-auto">
                ID: {order.cloud_id.substring(0, 8)}...
              </span>
            )}
          </div>

          {/* Error Message */}
          {order.sync_status === "failed" && order.sync_error && (
            <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded-lg">
              <p className="text-sm text-red-800 font-medium mb-1">Error de sincronización:</p>
              <p className="text-xs text-red-600 font-mono break-all">
                {order.sync_error}
              </p>
            </div>
          )}
        </div>

        {/* Retry Button */}
        {order.sync_status === "failed" && onRetry && (
          <button
            onClick={() => onRetry(order.local_uuid)}
            className="flex items-center gap-2 px-3 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <RefreshCw className="w-4 h-4" />
            Reintentar
          </button>
        )}
      </div>
    </div>
  );
}
