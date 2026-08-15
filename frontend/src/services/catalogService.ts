import apiClient from "./apiClient";
import type { Category, Product } from "@/types/catalog";

interface ListResponse<T> {
  data: T[];
  meta?: any;
}

export const catalogService = {
  /**
   * Lista categorías activas
   */
  async listCategories(): Promise<Category[]> {
    const response = await apiClient.get<ListResponse<Category>>(
      "/catalog/categories",
      { params: { active_only: true } }
    );
    const data = response.data as any;
    return Array.isArray(data) ? data : data.data || [];
  },

  /**
   * Lista productos activos
   */
  async listProducts(categoryId?: number): Promise<Product[]> {
    const params = {
      active_only: true,
      ...(categoryId && { category_id: categoryId }),
    };
    const response = await apiClient.get<ListResponse<Product>>(
      "/catalog/products",
      { params }
    );
    const data = response.data as any;
    return Array.isArray(data) ? data : data.data || [];
  },

  /**
   * Obtiene detalle de un producto (incluye componentes si es combo)
   */
  async showProduct(uuid: string): Promise<Product> {
    const response = await apiClient.get<{ data: Product }>(
      `/catalog/products/${uuid}`
    );
    return response.data.data;
  },

  /**
   * Busca productos por nombre/SKU
   */
  async searchProducts(query: string): Promise<Product[]> {
    const response = await apiClient.get<ListResponse<Product>>(
      "/catalog/products",
      { params: { search: query, active_only: true } }
    );
    const data = response.data as any;
    return Array.isArray(data) ? data : data.data || [];
  },
};
