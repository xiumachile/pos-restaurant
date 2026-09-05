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
 * - Offline: desactiva refetch automático (tablaService usa caché + SQLite)
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

export function useInvalidateTables() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: TABLES_QUERY_KEY });
}
