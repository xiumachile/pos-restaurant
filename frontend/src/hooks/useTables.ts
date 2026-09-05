import { useQuery, useQueryClient } from "@tanstack/react-query";
import { tablesService } from "@/services/tablesService";
import { useSyncStore } from "@/store/useSyncStore";
import type { TablesArea } from "@/types/tables";

const TABLES_QUERY_KEY = ["tables"];

/**
 * Hook para obtener la lista de mesas agrupadas por área.
 * 
 * COMPORTAMIENTO:
 * - Online: refetch cada 5s para mantener datos frescos
 * - Offline: desactiva refetch automático (tableService usa caché + SQLite)
 * - Refetch manual siempre disponible (botón de recargar)
 */
export function useTables() {
  const syncStatus = useSyncStore((s) => s.status);
  const isOffline = syncStatus === "offline";

  return useQuery<TablesArea[], Error>({
    queryKey: TABLES_QUERY_KEY,
    queryFn: tablesService.list,
    // En offline, no hacer refetch automático (la data viene de caché/SQLite)
    refetchInterval: isOffline ? false : 5000,
    // En offline, considerar datos siempre frescos (ya son locales)
    staleTime: isOffline ? Infinity : 2000,
    // Mantener datos en caché 5 minutos aunque el componente se desmonte
    gcTime: 5 * 60 * 1000,
    // No reintentar en offline (tablesService ya maneja el fallback)
    retry: !isOffline,
    // No bloquear render inicial (mostrar caché si existe)
    placeholderData: (previousData) => previousData,
  });
}

/**
 * Invalida y fuerza refetch de la query de tables.
 * 
 * IMPORTANTE: Usamos refetchQueries en lugar de invalidateQueries porque:
 * - invalidateQueries solo marca como stale
 * - Con staleTime: Infinity en offline, el refetch no ocurre automáticamente
 * - refetchQueries fuerza el refetch inmediato de queries activas
 */
export function useInvalidateTables() {
  const queryClient = useQueryClient();

  return async () => {
    console.log("[useInvalidateTables] 🔄 Forzando fetchQuery de tables");

    try {
      // fetchQuery fuerza la ejecución de tablesService.list()
      // aunque la query esté inactiva/desmontada.
      const data = await queryClient.fetchQuery({
        queryKey: TABLES_QUERY_KEY,
        queryFn: tablesService.list,
        staleTime: 0,
      });

      // Asegurar que el cache queda actualizado antes de navegar a Mesas.
      queryClient.setQueryData(TABLES_QUERY_KEY, data);

      console.log("[useInvalidateTables] ✅ Cache de tables actualizado:", data.length, "áreas");
    } catch (error) {
      console.error("[useInvalidateTables] ❌ Error actualizando tables:", error);

      // Fallback: al menos marcar como inválida para que refetchee al montar.
      queryClient.invalidateQueries({ queryKey: TABLES_QUERY_KEY });
    }
  };
}
