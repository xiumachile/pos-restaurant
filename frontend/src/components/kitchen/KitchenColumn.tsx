import type { KitchenOrder } from "@/types/kitchen";
import { OrderCard } from "./OrderCard";
import { Clock, ChefHat, CheckCircle2 } from "lucide-react";

interface KitchenColumnProps {
  title: string;
  icon: "confirmed" | "preparing" | "ready";
  orders: KitchenOrder[];
  onPrepare?: (uuid: string) => void;
  onReady?: (uuid: string) => void;
  onServe?: (uuid: string) => void;
  transitioningUuids?: Set<string>;
}

const COLUMN_CONFIG = {
  confirmed: {
    icon: Clock,
    color: "text-blue-400",
    bg: "bg-blue-900/20",
    border: "border-blue-700",
  },
  preparing: {
    icon: ChefHat,
    color: "text-amber-400",
    bg: "bg-amber-900/20",
    border: "border-amber-700",
  },
  ready: {
    icon: CheckCircle2,
    color: "text-green-400",
    bg: "bg-green-900/20",
    border: "border-green-700",
  },
};

export function KitchenColumn({
  title,
  icon,
  orders,
  onPrepare,
  onReady,
  onServe,
  transitioningUuids = new Set(),
}: KitchenColumnProps) {
  const config = COLUMN_CONFIG[icon];
  const Icon = config.icon;

  return (
    <div className="flex-1 flex flex-col overflow-hidden">
      {/* Header de columna */}
      <div
        className={`flex items-center justify-between p-4 rounded-t-xl border-b-2 ${config.border} ${config.bg}`}
      >
        <div className="flex items-center gap-2">
          <Icon size={20} className={config.color} />
          <h2 className="text-lg font-bold text-white">{title}</h2>
        </div>
        <span
          className={`px-2 py-1 rounded-full text-sm font-bold ${config.bg} ${config.color} border ${config.border}`}
        >
          {orders.length}
        </span>
      </div>

      {/* Lista de órdenes */}
      <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-900/50">
        {orders.length === 0 ? (
          <div className="text-center py-12 text-slate-500">
            <Icon size={48} className="mx-auto mb-3 opacity-30" />
            <p className="text-sm">Sin pedidos</p>
          </div>
        ) : (
          orders.map((order) => (
            <OrderCard
              key={order.uuid}
              order={order}
              onPrepare={onPrepare}
              onReady={onReady}
              onServe={onServe}
              isTransitioning={transitioningUuids.has(order.uuid)}
            />
          ))
        )}
      </div>
    </div>
  );
}
