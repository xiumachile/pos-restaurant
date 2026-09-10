import { useState } from "react";
import { useSyncQueueItems, useSyncQueueActions } from "@/hooks/useSyncQueue";
import { SyncBadge } from "./SyncBadge";
import { SyncItemDetailModal } from "./SyncItemDetailModal";
import { RefreshCw, Trash2, Eye } from "lucide-react";
import type { SyncQueueItem } from "@/db/repositories/SyncQueueRepository";

export function SyncQueueTable() {
  const { data: items, isLoading } = useSyncQueueItems();
  const { retryItem, deleteItem } = useSyncQueueActions();
  const [selectedItem, setSelectedItem] = useState<SyncQueueItem | null>(null);

  if (isLoading) {
    return (
      <div className="bg-slate-800 rounded-lg p-8 text-center">
        <RefreshCw className="w-8 h-8 text-slate-400 animate-spin mx-auto mb-2" />
        <p className="text-slate-400">Cargando cola de sincronización...</p>
      </div>
    );
  }

  if (!items || items.length === 0) {
    return (
      <div className="bg-slate-800 rounded-lg p-12 text-center">
        <div className="text-6xl mb-4">🎉</div>
        <h3 className="text-xl font-bold text-white mb-2">Cola vacía</h3>
        <p className="text-slate-400">
          No hay items pendientes de sincronización
        </p>
      </div>
    );
  }

  return (
    <>
      <div className="bg-slate-800 rounded-lg overflow-hidden">
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
                Estado
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">
                Intentos
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
            {items.map((item) => (
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
                  <SyncBadge status={item.sync_status} variant="compact" />
                </td>
                <td className="px-4 py-3">
                  <div className="text-sm text-slate-300">
                    {item.attempts} / {item.max_attempts}
                  </div>
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
            ))}
          </tbody>
        </table>
      </div>

      {selectedItem && (
        <SyncItemDetailModal
          item={selectedItem}
          onClose={() => setSelectedItem(null)}
        />
      )}
    </>
  );
}
