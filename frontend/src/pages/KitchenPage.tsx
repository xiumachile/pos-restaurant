import { useState } from "react";
import { useKitchenQueue, useKitchenStats, useKitchenTransition } from "@/hooks/useKitchenOrders";
import { KitchenColumn } from "@/components/kitchen/KitchenColumn";
import { Loader2, RefreshCw, ChefHat } from "lucide-react";
import type { KitchenOrder } from "@/types/kitchen";

export function KitchenPage() {
  const { data: zones = [], isLoading, refetch, isRefetching } = useKitchenQueue();
  const { data: stats } = useKitchenStats();
  const { prepare, ready, serve } = useKitchenTransition();

  const [transitioningUuids, setTransitioningUuids] = useState<Set<string>>(new Set());

  // Aplanar todas las órdenes de todas las zonas
  const allOrders = zones.flatMap((zone) => zone.orders);

  // Agrupar por estado
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

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <ChefHat size={32} className="text-orange-400" />
          <div>
            <h1 className="text-3xl font-bold">Cocina</h1>
            <p className="text-slate-400 mt-1">
              {stats?.total_active || 0} pedidos activos
              {stats?.avg_preparation_minutes && stats.avg_preparation_minutes > 0 && (
                <span className="ml-2">
                  · Promedio: {stats.avg_preparation_minutes} min
                </span>
              )}
            </p>
          </div>
        </div>

        <button
          onClick={() => refetch()}
          disabled={isRefetching}
          className="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-white transition-colors disabled:opacity-50"
        >
          <RefreshCw size={16} className={isRefetching ? "animate-spin" : ""} />
          Actualizar
        </button>
      </div>

      {/* 3 columnas */}
      <div className="flex-1 flex gap-4 overflow-hidden">
        <KitchenColumn
          title="Confirmados"
          icon="confirmed"
          orders={confirmed}
          onPrepare={(uuid) => handleTransition(uuid, prepare.mutateAsync)}
          transitioningUuids={transitioningUuids}
        />

        <KitchenColumn
          title="En Preparación"
          icon="preparing"
          orders={preparing}
          onReady={(uuid) => handleTransition(uuid, ready.mutateAsync)}
          transitioningUuids={transitioningUuids}
        />

        <KitchenColumn
          title="Listos"
          icon="ready"
          orders={readyOrders}
          onServe={(uuid) => handleTransition(uuid, serve.mutateAsync)}
          transitioningUuids={transitioningUuids}
        />
      </div>
    </div>
  );
}
