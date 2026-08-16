import type { Order } from "@/types/orders";
import { formatPrice } from "@/types/catalog";
import { CheckCircle2, Clock, ChefHat, UtensilsCrossed } from "lucide-react";

interface ActiveOrderItemsProps {
  orders: Order[];
}

/**
 * Muestra los items de pedidos confirmados/activos de la mesa.
 * Solo lectura (se manejan desde cocina/caja).
 */
export function ActiveOrderItems({ orders }: ActiveOrderItemsProps) {
  if (orders.length === 0) return null;

  const statusConfig = {
    draft: {
      label: "Borrador",
      icon: Clock,
      color: "text-slate-400",
      bg: "bg-slate-800/50",
      border: "border-slate-700",
    },
    confirmed: {
      label: "Confirmado",
      icon: CheckCircle2,
      color: "text-blue-400",
      bg: "bg-blue-900/20",
      border: "border-blue-700/40",
    },
    preparing: {
      label: "En preparación",
      icon: ChefHat,
      color: "text-amber-400",
      bg: "bg-amber-900/20",
      border: "border-amber-700/40",
    },
    ready: {
      label: "Listo para servir",
      icon: UtensilsCrossed,
      color: "text-green-400",
      bg: "bg-green-900/20",
      border: "border-green-700/40",
    },
    served: {
      label: "Servido",
      icon: CheckCircle2,
      color: "text-slate-400",
      bg: "bg-slate-800/50",
      border: "border-slate-700",
    },
  } as const;

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2 px-1 text-xs font-semibold text-slate-400 uppercase tracking-wide">
        <Clock size={12} />
        Pedidos en curso ({orders.length})
      </div>

      {orders.map((order) => {
        const cfg = statusConfig[order.status as keyof typeof statusConfig] || statusConfig.confirmed;
        const Icon = cfg.icon;

        return (
          <div
            key={order.uuid}
            className={`rounded-lg p-3 border ${cfg.bg} ${cfg.border}`}
          >
            {/* Header del order */}
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                <Icon size={14} className={cfg.color} />
                <span className="text-xs font-semibold text-white">
                  {order.order_number}
                </span>
                <span className={`text-xs font-medium ${cfg.color}`}>{cfg.label}</span>
              </div>
              {order.waiter && (
                <span className="text-xs text-slate-500">
                  👤 {order.waiter.name}
                </span>
              )}
            </div>

            {/* Items */}
            {order.items.length > 0 ? (
              <div className="space-y-1">
                {order.items.map((item) => (
                  <div
                    key={item.uuid}
                    className="flex items-center justify-between text-sm"
                  >
                    <span className="text-slate-200 flex-1 min-w-0 truncate">
                      <span className="text-slate-400 mr-1">{item.quantity}×</span>
                      {item.name}
                      {item.notes && (
                        <span className="text-slate-500 italic ml-1">
                          ({item.notes})
                        </span>
                      )}
                    </span>
                    <span className="text-slate-300 font-medium ml-2 flex-shrink-0">
                      {formatPrice(item.subtotal)}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-500 italic">Sin items</p>
            )}

            {/* Total del order */}
            <div className="mt-2 pt-2 border-t border-slate-700/50 flex justify-between text-sm">
              <span className="text-slate-400">Total pedido</span>
              <span className={`font-bold ${cfg.color}`}>
                {formatPrice(order.total)}
              </span>
            </div>
          </div>
        );
      })}
    </div>
  );
}
