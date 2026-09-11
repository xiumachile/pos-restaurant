import { useState, useMemo } from "react";
import { useSyncQueueItems, useSyncQueueActions } from "@/hooks/useSyncQueue";
import { SyncBadge } from "./SyncBadge";
import { SyncItemDetailModal } from "./SyncItemDetailModal";
import { RefreshCw, Trash2, Eye, Search, Filter } from "lucide-react";
import type { SyncQueueItem } from "@/db/repositories/SyncQueueRepository";
import { 
  enrichSyncQueueItem, 
  formatPaymentUuid, 
  formatCashSession 
} from "@/services/sync/SyncQueueEnrichment";

type StatusFilter = "all" | "pending" | "syncing" | "synced" | "failed";

export function SyncQueueTable() {
  const { data: items, isLoading } = useSyncQueueItems();
  const { retryItem, deleteItem } = useSyncQueueActions();
  const [selectedItem, setSelectedItem] = useState<SyncQueueItem | null>(null);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [searchQuery, setSearchQuery] = useState("");

  // Filtrar items por estado + búsqueda
  const filteredItems = useMemo(() => {
    if (!items) return [];

    let result = items;

    // Filtrar por estado
    if (statusFilter !== "all") {
      result = result.filter((item) => item.sync_status === statusFilter);
    }

    // Buscar en ID, payment_uuid, error
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      result = result.filter((item) => {
        const enriched = enrichSyncQueueItem(item.payload, item.entity_type);
        return (
          item.id.toLowerCase().includes(query) ||
          item.entity_local_uuid.toLowerCase().includes(query) ||
          (enriched.payment_uuid || "").toLowerCase().includes(query) ||
          (enriched.idempotency_key || "").toLowerCase().includes(query) ||
          (enriched.terminal_id || "").toLowerCase().includes(query) ||
          (enriched.cash_session_uuid || "").toLowerCase().includes(query) ||
          (item.last_error || "").toLowerCase().includes(query)
        );
      });
    }

    return result;
  }, [items, statusFilter, searchQuery]);

  if (isLoading) {
    return (
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <RefreshCw className="w-8 h-8 text-slate-400 animate-spin mx-auto mb-2" />
        <p className="text-slate-400">Cargando cola de sincronización...</p>
      </div>
    );
  }

  const filterButtons: { value: StatusFilter; label: string; color: string }[] = [
    { value: "all", label: "Todos", color: "bg-slate-700" },
    { value: "pending", label: "Pendientes", color: "bg-yellow-500/20 text-yellow-400" },
    { value: "syncing", label: "Sincronizando", color: "bg-blue-500/20 text-blue-400" },
    { value: "synced", label: "Sincronizados", color: "bg-green-500/20 text-green-400" },
    { value: "failed", label: "Fallidos", color: "bg-red-500/20 text-red-400" },
  ];

  return (
    <>
      {/* Barra de filtros y búsqueda */}
      <div className="bg-slate-800 rounded-lg p-4 mb-4 space-y-3">
        {/* Filtros por estado */}
        <div className="flex flex-wrap items-center gap-2">
          <Filter className="w-4 h-4 text-slate-400" />
          {filterButtons.map((btn) => (
            <button
              key={btn.value}
              onClick={() => setStatusFilter(btn.value)}
              className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                statusFilter === btn.value
                  ? `${btn.color} border border-current`
                  : "bg-slate-700 text-slate-300 hover:bg-slate-600"
              }`}
            >
              {btn.label}
            </button>
          ))}
          <span className="ml-auto text-xs text-slate-500">
            {filteredItems.length} de {items?.length || 0} items
          </span>
        </div>

        {/* Búsqueda */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar por ID, payment, terminal, error..."
            className="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
          />
        </div>
      </div>

      {/* Tabla */}
      {filteredItems.length === 0 ? (
        <div className="bg-slate-800 rounded-lg p-12 text-center">
          <div className="text-6xl mb-4">
            {items && items.length > 0 ? "🔍" : "🎉"}
          </div>
          <h3 className="text-xl font-bold text-white mb-2">
            {items && items.length > 0 ? "Sin resultados" : "Cola vacía"}
          </h3>
          <p className="text-slate-400">
            {items && items.length > 0
              ? "Prueba ajustando los filtros o la búsqueda"
              : "No hay items pendientes de sincronización"}
          </p>
        </div>
      ) : (
        <div className="bg-slate-800 rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-900 border-b border-slate-700">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Entidad
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Acción
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Payment / Sesión
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Terminal
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Estado
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Intentos
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Último Error
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                    Creado
                  </th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-700">
                {filteredItems.map((item) => {
                  const enriched = enrichSyncQueueItem(item.payload, item.entity_type);
                  return (
                    <tr key={item.id} className="hover:bg-slate-700/50 transition-colors">
                      <td className="px-4 py-3">
                        <div className="text-sm font-medium text-white">
                          {item.entity_type}
                        </div>
                        <div className="text-xs text-slate-400 font-mono">
                          {item.entity_local_uuid.substring(0, 8)}...
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-1 text-xs font-medium bg-slate-700 rounded">
                          {item.action}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {enriched.payment_uuid ? (
                          <div>
                            <div className="text-xs text-slate-400">Payment</div>
                            <div className="text-xs text-white font-mono">
                              {formatPaymentUuid(enriched.payment_uuid)}
                            </div>
                          </div>
                        ) : enriched.cash_session_uuid ? (
                          <div>
                            <div className="text-xs text-slate-400">Cash Session</div>
                            <div className="text-xs text-white font-mono">
                              {formatCashSession(enriched.cash_session_uuid)}
                            </div>
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">-</span>
                        )}
                        {enriched.idempotency_key && (
                          <div className="mt-1">
                            <div className="text-[10px] text-slate-500">Idempotency</div>
                            <div className="text-[10px] text-slate-400 font-mono">
                              {enriched.idempotency_key.substring(0, 8)}...
                            </div>
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        {enriched.terminal_id ? (
                          <div className="text-xs text-white font-mono">
                            {enriched.terminal_id}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <SyncBadge status={item.sync_status} variant="compact" />
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-sm text-slate-300">
                          {item.attempts} / {item.max_attempts}
                        </div>
                      </td>
                      <td className="px-4 py-3 max-w-xs">
                        {item.last_error ? (
                          <div
                            className="text-xs text-red-300 truncate font-mono"
                            title={item.last_error}
                          >
                            {item.last_error}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-xs text-slate-400">
                          {new Date(item.created_at).toLocaleString("es-CL", {
                            day: "2-digit",
                            month: "2-digit",
                            hour: "2-digit",
                            minute: "2-digit",
                          })}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => setSelectedItem(item)}
                            className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                            title="Ver detalles"
                            aria-label="Ver detalles del item"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {item.sync_status === "failed" && (
                            <>
                              <button
                                onClick={() => retryItem.mutate(item.id)}
                                disabled={retryItem.isPending}
                                className="p-1.5 text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 rounded transition-colors disabled:opacity-50"
                                title="Reintentar"
                                aria-label="Reintentar sincronización"
                              >
                                <RefreshCw className="w-4 h-4" />
                              </button>
                              <button
                                onClick={() => deleteItem.mutate(item.id)}
                                disabled={deleteItem.isPending}
                                className="p-1.5 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded transition-colors disabled:opacity-50"
                                title="Eliminar"
                                aria-label="Eliminar item de la cola"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {selectedItem && (
        <SyncItemDetailModal
          item={selectedItem}
          onClose={() => setSelectedItem(null)}
        />
      )}
    </>
  );
}
