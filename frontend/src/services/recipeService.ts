import apiClient from "./apiClient";

/* ─── Tipos ─── */

export interface RawIngredient {
  uuid: string;
  sku: string;
  name_translations: Record<string, string>;
  dimension_type: string | null;
  base_unit: string | null;
  current_stock_base: number;
  minimum_stock_base: number;
  cost_per_base_unit: number;
  total_stock_value: number;
  is_active: boolean;
  is_low_stock: boolean;
  created_at: string;
}

export interface RecipeItem {
  uuid: string;
  ingredient_uuid: string;
  ingredient_sku: string;
  ingredient_name: string;
  quantity_base_unit: number;
  waste_percentage: number;
  effective_discount_base_quantity: number;
  calculated_item_cost: number;
}

export interface ProductRecipe {
  uuid: string;
  product_uuid: string;
  product_name: string;
  product_base_price: number;
  description: string | null;
  yield_servings: number;
  total_recipe_cost: number;
  food_cost_percentage: number;
  gross_margin: number;
  items: RecipeItem[];
  created_at: string;
  updated_at: string;
}

export interface RecipeIngredientPayload {
  raw_ingredient_id: number;
  quantity_base_unit: number;
  waste_percentage?: number;
}

export interface CreateRecipePayload {
  product_uuid: string;
  description?: string | null;
  yield_servings?: number;
  ingredients: RecipeIngredientPayload[];
}

export interface UpdateRecipePayload {
  description?: string | null;
  yield_servings?: number;
  ingredients: RecipeIngredientPayload[];
}

/* ─── Respuestas ─── */

interface SuccessResponse<T> {
  success?: boolean;
  data: T;
}

interface ErrorResponse {
  error: string;
  message: string;
}

/* ─── Servicio ─── */

export const recipeService = {
  /**
   * Lista todos los ingredientes activos (materias primas).
   */
  async listIngredients(): Promise<RawIngredient[]> {
    const response = await apiClient.get<{ data: RawIngredient[] }>(
      "/recipes/ingredients"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
  },

  /**
   * Obtiene la receta de un producto (si existe).
   * Retorna null si el producto no tiene receta (404).
   */
  async getRecipeByProduct(productUuid: string): Promise<ProductRecipe | null> {
    try {
      const response = await apiClient.get<ProductRecipe>(
        `/recipes/product/${productUuid}`
      );
      const data = response.data as any;
      // El recurso puede venir envuelto en {data: ...} o directo
      if (data?.uuid) return data as ProductRecipe;
      if (data?.data?.uuid) return data.data as ProductRecipe;
      return null;
    } catch (err: any) {
      if (err?.response?.status === 404) return null;
      throw err;
    }
  },

  /**
   * Crea una receta para un producto.
   */
  async createRecipe(payload: CreateRecipePayload): Promise<ProductRecipe> {
    const response = await apiClient.post<ProductRecipe>("/recipes", payload);
    const data = response.data as any;
    if (data?.uuid) return data as ProductRecipe;
    if (data?.data?.uuid) return data.data as ProductRecipe;
    return data as ProductRecipe;
  },

  /**
   * Actualiza una receta existente (reemplaza todos los ingredientes).
   */
  async updateRecipe(
    recipeUuid: string,
    payload: UpdateRecipePayload
  ): Promise<ProductRecipe> {
    const response = await apiClient.put<ProductRecipe>(
      `/recipes/${recipeUuid}`,
      payload
    );
    const data = response.data as any;
    if (data?.uuid) return data as ProductRecipe;
    if (data?.data?.uuid) return data.data as ProductRecipe;
    return data as ProductRecipe;
  },

  /**
   * Elimina una receta.
   */
  async deleteRecipe(recipeUuid: string): Promise<void> {
    await apiClient.delete(`/recipes/${recipeUuid}`);
  },
};
