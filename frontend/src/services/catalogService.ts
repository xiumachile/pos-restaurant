import apiClient from "./apiClient";
import { localCatalogService } from "./localCatalogService";
import { useSyncStore } from "@/store/useSyncStore";
import type { Category, Product } from "@/types/catalog";

interface ListResponse<T> {
  data: T[];
}

export const catalogService = {
  /**
   * Lista categorías activas.
   * 
   * OFFLINE: lee directamente de SQLite (local_categories)
   * ONLINE: intenta backend, fallback a SQLite si falla
   */
  async listCategories(): Promise<Category[]> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    console.log(`[catalogService] 📋 listCategories() - syncStatus: ${syncStatus}, isOffline: ${isOffline}`);

    if (isOffline) {
      try {
        const result = await localCatalogService.listCategories();
        console.log(`[catalogService] ✅ Offline: ${result.length} categorías desde SQLite`);
        return result;
      } catch (error: any) {
        console.error("[catalogService] ❌ Error leyendo categorías desde SQLite:", error?.message || error);
        return [];
      }
    }

    try {
      const response = await apiClient.get<ListResponse<Category>>(
        "/catalog/categories",
        { params: { active_only: true } }
      );
      const data = response.data as any;
      const result = Array.isArray(data?.data) ? data.data : [];
      console.log(`[catalogService] ✅ Online: ${result.length} categorías desde backend`);
      return result;
    } catch (error: any) {
      console.warn("[catalogService] ⚠️ Backend inaccesible, usando SQLite:", error?.message);
      try {
        return await localCatalogService.listCategories();
      } catch (fallbackError: any) {
        console.error("[catalogService] ❌ Error en fallback SQLite:", fallbackError?.message || fallbackError);
        return [];
      }
    }
  },

  /**
   * Lista productos con filtros opcionales.
   * 
   * OFFLINE: lee directamente de SQLite (local_products) con filtros
   * ONLINE: intenta backend, fallback a SQLite si falla
   */
  async listProducts(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    const syncStatus = useSyncStore.getState().status;
    const isOffline = syncStatus === "offline";

    console.log(`[catalogService] 📋 listProducts(filters: ${JSON.stringify(filters)}) - isOffline: ${isOffline}`);

    if (isOffline) {
      try {
        const result = await localCatalogService.listProducts(filters);
        console.log(`[catalogService] ✅ Offline: ${result.length} productos desde SQLite`);
        return result;
      } catch (error: any) {
        console.error("[catalogService] ❌ Error leyendo productos desde SQLite:", error?.message || error);
        return [];
      }
    }

    const params: Record<string, any> = { active_only: true };
    if (filters?.categoryId) params.category_id = filters.categoryId;
    if (filters?.search && filters.search.trim()) params.search = filters.search.trim();

    try {
      const response = await apiClient.get<ListResponse<Product>>(
        "/catalog/products",
        { params }
      );
      const data = response.data as any;
      const result = Array.isArray(data?.data) ? data.data : [];
      console.log(`[catalogService] ✅ Online: ${result.length} productos desde backend`);
      return result;
    } catch (error: any) {
      console.warn("[catalogService] ⚠️ Backend inaccesible, usando SQLite:", error?.message);
      try {
        return await localCatalogService.listProducts(filters);
      } catch (fallbackError: any) {
        console.error("[catalogService] ❌ Error en fallback SQLite:", fallbackError?.message || fallbackError);
        return [];
      }
    }
  },

  /**
   * Obtiene detalle de un producto.
   * (No implementado offline - uso raro en contexto offline)
   */
  async showProduct(uuid: string): Promise<Product> {
    const response = await apiClient.get<{ data: Product }>(
      `/catalog/products/${uuid}`
    );
    return response.data.data;
  },
};
