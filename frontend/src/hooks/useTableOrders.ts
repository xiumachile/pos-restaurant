import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ordersService } from "@/services/ordersService";
import type { Order } from "@/types/orders";

const KEY = (tableUuid: string) => ["table-orders", tableUuid];

/**
 * Hook para obtener pedidos activos de una mesa con polling cada 10s.
 */
export function useTableOrders(tableUuid: string | null) {
  return useQuery<Order[], Error>({
    queryKey: tableUuid ? KEY(tableUuid) : ["table-orders", "disabled"],
    queryFn: () => ordersService.listTableOrders(tableUuid!),
    enabled: !!tableUuid,
    refetchInterval: 10000,
    staleTime: 5000,
  });
}

export function useInvalidateTableOrders(tableUuid: string) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: KEY(tableUuid) });
}
