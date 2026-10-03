import { useQuery } from "@tanstack/react-query";
import {
  inventoryService,
  type RawIngredientMovement,
  type MovementFilters,
} from "@/services/inventoryService";

const INVENTORY_KEY = "inventory";

/**
 * Hook para cargar el historial de movimientos de un insumo.
 * Se invalida cuando el ingrediente cambia o los filtros cambian.
 */
export function useIngredientMovements(
  ingredientUuid: string | null,
  filters: MovementFilters = {}
) {
  return useQuery<RawIngredientMovement[], Error>({
    queryKey: [
      INVENTORY_KEY,
      "movements",
      ingredientUuid,
      filters.type ?? "all",
      filters.limit ?? 100,
    ],
    queryFn: () =>
      inventoryService.listMovements(ingredientUuid!, {
        type: filters.type,
        limit: filters.limit,
      }),
    enabled: !!ingredientUuid,
    staleTime: 30 * 1000,
  });
}
