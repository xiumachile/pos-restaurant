import { useState } from "react";
import { useKitchenQueue, useKitchenStats, useKitchenTransition } from "@/hooks/useKitchenOrders";
import { KitchenColumn } from "@/components/kitchen/KitchenColumn";
import { TableHistoryModal } from "@/components/kitchen/TableHistoryModal";
import { TablesTodayView } from "@/components/kitchen/TablesTodayView";
import { Loader2, RefreshCw, ChefHat, History } from "lucide-react";

export function KitchenPage() {
  const [activeTab, setActiveTab] = useState<"queue" | "history">("queue");
  
  const { data: zones = [], isLoading, refetch, isRefetching } = useKitchenQueue();
  const { data: stats } = useKitchenStats();
  const { prepare, ready, serve } = useKitchenTransition();

  const [transitioningUuids, setTransitioningUuids] = useState<Set<string>>(new Set());
  const [selectedTableUuid, setSelectedTableUuid] = useState<string | null>(null);

  const allOrders = zones.flatMap((zone) => zone.orders);
  const confirmed = allOrders.filter((o) => o.status === "confirmed");
  const preparing = allOrders.filter((o) => o.status === "preparing");
  const readyOrders = allOrders.filter((o) => o.status === "ready");

  const handleTransition = async (
    uuid: string,
    action: (uuid: string) => Promise<any>
  ) => {
    setTransitioningUuids((prev) => new Set(prev).add(uuid));
    try {
      await action(uuid);
    } catch (error) {
      console.error("Error en transición:", error);
    } finally {
      setTransitioningUuids((prev) => {
        const next = new Set(prev);
        next.delete(uuid);
        return next;
      });
    }
  };

  const handleTableClick = (tableUuid: string) => {
    setSelectedTableUuid(tableUuid);
  };

  if (isLoading && activeTab === "queue") {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header con tabs */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-6">
          <div className="flex items-center gap-3">
            <ChefHat size={32} className="text-orange-400" />
            <div>
              <h1 className="text-3xl font-bold">Cocina</h1>
              {activeTab === "queue" && (
                <p className="text-slate-400 mt-1">
                  {stats?.total_active || 0} pedidos activos
                  {stats?.avg_preparation_minutes && stats.avg_preparation_minutes > 0 && (
                    <span className="ml-2">
                      · Promedio: {stats.avg_preparation_minutes} min
                    </span>
                  )}
                </p>
              )}
              {activeTab === "history" && (
                <p className="text-slate-400 mt-1">
                  Historial de mesas del día
                </p>
              )}
            </div>
          </div>

          {/* Tabs */}
          <div className="flex gap-2">
            <button
              onClick={() => setActiveTab("queue")}
              className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                activeTab === "queue"
                  ? "bg-orange-500 text-white"
                  : "bg-slate-800 text-slate-300 hover:bg-slate-700"
              }`}
            >
              Cola de Pedidos
            </button>
            <button
              onClick={() => setActiveTab("history")}
              className={`px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 ${
                activeTab === "history"
                  ? "bg-orange-500 text-white"
                  : "bg-slate-800 text-slate-300 hover:bg-slate-700"
              }`}
            >
              <History size={16} />
              Historial del Día
            </button>
          </div>
        </div>

        {activeTab === "queue" && (
          <button
            onClick={() => refetch()}
            disabled={isRefetching}
            className="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-white transition-colors disabled:opacity-50"
          >
            <RefreshCw size={16} className={isRefetching ? "animate-spin" : ""} />
            Actualizar
          </button>
        )}
      </div>

      {/* Contenido según tab activa */}
      {activeTab === "queue" && (
        <div className="flex-1 flex gap-4 overflow-hidden">
          <KitchenColumn
            title="Confirmados"
            icon="confirmed"
            orders={confirmed}
            onPrepare={(uuid) => handleTransition(uuid, prepare.mutateAsync)}
            onTableClick={handleTableClick}
            transitioningUuids={transitioningUuids}
          />

          <KitchenColumn
            title="En Preparación"
            icon="preparing"
            orders={preparing}
            onReady={(uuid) => handleTransition(uuid, ready.mutateAsync)}
            onTableClick={handleTableClick}
            transitioningUuids={transitioningUuids}
          />

          <KitchenColumn
            title="Listos"
            icon="ready"
            orders={readyOrders}
            onServe={(uuid) => handleTransition(uuid, serve.mutateAsync)}
            onTableClick={handleTableClick}
            transitioningUuids={transitioningUuids}
          />
        </div>
      )}

      {activeTab === "history" && <TablesTodayView />}

      {/* Modal de historial de mesa (desde cola) */}
      {selectedTableUuid && (
        <TableHistoryModal
          tableUuid={selectedTableUuid}
          isOpen={!!selectedTableUuid}
          onClose={() => setSelectedTableUuid(null)}
        />
      )}
    </div>
  );
}
