import { useQuery } from "@tanstack/react-query";
import { useTranslation } from 'react-i18next';
import { kitchenService } from "@/services/kitchenService";
import { TableHistoryModal } from "./TableHistoryModal";
import { useState } from "react";
import { Loader2, Search, Users, Clock, DollarSign } from "lucide-react";
import { formatPrice } from "@/types/catalog";

export function TablesTodayView() {
  const { t } = useTranslation();
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

  const getStatusConfig = (status: string) => {
    const map: Record<string, { labelKey: string; color: string }> = {
      draft: { labelKey: "kitchen.draft", color: "bg-slate-500" },
      confirmed: { labelKey: "kitchen.confirmed", color: "bg-blue-500" },
      preparing: { labelKey: "kitchen.preparing", color: "bg-amber-500" },
      ready: { labelKey: "kitchen.ready", color: "bg-green-500" },
      served: { labelKey: "kitchen.served", color: "bg-emerald-500" },
      paid: { labelKey: "kitchen.paid", color: "bg-emerald-600" },
      closed: { labelKey: "kitchen.closed", color: "bg-slate-600" },
      cancelled: { labelKey: "kitchen.cancelled", color: "bg-red-500" },
    };
    return map[status] || map["served"];
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
        <p className="text-red-300">{t("kitchen.error_loading_history")}</p>
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
            placeholder={t("kitchen.search_table_area")}
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
        <div className="text-sm text-slate-400">
          {filteredTables.length} {filteredTables.length === 1 ? t("tables.table") : t("tables.tables")} {t("kitchen.with_activity_today")}
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
              const statusConfig = getStatusConfig(table.last_order_status || "served");

              return (
                <button
                  key={table.uuid}
                  onClick={() => setSelectedTableUuid(table.uuid)}
                  className="bg-slate-800 rounded-xl p-4 border border-slate-700 hover:border-orange-500/50 hover:bg-slate-750 transition-all text-left"
                >
                  {/* Header: número de mesa + badge de estado */}
                  <div className="flex items-start justify-between mb-3">
                    <div className="text-2xl font-bold text-white">
                      {t("tables.table")} {table.table_number}
                    </div>
                    <span
                      className={`px-2 py-0.5 rounded text-xs font-medium text-white ${statusConfig.color}`}
                    >
                      {t(statusConfig.labelKey)}
                    </span>
                  </div>

                  {/* Stats */}
                  <div className="space-y-1.5 text-sm">
                    <div className="flex items-center gap-2 text-slate-300">
                      <Users size={14} />
                      <span>{table.capacity} {t("tables.capacity")}</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-300">
                      <Clock size={14} />
                      <span>
                        {table.orders_count} {table.orders_count === 1 ? t("orders.order") : t("orders.orders")}
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
                  <div className="mt-3 pt-3 border-t border-slate-700 text-xs text-slate-400">
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
        <div className="text-center py-12 text-slate-400">
          <p>{t("kitchen.no_tables_activity_today")}</p>
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
