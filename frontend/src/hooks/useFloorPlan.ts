import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { floorPlanService } from "@/services/floorPlanService";
import type {
  SaveFloorPlanPayload,
  CreateDiningZonePayload,
  UpdateDiningZonePayload,
} from "@/types/floorPlan";

const QUERY_KEY = ["floor-plan"] as const;

/**
 * Hook para cargar el floor plan completo.
 * 
 * @param enabled - Si debe ejecutarse la query (default: true)
 * @returns Query con el layout (zonas + mesas con posiciones)
 */
export function useFloorPlan(enabled = true) {
  return useQuery({
    queryKey: QUERY_KEY,
    queryFn: floorPlanService.getFloorPlan,
    staleTime: 30 * 1000, // 30 segundos (el layout no cambia tan rápido)
    refetchOnWindowFocus: false,
    enabled,
  });
}

/**
 * Hook para guardar el floor plan (PUT transaccional).
 * Invalida la query al tener éxito para refrescar el layout.
 */
export function useSaveFloorPlan() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveFloorPlanPayload) =>
      floorPlanService.saveFloorPlan(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para crear una zona nueva.
 * Invalida la query al tener éxito.
 */
export function useCreateDiningZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateDiningZonePayload) =>
      floorPlanService.createZone(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para actualizar una zona existente.
 * Invalida la query al tener éxito.
 */
export function useUpdateDiningZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      uuid,
      payload,
    }: {
      uuid: string;
      payload: UpdateDiningZonePayload;
    }) => floorPlanService.updateZone(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para eliminar una zona.
 * Invalida la query al tener éxito.
 */
export function useDeleteDiningZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (uuid: string) => floorPlanService.deleteZone(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}
