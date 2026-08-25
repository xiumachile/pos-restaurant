import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  recipeService,
  type RawIngredient,
  type ProductRecipe,
  type CreateRecipePayload,
  type UpdateRecipePayload,
} from "@/services/recipeService";

const INGREDIENTS_KEY = ["recipes", "ingredients"];
const RECIPE_KEY = "recipes";

/**
 * Hook para cargar todos los ingredientes disponibles.
 */
export function useIngredients() {
  return useQuery<RawIngredient[], Error>({
    queryKey: INGREDIENTS_KEY,
    queryFn: recipeService.listIngredients,
    staleTime: 60 * 1000,
  });
}

/**
 * Hook para cargar la receta de un producto específico.
 * Retorna null si el producto no tiene receta.
 */
export function useProductRecipe(productUuid: string | null) {
  return useQuery<ProductRecipe | null, Error>({
    queryKey: [RECIPE_KEY, "by-product", productUuid],
    queryFn: () => recipeService.getRecipeByProduct(productUuid!),
    enabled: !!productUuid,
    staleTime: 30 * 1000,
  });
}

export function useCreateRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateRecipePayload) =>
      recipeService.createRecipe(payload),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({
        queryKey: [RECIPE_KEY, "by-product", variables.product_uuid],
      });
    },
  });
}

export function useUpdateRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      recipeUuid,
      payload,
    }: {
      recipeUuid: string;
      payload: UpdateRecipePayload;
    }) => recipeService.updateRecipe(recipeUuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: [RECIPE_KEY],
      });
    },
  });
}

export function useDeleteRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (recipeUuid: string) => recipeService.deleteRecipe(recipeUuid),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: [RECIPE_KEY],
      });
    },
  });
}
