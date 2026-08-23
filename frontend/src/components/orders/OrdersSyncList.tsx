import { useEffect, useState } from "react";
import { Package, AlertTriangle, CheckCircle, Clock, RefreshCw } from "lucide-react";
import { useSyncStore } from "@/store/useSyncStore";
import { OrderSyncCard } from "./OrderSyncCard";
import { localDb } from "@/db/localDb";

interface LocalOrder {
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
}

export function OrdersSyncList() {
  const [orders, setOrders] = useState<LocalOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const { status, pendingCount, triggerFullSync, refreshPendingCount } = useSyncStore();

  const loadOrders = async () => {
    try {
      const db = await localDb.getConnection();
      const result = await db.select(
        `SELECT 
          local_uuid,
          cloud_id,
          order_number,
          order_type,
          status,
          grand_total,
          sync_status,
          sync_error,
          created_at,
          waiter_name
        FROM local_orders
        ORDER BY created_at DESC
        LIMIT 50`
      );
      setOrders(result as LocalOrder[]);
    } catch (error) {
      console.error("[OrdersSyncList] Error cargando pedidos:", error);
    } finally {
      setLoading(false);
    }
  };

  const refreshAll = async () => {
    await loadOrders();
    await refreshPendingCount();
  };

  // Reintentar un pedido específico
  const handleRetry = async (orderId: string) => {
    try {
      const db = await localDb.getConnection();

      await db.execute(
        "UPDATE local_orders SET sync_status = 'pending', sync_error = NULL WHERE local_uuid = ?",
        [orderId]
      );

      await db.execute(
        `UPDATE sync_queue 
         SET sync_status = 'pending', attempts = 0, last_error = NULL, next_retry_at = NULL
         WHERE entity_local_uuid = ? AND sync_status = 'failed'`,
        [orderId]
      );

      await refreshAll();
      await triggerFullSync();
    } catch (error) {
      console.error("[OrdersSyncList] Error al reintentar:", error);
    }
  };

  // Sincronizar ahora: reintenta failed + procesa pending
  const handleSyncNow = async () => {
    try {
      setSyncing(true);
      const db = await localDb.getConnection();

      // Resetear eventos failed para reintentarlos
      await db.execute(
        `UPDATE sync_queue 
         SET sync_status = 'pending', attempts = 0, last_error = NULL, next_retry_at = NULL
         WHERE sync_status = 'failed'`
      );

      // Resetear pedidos failed a pending
      await db.execute(
        `UPDATE local_orders 
         SET sync_status = 'pending', sync_error = NULL
         WHERE sync_status = 'failed'`
      );

      await refreshAll();
      await triggerFullSync();
    } catch (error) {
      console.error("[OrdersSyncList] Error al sincronizar:", error);
    } finally {
      setSyncing(false);
    }
  };

  useEffect(() => {
    refreshAll();
    const interval = setInterval(refreshAll, 5000);
    return () => clearInterval(interval);
  }, []);

  const stats = {
    total: orders.length,
    synced: orders.filter((o) => o.sync_status === "synced").length,
    pending: orders.filter((o) => o.sync_status === "pending").length,
    syncing: orders.filter((o) => o.sync_status === "syncing").length,
    failed: orders.filter((o) => o.sync_status === "failed").length,
  };

  // Habilitar botón si hay trabajo por hacer
  const hasWork = pendingCount > 0 || stats.pending > 0 || stats.failed > 0;
  const buttonDisabled = status === "offline" || !hasWork || syncing;

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-center space-y-2">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto text-gray-400" />
          <p className="text-gray-500">Cargando pedidos...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Pedidos</h1>
          <p className="text-gray-600 mt-1">
            Estado de sincronización de pedidos locales
          </p>
        </div>
        <div className="flex items-center gap-2">
          {status === "offline" && (
            <div className="flex items-center gap-2 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded-lg">
              <AlertTriangle className="h-4 w-4 text-yellow-600" />
              <span className="text-sm text-yellow-800">Modo offline</span>
            </div>
          )}
          <button
            onClick={handleSyncNow}
            disabled={buttonDisabled}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
          >
            <RefreshCw className={`w-4 h-4 ${syncing || stats.syncing > 0 ? "animate-spin" : ""}`} />
            Sincronizar ahora
          </button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div className="bg-white rounded-lg border p-4">
          <div className="flex items-center gap-2 text-gray-600 mb-1">
            <Package className="w-4 h-4" />
            <span className="text-sm font-medium">Total</span>
          </div>
          <p className="text-2xl font-bold">{stats.total}</p>
        </div>

        <div className="bg-green-50 rounded-lg border border-green-200 p-4">
          <div className="flex items-center gap-2 text-green-600 mb-1">
            <CheckCircle className="w-4 h-4" />
            <span className="text-sm font-medium">Sincronizados</span>
          </div>
          <p className="text-2xl font-bold text-green-700">{stats.synced}</p>
        </div>

        <div className="bg-yellow-50 rounded-lg border border-yellow-200 p-4">
          <div className="flex items-center gap-2 text-yellow-600 mb-1">
            <Clock className="w-4 h-4" />
            <span className="text-sm font-medium">Pendientes</span>
          </div>
          <p className="text-2xl font-bold text-yellow-700">{stats.pending}</p>
        </div>

        <div className="bg-blue-50 rounded-lg border border-blue-200 p-4">
          <div className="flex items-center gap-2 text-blue-600 mb-1">
            <RefreshCw className="w-4 h-4" />
            <span className="text-sm font-medium">Sincronizando</span>
          </div>
          <p className="text-2xl font-bold text-blue-700">{stats.syncing}</p>
        </div>

        <div className="bg-red-50 rounded-lg border border-red-200 p-4">
          <div className="flex items-center gap-2 text-red-600 mb-1">
            <AlertTriangle className="w-4 h-4" />
            <span className="text-sm font-medium">Fallidos</span>
          </div>
          <p className="text-2xl font-bold text-red-700">{stats.failed}</p>
        </div>
      </div>

      {/* Orders List */}
      {orders.length === 0 ? (
        <div className="text-center py-12">
          <Package className="w-16 h-16 mx-auto text-gray-300 mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-1">No hay pedidos</h3>
          <p className="text-gray-500">Los pedidos que crees aparecerán aquí</p>
        </div>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => (
            <OrderSyncCard
              key={order.local_uuid}
              order={order}
              onRetry={handleRetry}
            />
          ))}
        </div>
      )}
    </div>
  );
}
