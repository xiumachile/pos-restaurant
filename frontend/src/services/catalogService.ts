import apiClient from "./apiClient";
import type { Category, Product } from "@/types/catalog";

interface ListResponse<T> {
  data: T[];
}

export const catalogService = {
  /**
   * Lista categorías activas.
   */
  async listCategories(): Promise<Category[]> {
    const response = await apiClient.get<ListResponse<Category>>(
      "/catalog/categories",
      { params: { active_only: true } }
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Lista productos con filtros opcionales.
   */
  async listProducts(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    const params: Record<string, any> = { active_only: true };
    if (filters?.categoryId) params.category_id = filters.categoryId;
    if (filters?.search && filters.search.trim()) params.search = filters.search.trim();

    const response = await apiClient.get<ListResponse<Product>>(
      "/catalog/products",
      { params }
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Obtiene detalle de un producto.
   */
  async showProduct(uuid: string): Promise<Product> {
    const response = await apiClient.get<{ data: Product }>(
      `/catalog/products/${uuid}`
    );
    return response.data.data;
  },
};
