import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { SyncQueueRepository, type SyncQueueItem } from "@/db/repositories/SyncQueueRepository";
import { syncEngine } from "@/services/sync/SyncEngine";

const QUERY_KEY = "sync-queue";
const STATS_KEY = "sync-queue-stats";

/**
 * Hook para obtener todos los items de la cola de sincronización.
 * Polling cada 2 segundos para mantener actualizado.
 */
export function useSyncQueueItems(limit: number = 100) {
  return useQuery({
    queryKey: [QUERY_KEY, limit],
    queryFn: () => SyncQueueRepository.getAll(limit),
    refetchInterval: 2000, // Polling cada 2s
    staleTime: 1000, // Considerar stale después de 1s
  });
}

/**
 * Hook para obtener estadísticas de la cola (conteos por status).
 */
export function useSyncQueueStats() {
  return useQuery({
    queryKey: [STATS_KEY],
    queryFn: () => SyncQueueRepository.countByStatus(),
    refetchInterval: 2000,
    staleTime: 1000,
  });
}

/**
 * Hook con todas las mutaciones disponibles para la cola.
 */
export function useSyncQueueActions() {
  const queryClient = useQueryClient();

  // Invalidar queries después de cada mutación
  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: [QUERY_KEY] });
    queryClient.invalidateQueries({ queryKey: [STATS_KEY] });
  };

  // Reintentar un item específico
  const retryItem = useMutation({
    mutationFn: async (id: string) => {
      await SyncQueueRepository.resetToPending(id);
      // Disparar sync inmediatamente
      await syncEngine.processBatch();
    },
    onSuccess: invalidate,
  });

  // Reintentar todos los fallidos
  const retryAllFailed = useMutation({
    mutationFn: async () => {
      const count = await SyncQueueRepository.resetAllFailed();
      if (count > 0) {
        await syncEngine.processBatch();
      }
      return count;
    },
    onSuccess: invalidate,
  });

  // Eliminar un item específico
  const deleteItem = useMutation({
    mutationFn: (id: string) => SyncQueueRepository.deleteById(id),
    onSuccess: invalidate,
  });

  // Eliminar todos los fallidos
  const deleteAllFailed = useMutation({
    mutationFn: () => SyncQueueRepository.deleteAllFailed(),
    onSuccess: invalidate,
  });

  // Disparar sync manual
  const triggerSync = useMutation({
    mutationFn: () => syncEngine.processBatch(),
    onSuccess: invalidate,
  });

  return {
    retryItem,
    retryAllFailed,
    deleteItem,
    deleteAllFailed,
    triggerSync,
  };
}
