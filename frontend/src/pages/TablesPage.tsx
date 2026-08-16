import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useTables } from "@/hooks/useTables";
import { useCartStore } from "@/stores/useCartStore";
import { TablesGrid } from "@/components/tables/TablesGrid";
import { TablesStats } from "@/components/tables/TablesStats";
import { flattenAreas, TABLE_STATUS_LABELS } from "@/types/tables";
import type { RestaurantTable, TableStatus, TablesArea } from "@/types/tables";
import { Loader2, AlertCircle, RefreshCw } from "lucide-react";

export function TablesPage() {
  const { data: areas = [], isLoading, error, refetch, isRefetching } = useTables();
  const [statusFilter, setStatusFilter] = useState<TableStatus | "all">("all");
  const carts = useCartStore((s) => s.carts);
  const navigate = useNavigate();

  const handleTableClick = (table: RestaurantTable) => {
    // Navegar a la vista de toma de pedido
    navigate(`/tables/${table.uuid}`);
  };

  const filteredAreas: TablesArea[] =
    statusFilter === "all"
      ? areas
      : areas
          .map((area) => ({
            ...area,
            tables: area.tables.filter((t) => t.status === statusFilter),
          }))
          .filter((area) => area.tables.length > 0);

  const allTables = flattenAreas(areas);

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
        <AlertCircle className="mx-auto text-red-400 mb-3" size={32} />
        <p className="text-red-300 mb-3">Error al cargar las mesas</p>
        <p className="text-sm text-red-400 mb-4">{(error as Error).message}</p>
        <button
          onClick={() => refetch()}
          className="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white"
        >
          Reintentar
        </button>
      </div>
    );
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-bold">Mesas</h1>
          <p className="text-slate-400 mt-1">
            {allTables.length} mesas en {areas.length} áreas · Toca una mesa para tomar pedido
          </p>
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

      {/* Stats */}
      <TablesStats areas={areas} />

      {/* Filtros */}
      <div className="flex flex-wrap gap-2 mb-6">
        <button
          onClick={() => setStatusFilter("all")}
          className={`px-4 py-2 rounded-lg font-medium transition-colors ${
            statusFilter === "all"
              ? "bg-orange-500 text-white"
              : "bg-slate-800 text-slate-300 hover:bg-slate-700"
          }`}
        >
          Todas ({allTables.length})
        </button>
        {(Object.keys(TABLE_STATUS_LABELS) as TableStatus[]).map((status) => {
          const count = allTables.filter((t) => t.status === status).length;
          return (
            <button
              key={status}
              onClick={() => setStatusFilter(status)}
              className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                statusFilter === status
                  ? "bg-orange-500 text-white"
                  : "bg-slate-800 text-slate-300 hover:bg-slate-700"
              }`}
            >
              {TABLE_STATUS_LABELS[status]} ({count})
            </button>
          );
        })}
      </div>

      {/* Grids por área */}
      {filteredAreas.length === 0 ? (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-8 text-center">
          <p className="text-slate-400">
            No hay mesas{" "}
            {statusFilter !== "all"
              ? `con estado "${TABLE_STATUS_LABELS[statusFilter]}"`
              : ""}
          </p>
        </div>
      ) : (
        filteredAreas.map((area) => (
          <TablesGridWithCartBadge
            key={area.area_code}
            area={area}
            carts={carts}
            onTableClick={handleTableClick}
          />
        ))
      )}
    </div>
  );
}

/**
 * Wrapper de TablesGrid que muestra badge de items en mesas con carrito activo.
 */
import { TablesGrid as BaseTablesGrid } from "@/components/tables/TablesGrid";
import type { TableCart } from "@/types/cart";

function TablesGridWithCartBadge({
  area,
  carts,
  onTableClick,
}: {
  area: TablesArea;
  carts: Record<string, TableCart>;
  onTableClick: (table: RestaurantTable) => void;
}) {
  return <BaseTablesGrid area={area} onTableClick={onTableClick} cartItems={carts} />;
}
