import { useQuery } from "@tanstack/react-query";
import { kitchenService } from "@/services/kitchenService";
import { TableHistoryModal } from "./TableHistoryModal";
import { useState } from "react";
import { Loader2, Search, Users, Clock, DollarSign } from "lucide-react";
import { formatPrice } from "@/types/catalog";

const STATUS_LABELS: Record<string, { label: string; color: string }> = {
  draft: { label: "Borrador", color: "bg-slate-500" },
  confirmed: { label: "Confirmado", color: "bg-blue-500" },
  preparing: { label: "Preparando", color: "bg-amber-500" },
  ready: { label: "Listo", color: "bg-green-500" },
  served: { label: "Servido", color: "bg-emerald-500" },
  paid: { label: "Pagado", color: "bg-emerald-600" },
  closed: { label: "Cerrado", color: "bg-slate-600" },
  cancelled: { label: "Cancelado", color: "bg-red-500" },
};

export function TablesTodayView() {
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedTableUuid, setSelectedTableUuid] = useState<string | null>(null);

  const { data: tables = [], isLoading, error } = useQuery({
    queryKey: ["tables-today"],
    queryFn: kitchenService.getTablesToday,
    refetchInterval: 10000,
    staleTime: 5000,
  });

  // Filtrar por búsqueda
  const filteredTables = tables.filter((t) =>
    t.table_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
    t.area_code.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // Agrupar por área
  const groupedByArea = filteredTables.reduce((acc, table) => {
    const area = table.area_code || "OTHER";
    if (!acc[area]) acc[area] = [];
    acc[area].push(table);
    return acc;
  }, {} as Record<string, typeof tables>);

  const formatTime = (isoString: string | null) => {
    if (!isoString) return "-";
    const date = new Date(isoString);
    return date.toLocaleTimeString("es-CL", { hour: "2-digit", minute: "2-digit" });
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
        <p className="text-red-300">Error al cargar el historial</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header con búsqueda */}
      <div className="flex items-center gap-4">
        <div className="relative flex-1 max-w-md">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar mesa o área..."
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
        <div className="text-sm text-slate-400">
          {filteredTables.length} {filteredTables.length === 1 ? "mesa" : "mesas"} con actividad hoy
        </div>
      </div>

      {/* Grid de mesas agrupadas por área */}
      {Object.entries(groupedByArea).map(([area, areaTables]) => (
        <div key={area}>
          <h3 className="text-lg font-semibold text-white mb-3 flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-orange-400"></span>
            {area} ({areaTables.length})
          </h3>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            {areaTables.map((table) => {
              const statusConfig = STATUS_LABELS[table.last_order_status || "served"];

              return (
                <button
                  key={table.uuid}
                  onClick={() => setSelectedTableUuid(table.uuid)}
                  className="bg-slate-800 rounded-xl p-4 border border-slate-700 hover:border-orange-500/50 hover:bg-slate-750 transition-all text-left"
                >
                  {/* Header: número de mesa + badge de estado */}
                  <div className="flex items-start justify-between mb-3">
                    <div className="text-2xl font-bold text-white">
                      Mesa {table.table_number}
                    </div>
                    <span
                      className={`px-2 py-0.5 rounded text-xs font-medium text-white ${statusConfig.color}`}
                    >
                      {statusConfig.label}
                    </span>
                  </div>

                  {/* Stats */}
                  <div className="space-y-1.5 text-sm">
                    <div className="flex items-center gap-2 text-slate-300">
                      <Users size={14} />
                      <span>{table.capacity} personas</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-300">
                      <Clock size={14} />
                      <span>
                        {table.orders_count} {table.orders_count === 1 ? "pedido" : "pedidos"}
                      </span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-300">
                      <DollarSign size={14} />
                      <span className="font-semibold text-orange-400">
                        {formatPrice(table.total_amount)}
                      </span>
                    </div>
                  </div>

                  {/* Timestamps */}
                  <div className="mt-3 pt-3 border-t border-slate-700 text-xs text-slate-500">
                    {formatTime(table.first_order_at)} → {formatTime(table.last_order_at)}
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      ))}

      {/* Empty state */}
      {filteredTables.length === 0 && (
        <div className="text-center py-12 text-slate-500">
          <p>No hay mesas con actividad hoy</p>
        </div>
      )}

      {/* Modal de historial */}
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
