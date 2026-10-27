// src/hooks/useFloorPlan.ts
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { floorPlanService } from "@/services/floorPlanService";
import type { SaveFloorPlanPayload, CreateDiningZonePayload, UpdateDiningZonePayload } from "@/types/floorPlan";

const QUERY_KEY = ["floor-plan"] as const;

/**
 * Hook para cargar el floor plan completo.
 * 
 * @param enabled - Si debe ejecutarse la query (default: true)
 * @param refetchInterval - Intervalo en ms para polling (ej: 15000 para modo operacional, 0 para edición)
 * @returns Query con el layout (plan, zones, objects, available_tables)
 */
export function useFloorPlan(enabled = true, refetchInterval: number = 0) {
  return useQuery({
    queryKey: QUERY_KEY,
    queryFn: () => floorPlanService.getFloorPlan(),
    staleTime: refetchInterval > 0 ? 5 * 1000 : 30 * 1000, // 5s si hay polling, 30s si no
    refetchOnWindowFocus: false,
    refetchInterval: refetchInterval > 0 ? refetchInterval : false, // 👈 Polling ligero fuera de edición
    enabled,
  });
}

/**
 * Hook para guardar el floor plan (PUT transaccional).
 * Invalida la query al tener éxito para refrescar el layout con la nueva versión.
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
 */
export function useUpdateDiningZone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: UpdateDiningZonePayload }) =>
      floorPlanService.updateZone(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para eliminar una zona.
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
