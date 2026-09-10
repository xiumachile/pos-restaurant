import { Cloud, CloudOff, RefreshCw } from "lucide-react";
import { SyncBadge, SyncErrorBox } from "@/components/system";

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
    <div className="bg-white rounded-lg border-2 p-4 border-gray-200">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 space-y-2">
          {/* Header */}
          <div className="flex items-center gap-2">
            <h3 className="font-semibold text-lg">#{order.order_number}</h3>
            <span className="px-2 py-1 text-xs font-medium border border-gray-300 rounded">
              {order.order_type === "dine_in" ? "Mesa" : "Para llevar"}
            </span>
            {order.cloud_id ? (
              <Cloud className="w-4 h-4 text-blue-500" aria-label="Sincronizado en la nube" />
            ) : (
              <CloudOff className="w-4 h-4 text-gray-400" aria-label="Solo local" />
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

          {/* Sync Status Badge */}
          <SyncBadge
            status={order.sync_status}
            cloudId={order.cloud_id}
            showCloudId={true}
            variant="normal"
          />

          {/* Error Message */}
          {order.sync_status === "failed" && order.sync_error && (
            <SyncErrorBox errorMessage={order.sync_error} className="mt-2" />
          )}
        </div>

        {/* Retry Button */}
        {order.sync_status === "failed" && onRetry && (
          <button
            onClick={() => onRetry(order.local_uuid)}
            className="flex items-center gap-2 px-3 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            aria-label="Reintentar sincronización"
          >
            <RefreshCw className="w-4 h-4" />
            Reintentar
          </button>
        )}
      </div>
    </div>
  );
}
