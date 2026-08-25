import apiClient from "./apiClient";
import type { Category } from "@/types/catalog";

/**
 * Modos de política de sustitución (coincide con backend SetComboItemSubstitutionPolicy).
 */
export type SubstitutionMode =
  | "no_substitution"
  | "any_product"
  | "allowed_category";

/**
 * Política efectiva de un producto dentro de un combo.
 */
export interface SubstitutionPolicy {
  product_id: string;
  product_name: string;
  quantity: number;
  mode: SubstitutionMode | null;
  allowed_category: { id: string; name: string } | null;
  max_price_delta: number | null;
  requires_authorization: boolean;
  scope: "none" | "branch" | "company";
}

interface SubstitutionPoliciesResponse {
  success: boolean;
  data: {
    menu_item_id: string;
    items: SubstitutionPolicy[];
  };
}

interface UpdatePolicyPayload {
  mode: SubstitutionMode;
  allowed_category_id?: string | null;
  max_price_delta?: number | null;
  requires_authorization?: boolean;
}

export const comboService = {
  /**
   * Lista las políticas efectivas (resueltas por jerarquía sucursal > empresa)
   * para cada producto del combo.
   */
  async getSubstitutionPolicies(
    menuItemUuid: string
  ): Promise<SubstitutionPolicy[]> {
    const response = await apiClient.get<SubstitutionPoliciesResponse>(
      `/catalog/combos/${menuItemUuid}/substitution-policies`
    );
    return response.data.data.items;
  },

  /**
   * Configura la política de sustitución de un producto dentro de un combo.
   */
  async updateSubstitutionPolicy(
    menuItemUuid: string,
    productUuid: string,
    payload: UpdatePolicyPayload
  ): Promise<any> {
    const response = await apiClient.put(
      `/catalog/combos/${menuItemUuid}/items/${productUuid}/substitution-policy`,
      payload
    );
    return response.data;
  },

  /**
   * Elimina el override de sucursal (vuelve a aplicar la política de empresa).
   */
  async deleteSubstitutionPolicy(
    menuItemUuid: string,
    productUuid: string
  ): Promise<any> {
    const response = await apiClient.delete(
      `/catalog/combos/${menuItemUuid}/items/${productUuid}/substitution-policy`
    );
    return response.data;
  },

  /**
   * Lista categorías para el selector (usado en modo allowed_category).
   */
  async listCategories(): Promise<Category[]> {
    const response = await apiClient.get<{ data: Category[] }>(
      "/catalog/categories",
      { params: { active_only: true } }
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },
};
