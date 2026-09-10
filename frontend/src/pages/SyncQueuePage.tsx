import { SyncQueueStats } from "@/components/system/SyncQueueStats";
import { SyncQueueTable } from "@/components/system/SyncQueueTable";
import { useSyncQueueActions, useSyncQueueStats } from "@/hooks/useSyncQueue";
import { RefreshCw, Trash2, Play } from "lucide-react";

export function SyncQueuePage() {
  const { retryAllFailed, deleteAllFailed, triggerSync } = useSyncQueueActions();
  const { data: stats } = useSyncQueueStats();

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2">
            Cola de Sincronización
          </h1>
          <p className="text-slate-400">
            Diagnóstico y gestión de operaciones pendientes
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => triggerSync.mutate()}
            disabled={triggerSync.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50"
          >
            <Play className="w-4 h-4" />
            Sincronizar Ahora
          </button>
          {stats && stats.failed > 0 && (
            <>
              <button
                onClick={() => retryAllFailed.mutate()}
                disabled={retryAllFailed.isPending}
                className="flex items-center gap-2 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors disabled:opacity-50"
              >
                <RefreshCw className="w-4 h-4" />
                Reintentar Fallidos ({stats.failed})
              </button>
              <button
                onClick={() => {
                  if (confirm(`¿Eliminar ${stats.failed} items fallidos?`)) {
                    deleteAllFailed.mutate();
                  }
                }}
                disabled={deleteAllFailed.isPending}
                className="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50"
              >
                <Trash2 className="w-4 h-4" />
                Limpiar Fallidos
              </button>
            </>
          )}
        </div>
      </div>

      {/* Stats */}
      <SyncQueueStats />

      {/* Tabla */}
      <SyncQueueTable />
    </div>
  );
}
