import { useQuery, useQueryClient } from "@tanstack/react-query";
import { tablesService } from "@/services/tablesService";
import { useSyncStore } from "@/store/useSyncStore";
import type { TablesArea } from "@/types/tables";

const TABLES_QUERY_KEY = ["tables"];

/**
 * Hook para obtener la lista de mesas agrupadas por área.
 */
export function useTables() {
  const syncStatus = useSyncStore((s) => s.status);
  const isOffline = syncStatus === "offline";

  return useQuery<TablesArea[], Error>({
    queryKey: TABLES_QUERY_KEY,
    queryFn: tablesService.list,
    refetchInterval: isOffline ? false : 5000,
    staleTime: isOffline ? Infinity : 2000,
    gcTime: 5 * 60 * 1000,
    retry: !isOffline,
    placeholderData: (previousData) => previousData,
  });
}

/**
 * Invalida y fuerza refetch de la query de tables.
 *
 * FIX CRÍTICO OFFLINE:
 * React Query v5 + fetchQuery se cuelga cuando:
 * - staleTime: Infinity
 * - retry: false
 * - query no está activamente montada
 *
 * En offline, BYPASS total de React Query:
 * 1. Llamar tablesService.list() directamente
 * 2. Actualizar cache con setQueryData
 *
 * En online, usar fetchQuery normal.
 */
export function useInvalidateTables() {
  const queryClient = useQueryClient();

  return async () => {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    console.log("[useInvalidateTables] 🔄 Invalidando tables, syncStatus:", syncStatus);

    try {
      if (isOffline) {
        // 🔑 BYPASS OFFLINE: React Query puede colgarse en este caso
        // Llamar directamente a tablesService y actualizar cache manualmente
        console.log("[useInvalidateTables] ✈️ Modo offline: bypass de React Query");
        const data = await tablesService.list();
        queryClient.setQueryData(TABLES_QUERY_KEY, data);
        console.log("[useInvalidateTables] ✅ Cache actualizado manualmente:", data.length, "áreas");
      } else {
        // Online: usar fetchQuery normal
        console.log("[useInvalidateTables] 🌐 Modo online: fetchQuery");
        const data = await queryClient.fetchQuery({
          queryKey: TABLES_QUERY_KEY,
          queryFn: tablesService.list,
          staleTime: 0,
        });
        queryClient.setQueryData(TABLES_QUERY_KEY, data);
        console.log("[useInvalidateTables] ✅ Cache actualizado:", data.length, "áreas");
      }
    } catch (error) {
      console.error("[useInvalidateTables] ❌ Error actualizando tables:", error);
      queryClient.invalidateQueries({ queryKey: TABLES_QUERY_KEY });
    }
  };
}
