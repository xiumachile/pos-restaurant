import apiClient from "./apiClient";
import type { Category, Product } from "@/types/catalog";

/* ─── Tipos para administración ─── */

export interface CategoryCreatePayload {
  name_translations: Record<string, string>;
  parent_id?: string | null;
  sort_order?: number;
  is_active?: boolean;
  tax_id?: number | null;
}

export interface CategoryUpdatePayload extends Partial<CategoryCreatePayload> {}

export interface ProductCreatePayload {
  sku: string;
  name_translations: Record<string, string>;
  description_translations?: Record<string, string> | null;
  category_id: string;
  base_price: number;
  tax_rate?: number | null;
  is_combo?: boolean;
  kitchen_zone_id?: number | null;
  is_active?: boolean;
  tax_id?: number | null;
}

export interface ProductUpdatePayload extends Partial<ProductCreatePayload> {}

/* ─── Respuestas del backend ─── */

interface SuccessResponse<T> {
  success: boolean;
  data: T;
}

interface ListResponse<T> {
  success: boolean;
  data: T[];
}

/* ─── Servicio de administración de categorías ─── */

export const categoryAdminService = {
  /**
   * Lista todas las categorías (incluye inactivas para admin).
   */
  async listAll(): Promise<Category[]> {
    const response = await apiClient.get<ListResponse<Category>>(
      "/catalog/categories"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Crea una nueva categoría.
   */
  async create(payload: CategoryCreatePayload): Promise<Category> {
    const response = await apiClient.post<SuccessResponse<Category>>(
      "/catalog/categories",
      payload
    );
    return response.data.data;
  },

  /**
   * Actualiza una categoría.
   */
  async update(uuid: string, payload: CategoryUpdatePayload): Promise<Category> {
    const response = await apiClient.put<SuccessResponse<Category>>(
      `/catalog/categories/${uuid}`,
      payload
    );
    return response.data.data;
  },

  /**
   * Elimina una categoría (soft delete).
   */
  async delete(uuid: string): Promise<void> {
    await apiClient.delete(`/catalog/categories/${uuid}`);
  },
};

/* ─── Servicio de administración de productos ─── */

export const productAdminService = {
  /**
   * Lista todos los productos (incluye inactivos para admin).
   */
  async listAll(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    const params: Record<string, any> = {};
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
   * Crea un nuevo producto.
   */
  async create(payload: ProductCreatePayload): Promise<Product> {
    const response = await apiClient.post<SuccessResponse<Product>>(
      "/catalog/products",
      payload
    );
    return response.data.data;
  },

  /**
   * Actualiza un producto.
   */
  async update(uuid: string, payload: ProductUpdatePayload): Promise<Product> {
    const response = await apiClient.put<SuccessResponse<Product>>(
      `/catalog/products/${uuid}`,
      payload
    );
    return response.data.data;
  },

  /**
   * Elimina un producto (soft delete).
   */
  async delete(uuid: string): Promise<void> {
    await apiClient.delete(`/catalog/products/${uuid}`);
  },
};
