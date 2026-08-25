import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { comboService, type SubstitutionPolicy, type SubstitutionMode } from "@/services/comboService";

const SUBSTITUTIONS_KEY = "combo-substitutions";

/**
 * Hook para obtener las políticas de sustitución de un combo.
 */
export function useSubstitutionPolicies(menuItemUuid: string | null) {
  return useQuery<SubstitutionPolicy[], Error>({
    queryKey: [SUBSTITUTIONS_KEY, menuItemUuid],
    queryFn: () => comboService.getSubstitutionPolicies(menuItemUuid!),
    enabled: !!menuItemUuid,
    staleTime: 30 * 1000,
  });
}

interface UpdatePolicyParams {
  menuItemUuid: string;
  productUuid: string;
  mode: SubstitutionMode;
  allowed_category_id?: string | null;
  max_price_delta?: number | null;
  requires_authorization?: boolean;
}

/**
 * Mutación para actualizar la política de sustitución.
 */
export function useUpdateSubstitutionPolicy() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: UpdatePolicyParams) =>
      comboService.updateSubstitutionPolicy(
        params.menuItemUuid,
        params.productUuid,
        {
          mode: params.mode,
          allowed_category_id: params.allowed_category_id,
          max_price_delta: params.max_price_delta,
          requires_authorization: params.requires_authorization,
        }
      ),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({
        queryKey: [SUBSTITUTIONS_KEY, variables.menuItemUuid],
      });
    },
  });
}

/**
 * Mutación para eliminar override de sucursal.
 */
export function useDeleteSubstitutionPolicy() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      menuItemUuid,
      productUuid,
    }: {
      menuItemUuid: string;
      productUuid: string;
    }) => comboService.deleteSubstitutionPolicy(menuItemUuid, productUuid),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({
        queryKey: [SUBSTITUTIONS_KEY, variables.menuItemUuid],
      });
    },
  });
}
