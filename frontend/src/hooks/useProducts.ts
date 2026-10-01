import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/services/apiClient";
import type { Product } from "@/types/catalog";

export function useProducts() {
  return useQuery<Product[], Error>({
    queryKey: ["catalog", "products"],
    queryFn: async () => {
      const res = await apiClient.get("/catalog/products");
      const data = res.data as any;
      return (data?.data ?? []) as Product[];
    },
    staleTime: 30 * 1000,
  });
}
