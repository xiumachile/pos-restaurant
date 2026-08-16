import { useQuery } from "@tanstack/react-query";
import { catalogService } from "@/services/catalogService";
import type { Category, Product } from "@/types/catalog";

const CATEGORIES_KEY = ["catalog", "categories"];
const PRODUCTS_KEY = "catalog-products";

/**
 * Hook para obtener las categorías (cacheado por 5 min).
 */
export function useCategories() {
  return useQuery<Category[], Error>({
    queryKey: CATEGORIES_KEY,
    queryFn: catalogService.listCategories,
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Hook para obtener productos con filtros reactivos.
 * Re-fetch cuando cambia categoryId o search.
 */
export function useProducts(filters: {
  categoryId?: number | null;
  search?: string;
}) {
  return useQuery<Product[], Error>({
    queryKey: [PRODUCTS_KEY, filters.categoryId ?? "all", filters.search ?? ""],
    queryFn: () =>
      catalogService.listProducts({
        categoryId: filters.categoryId ?? undefined,
        search: filters.search ?? undefined,
      }),
    staleTime: 60 * 1000,
  });
}
