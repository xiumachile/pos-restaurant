import { useState } from "react";
import type { KitchenOrder } from "@/types/kitchen";
import { getUrgencyColor, formatWaitingTime } from "@/types/kitchen";
import { Clock, Play, CheckCircle2, UtensilsCrossed, AlertTriangle, Loader2 } from "lucide-react";

interface OrderCardProps {
  order: KitchenOrder;
  onPrepare?: (uuid: string) => void;
  onReady?: (uuid: string) => void;
  onServe?: (uuid: string) => void;
  isTransitioning?: boolean;
}

const PRIORITY_CONFIG = {
  vip: { label: "VIP", color: "text-purple-400", bg: "bg-purple-900/30", border: "border-purple-500" },
  rush: { label: "URGENTE", color: "text-red-400", bg: "bg-red-900/30", border: "border-red-500" },
  normal: { label: "Normal", color: "text-slate-400", bg: "bg-slate-800", border: "border-slate-700" },
};

export function OrderCard({
  order,
  onPrepare,
  onReady,
  onServe,
  isTransitioning = false,
}: OrderCardProps) {
  const [isProcessing, setIsProcessing] = useState(false);

  const urgency = getUrgencyColor(order.waiting_minutes);
  const priority = PRIORITY_CONFIG[order.priority];

  const handleTransition = async (action: () => Promise<void>) => {
    setIsProcessing(true);
    try {
      await action();
    } finally {
      setIsProcessing(false);
    }
  };

  const getActionButton = () => {
    if (isProcessing || isTransitioning) {
      return (
        <button
          disabled
          className="w-full py-2 bg-slate-700 rounded-lg text-slate-400 font-medium flex items-center justify-center gap-2"
        >
          <Loader2 size={16} className="animate-spin" />
          Procesando...
        </button>
      );
    }

    switch (order.status) {
      case "confirmed":
        return (
          <button
            onClick={() => handleTransition(async () => onPrepare?.(order.uuid))}
            className="w-full py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-medium flex items-center justify-center gap-2 transition-colors"
          >
            <Play size={16} />
            Empezar
          </button>
        );
      case "preparing":
        return (
          <button
            onClick={() => handleTransition(async () => onReady?.(order.uuid))}
            className="w-full py-2 bg-amber-500 hover:bg-amber-600 rounded-lg text-white font-medium flex items-center justify-center gap-2 transition-colors"
          >
            <CheckCircle2 size={16} />
            Listo
          </button>
        );
      case "ready":
        return (
          <button
            onClick={() => handleTransition(async () => onServe?.(order.uuid))}
            className="w-full py-2 bg-green-500 hover:bg-green-600 rounded-lg text-white font-medium flex items-center justify-center gap-2 transition-colors"
          >
            <UtensilsCrossed size={16} />
            Servido
          </button>
        );
      default:
        return null;
    }
  };

  return (
    <div
      className={`rounded-xl border-2 ${urgency.border} ${urgency.bg} p-4 space-y-3 transition-all ${
        urgency.pulse ? "animate-pulse" : ""
      }`}
    >
      {/* Header: tiempo + mesa + prioridad */}
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2 flex-1 min-w-0">
          <Clock size={16} className={urgency.text} />
          <span className={`font-bold ${urgency.text}`}>
            {formatWaitingTime(order.waiting_minutes)}
          </span>
        </div>

        {order.priority !== "normal" && (
          <span
            className={`px-2 py-0.5 rounded text-xs font-bold ${priority.bg} ${priority.color} border ${priority.border}`}
          >
            {priority.label}
          </span>
        )}
      </div>

      {/* Mesa + área */}
      <div className="text-sm text-slate-300">
        {order.table_number && (
          <span className="font-semibold">Mesa {order.table_number}</span>
        )}
        {order.area_code && (
          <span className="text-slate-500 ml-2">({order.area_code})</span>
        )}
      </div>

      {/* Items */}
      <div className="space-y-1 pt-2 border-t border-slate-700">
        {order.items.map((item) => (
          <div key={item.uuid} className="flex items-start gap-2 text-sm">
            <span className="font-semibold text-white flex-shrink-0">
              {item.quantity}×
            </span>
            <span className="text-slate-200 flex-1 min-w-0">{item.name}</span>
          </div>
        ))}
      </div>

      {/* Notas */}
      {order.notes && (
        <div className="text-xs text-slate-400 italic bg-slate-900/50 rounded p-2">
          📝 {order.notes}
        </div>
      )}

      {/* Notas de items */}
      {order.items.some((i) => i.notes) && (
        <div className="space-y-1">
          {order.items
            .filter((i) => i.notes)
            .map((item) => (
              <div
                key={item.uuid}
                className="text-xs text-slate-400 italic bg-slate-900/50 rounded p-2"
              >
                📝 {item.name}: {item.notes}
              </div>
            ))}
        </div>
      )}

      {/* Garzón */}
      {order.waiter_name && (
        <div className="text-xs text-slate-500">👤 {order.waiter_name}</div>
      )}

      {/* Botón de acción */}
      {getActionButton()}
    </div>
  );
}
