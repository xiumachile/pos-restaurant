import type { Order } from "@/types/orders";
import { aggregateOrders } from "@/types/orders";
import { formatPrice } from "@/types/catalog";
import { Receipt } from "lucide-react";

interface ActiveOrderItemsProps {
  orders: Order[];
}

/**
 * Vista agrupada tipo precuenta de los pedidos activos de una mesa.
 * - Agrupa productos iguales de varias órdenes
 * - Muestra cantidad total, precio unitario y subtotal
 * - Totales acumulados al pie
 */
export function ActiveOrderItems({ orders }: ActiveOrderItemsProps) {
  if (orders.length === 0) return null;

  const aggregated = aggregateOrders(orders);

  return (
    <div className="bg-blue-900/10 border border-blue-700/30 rounded-lg p-3 space-y-2">
      {/* Header */}
      <div className="flex items-center gap-2 pb-2 border-b border-blue-700/30">
        <Receipt size={14} className="text-blue-400" />
        <span className="text-xs font-bold text-blue-300 uppercase tracking-wide flex-1">
          Consumo de la mesa
        </span>
      </div>

      {/* Lista de productos agrupados */}
      <div className="space-y-1">
        {aggregated.items.map((item) => (
          <div key={item.key} className="py-1.5 px-1">
            {/* Línea principal: cantidad + nombre + total */}
            <div className="flex items-center gap-2">
              <span className="flex-1 min-w-0">
                <span className="text-white font-semibold mr-1.5">
                  {item.totalQuantity}×
                </span>
                <span className="text-slate-200 truncate">{item.name}</span>
              </span>
              <span className="text-white font-medium flex-shrink-0">
                {formatPrice(item.subtotal)}
              </span>
            </div>

            {/* Notas (si existen) */}
            {item.notes.length > 0 && (
              <div className="ml-5 text-xs text-slate-500 italic truncate">
                📝 {item.notes.join(" · ")}
              </div>
            )}

            {/* Precio unitario */}
            <div className="ml-5 text-xs text-slate-500">
              {formatPrice(item.unitPrice)} c/u
            </div>
          </div>
        ))}
      </div>

      {/* Totales */}
      <div className="pt-2 border-t border-blue-700/30 space-y-1">
        <div className="flex justify-between text-xs">
          <span className="text-slate-400">Subtotal</span>
          <span className="text-slate-200">{formatPrice(aggregated.subtotal)}</span>
        </div>
        <div className="flex justify-between text-xs">
          <span className="text-slate-400">IVA (19%)</span>
          <span className="text-slate-200">{formatPrice(aggregated.tax)}</span>
        </div>
        <div className="flex justify-between text-base font-bold pt-1 border-t border-blue-700/30">
          <span className="text-blue-300">Total consumido</span>
          <span className="text-blue-300">{formatPrice(aggregated.total)}</span>
        </div>
      </div>
    </div>
  );
}
