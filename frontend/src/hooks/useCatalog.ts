import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
import { catalogService } from "@/services/catalogService";
import { useSyncStore } from "@/store/useSyncStore";
import type { Category, Product } from "@/types/catalog";

const CATEGORIES_KEY = ["catalog", "categories"];
const PRODUCTS_KEY = "catalog-products";

/**
 * Hook que invalida las queries de catálogo cuando cambia el estado de conexión.
 * 
 * Esto garantiza que al pasar online↔offline, React Query recargue los datos
 * usando el método correcto (backend en online, SQLite en offline).
 * 
 * Se monta en AppContent para afectar globalmente a toda la app.
 */
export function useCatalogSyncInvalidation() {
  const queryClient = useQueryClient();
  const syncStatus = useSyncStore((s) => s.status);

  useEffect(() => {
    console.log(`[useCatalog] 🔄 syncStatus cambió a "${syncStatus}", invalidando queries de catálogo`);
    queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY });
    queryClient.invalidateQueries({ queryKey: [PRODUCTS_KEY] });
  }, [syncStatus, queryClient]);
}

/**
 * Hook para obtener las categorías.
 * 
 * REACTIVIDAD:
 * - syncStatus incluido en queryKey → refetch al cambiar online↔offline
 * - staleTime = 0 en offline (siempre fresh desde SQLite)
 * - staleTime = 5 min en online (cache normal del backend)
 */
export function useCategories() {
  const syncStatus = useSyncStore((s) => s.status);
  const isOffline = syncStatus === "offline";

  return useQuery<Category[], Error>({
    queryKey: [...CATEGORIES_KEY, syncStatus],
    queryFn: catalogService.listCategories,
    staleTime: isOffline ? 0 : 5 * 60 * 1000,
    refetchOnMount: isOffline ? "always" : true,
  });
}

/**
 * Hook para obtener productos con filtros reactivos.
 * Re-fetch cuando cambia categoryId, search O syncStatus.
 */
export function useProducts(filters: {
  categoryId?: number | null;
  search?: string;
}) {
  const syncStatus = useSyncStore((s) => s.status);
  const isOffline = syncStatus === "offline";

  return useQuery<Product[], Error>({
    queryKey: [PRODUCTS_KEY, filters.categoryId ?? "all", filters.search ?? "", syncStatus],
    queryFn: () =>
      catalogService.listProducts({
        categoryId: filters.categoryId ?? undefined,
        search: filters.search ?? undefined,
      }),
    staleTime: isOffline ? 0 : 60 * 1000,
    refetchOnMount: isOffline ? "always" : true,
  });
}
