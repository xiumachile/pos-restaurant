import { useQuery } from "@tanstack/react-query";
import { kitchenService } from "@/services/kitchenService";
import { X, Clock, CheckCircle2, ChefHat, UtensilsCrossed, DollarSign } from "lucide-react";
import { formatPrice } from "@/types/catalog";

interface TableHistoryModalProps {
  tableUuid: string;
  isOpen: boolean;
  onClose: () => void;
}

const STATUS_CONFIG = {
  draft: { label: "Borrador", color: "text-slate-400", icon: Clock },
  confirmed: { label: "Confirmado", color: "text-blue-400", icon: CheckCircle2 },
  preparing: { label: "En preparación", color: "text-amber-400", icon: ChefHat },
  ready: { label: "Listo", color: "text-green-400", icon: CheckCircle2 },
  served: { label: "Servido", color: "text-green-500", icon: UtensilsCrossed },
  paid: { label: "Pagado", color: "text-emerald-500", icon: DollarSign },
  closed: { label: "Cerrado", color: "text-slate-500", icon: CheckCircle2 },
  cancelled: { label: "Cancelado", color: "text-red-400", icon: X },
};

export function TableHistoryModal({ tableUuid, isOpen, onClose }: TableHistoryModalProps) {
  const { data, isLoading, error } = useQuery({
    queryKey: ["table-history", tableUuid],
    queryFn: () => kitchenService.getTableHistory(tableUuid),
    enabled: isOpen && !!tableUuid,
    staleTime: 10000,
  });

  if (!isOpen) return null;

  const formatTime = (isoString: string | null) => {
    if (!isoString) return "-";
    const date = new Date(isoString);
    return date.toLocaleTimeString("es-CL", { hour: "2-digit", minute: "2-digit" });
  };

  return (
    <>
      {/* Overlay */}
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />

      {/* Modal */}
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-6 border-b border-slate-700">
            <div>
              <h2 className="text-2xl font-bold text-white">
                Historial · Mesa {data?.table.table_number}
              </h2>
              <p className="text-sm text-slate-400 mt-1">
                {data?.table.area_code} · Capacidad: {data?.table.capacity} personas
              </p>
            </div>
            <button
              onClick={onClose}
              className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
            >
              <X size={24} />
            </button>
          </div>

          {/* Content */}
          <div className="flex-1 overflow-y-auto p-6">
            {isLoading ? (
              <div className="text-center py-12">
                <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
                <p className="text-slate-400 mt-4">Cargando historial...</p>
              </div>
            ) : error ? (
              <div className="text-center py-12 text-red-400">
                <p>Error al cargar el historial</p>
              </div>
            ) : !data || data.orders.length === 0 ? (
              <div className="text-center py-12 text-slate-500">
                <p>No hay pedidos para esta mesa hoy</p>
              </div>
            ) : (
              <>
                {/* Summary */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                  <div className="bg-slate-800 rounded-lg p-4">
                    <div className="text-sm text-slate-400">Total Pedidos</div>
                    <div className="text-2xl font-bold text-white mt-1">
                      {data.summary.total_orders}
                    </div>
                  </div>
                  <div className="bg-slate-800 rounded-lg p-4">
                    <div className="text-sm text-slate-400">Total Items</div>
                    <div className="text-2xl font-bold text-white mt-1">
                      {data.summary.total_items}
                    </div>
                  </div>
                  <div className="bg-slate-800 rounded-lg p-4">
                    <div className="text-sm text-slate-400">Total Monto</div>
                    <div className="text-2xl font-bold text-orange-400 mt-1">
                      {formatPrice(data.summary.total_amount)}
                    </div>
                  </div>
                </div>

                {/* Timeline de pedidos */}
                <div className="space-y-4">
                  {data.orders.map((order, index) => {
                    const statusConfig = STATUS_CONFIG[order.status as keyof typeof STATUS_CONFIG];
                    const Icon = statusConfig.icon;

                    return (
                      <div
                        key={order.uuid}
                        className="bg-slate-800 rounded-lg p-4 border border-slate-700"
                      >
                        {/* Header del pedido */}
                        <div className="flex items-start justify-between mb-3">
                          <div className="flex items-center gap-3">
                            <div className="flex items-center justify-center w-10 h-10 rounded-full bg-slate-700 text-white font-bold">
                              #{index + 1}
                            </div>
                            <div>
                              <div className="flex items-center gap-2">
                                <span className="font-semibold text-white">
                                  {order.order_number}
                                </span>
                                <span className={`flex items-center gap-1 text-sm ${statusConfig.color}`}>
                                  <Icon size={14} />
                                  {statusConfig.label}
                                </span>
                              </div>
                              <div className="text-xs text-slate-400 mt-1">
                                {formatTime(order.confirmed_at)}
                                {order.waiter_name && ` · ${order.waiter_name}`}
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Items */}
                        <div className="ml-13 space-y-1">
                          {order.items.map((item) => (
                            <div
                              key={item.uuid}
                              className="flex items-start gap-2 text-sm"
                            >
                              <span className="font-semibold text-white flex-shrink-0">
                                {item.quantity}×
                              </span>
                              <span className="text-slate-200 flex-1">{item.name}</span>
                              {item.notes && (
                                <span className="text-xs text-slate-500 italic">
                                  ({item.notes})
                                </span>
                              )}
                            </div>
                          ))}
                        </div>

                        {/* Notas generales del pedido */}
                        {order.notes && (
                          <div className="ml-13 mt-2 text-xs text-slate-400 italic bg-slate-900/50 rounded p-2">
                            📝 {order.notes}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
