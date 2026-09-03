import { useQuery, useQueryClient } from "@tanstack/react-query";
import { tablesService } from "@/services/tablesService";
import type { TablesArea } from "@/types/tables";

const TABLES_QUERY_KEY = ["tables"];

/**
 * Hook para obtener la lista de mesas agrupadas por área.
 * Refresca automáticamente cada 30 segundos.
 */
export function useTables() {
  return useQuery<TablesArea[], Error>({
    queryKey: TABLES_QUERY_KEY,
    queryFn: tablesService.list,
    refetchInterval: 5000,
    staleTime: 2000,  // Reducido de 10s a 2s para refetch más agresivo tras pago
  });
}

export function useInvalidateTables() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: TABLES_QUERY_KEY });
}
