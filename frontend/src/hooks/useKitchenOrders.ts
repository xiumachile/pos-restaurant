import { useQuery, useQueryClient, useMutation } from "@tanstack/react-query";
import { kitchenService } from "@/services/kitchenService";
import type { KitchenZone, KitchenStats } from "@/types/kitchen";

const QUEUE_KEY = ["kitchen", "queue"];
const STATS_KEY = ["kitchen", "stats"];

/**
 * Hook para obtener la cola de cocina con polling cada 5s.
 */
export function useKitchenQueue() {
  return useQuery<KitchenZone[], Error>({
    queryKey: QUEUE_KEY,
    queryFn: kitchenService.getQueue,
    refetchInterval: 5000, // 5 segundos
    staleTime: 2000,
  });
}

/**
 * Hook para obtener estadísticas de cocina.
 */
export function useKitchenStats() {
  return useQuery<KitchenStats, Error>({
    queryKey: STATS_KEY,
    queryFn: kitchenService.getStats,
    refetchInterval: 10000, // 10 segundos
    staleTime: 5000,
  });
}

/**
 * Hook para invalidar la cola de cocina (forzar refetch).
 */
export function useInvalidateKitchen() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: QUEUE_KEY });
    queryClient.invalidateQueries({ queryKey: STATS_KEY });
  };
}

/**
 * Hook para transiciones de estado (prepare, ready, serve).
 */
export function useKitchenTransition() {
  const queryClient = useQueryClient();

  const prepare = useMutation({
    mutationFn: kitchenService.prepare,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUEUE_KEY });
      queryClient.invalidateQueries({ queryKey: STATS_KEY });
    },
  });

  const ready = useMutation({
    mutationFn: kitchenService.ready,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUEUE_KEY });
      queryClient.invalidateQueries({ queryKey: STATS_KEY });
    },
  });

  const serve = useMutation({
    mutationFn: kitchenService.serve,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUEUE_KEY });
      queryClient.invalidateQueries({ queryKey: STATS_KEY });
    },
  });

  return { prepare, ready, serve };
}
