import { useState } from "react";
import type { ServedOrder } from "@/types/payments";
import { formatPrice } from "@/types/catalog";
import { PaymentModal } from "./PaymentModal";
import {
  UtensilsCrossed,
  Clock,
  Users,
  DollarSign,
  CreditCard,
  AlertCircle,
} from "lucide-react";

interface ServedOrdersListProps {
  orders: ServedOrder[];
  isCashierSessionOpen: boolean;
}

export function ServedOrdersList({
  orders,
  isCashierSessionOpen,
}: ServedOrdersListProps) {
  const [selectedOrder, setSelectedOrder] = useState<ServedOrder | null>(null);

  const formatTime = (isoString: string) => {
    return new Date(isoString).toLocaleTimeString("es-CL", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const getElapsedMinutes = (isoString: string) => {
    const minutes = Math.floor(
      (Date.now() - new Date(isoString).getTime()) / 60000
    );
    if (minutes < 1) return "< 1 min";
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  return (
    <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <UtensilsCrossed size={24} className="text-orange-400" />
          <div>
            <h2 className="text-xl font-bold">Pedidos por Cobrar</h2>
            <p className="text-sm text-slate-400">
              {orders.length} {orders.length === 1 ? "pedido" : "pedidos"}{" "}
              listos para cobro
            </p>
          </div>
        </div>
      </div>

      {!isCashierSessionOpen && (
        <div className="bg-amber-900/30 border border-amber-700 rounded-lg p-3 mb-4 text-sm text-amber-200 flex items-start gap-2">
          <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
          <span>
            Debes <strong>abrir caja</strong> antes de poder cobrar pedidos.
          </span>
        </div>
      )}

      {orders.length === 0 ? (
        <div className="text-center py-12 text-slate-500">
          <UtensilsCrossed size={48} className="mx-auto mb-3 opacity-30" />
          <p>No hay pedidos listos para cobrar</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {orders.map((order) => {
            const elapsed = getElapsedMinutes(order.served_at);
            const isUrgent =
              (Date.now() - new Date(order.served_at).getTime()) / 60000 > 30;

            return (
              <div
                key={order.uuid}
                className={`bg-slate-800 rounded-lg p-4 border-2 transition-all ${
                  isUrgent
                    ? "border-red-500 animate-pulse"
                    : "border-slate-700"
                }`}
              >
                {/* Header */}
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm font-semibold text-white">
                        {order.order_number}
                      </span>
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full ${
                          isUrgent
                            ? "bg-red-900/40 text-red-300"
                            : "bg-green-900/40 text-green-300"
                        }`}
                      >
                        Servido
                      </span>
                    </div>
                    {order.table && (
                      <div className="flex items-center gap-1 text-xs text-slate-400">
                        <Users size={12} />
                        Mesa {order.table.table_number} ({order.table.area_code})
                      </div>
                    )}
                  </div>
                  <div className="flex items-center gap-1 text-xs text-slate-400">
                    <Clock size={12} />
                    {elapsed}
                  </div>
                </div>

                {/* Items preview */}
                <div className="space-y-0.5 mb-3 max-h-24 overflow-y-auto text-sm">
                  {order.items.slice(0, 3).map((item) => (
                    <div
                      key={item.uuid}
                      className="flex justify-between text-slate-300"
                    >
                      <span className="truncate flex-1">
                        {item.quantity}× {item.name}
                      </span>
                    </div>
                  ))}
                  {order.items.length > 3 && (
                    <div className="text-xs text-slate-500 italic">
                      +{order.items.length - 3} más...
                    </div>
                  )}
                </div>

                {/* Total + botón */}
                <div className="pt-3 border-t border-slate-700 flex items-center justify-between">
                  <div className="flex items-center gap-1 text-slate-400">
                    <DollarSign size={14} />
                    <span className="text-xs">Total:</span>
                  </div>
                  <span className="text-lg font-bold text-orange-400 mr-2">
                    {formatPrice(order.total)}
                  </span>
                </div>

                <button
                  onClick={() => setSelectedOrder(order)}
                  disabled={!isCashierSessionOpen}
                  className="w-full mt-3 px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg font-medium flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                  <CreditCard size={16} />
                  Cobrar
                </button>
              </div>
            );
          })}
        </div>
      )}

      {selectedOrder && (
        <PaymentModal
          order={selectedOrder}
          isOpen={!!selectedOrder}
          onClose={() => setSelectedOrder(null)}
          onSuccess={() => setSelectedOrder(null)}
        />
      )}
    </div>
  );
}
