import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  inventoryService,
  type RawIngredientMovement,
  type MovementFilters,
  type CreateProductionBatchPayload,
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

/**
 * Hook para crear un lote de producción.
 * Invalida movimientos e ingredientes al tener éxito.
 */
export function useCreateProductionBatch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateProductionBatchPayload) =>
      inventoryService.createProductionBatch(payload),
    onSuccess: () => {
      // Invalidar ingredientes (cambió el stock)
      queryClient.invalidateQueries({ queryKey: ["recipes", "ingredients"] });
      // Invalidar todos los movimientos (hubo cambios)
      queryClient.invalidateQueries({ queryKey: [INVENTORY_KEY, "movements"] });
    },
  });
}
