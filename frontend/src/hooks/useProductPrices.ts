import { useQuery } from "@tanstack/react-query";
import { priceListService, type ProductPrice } from "@/services/priceListService";

/**
 * Hook para cargar los precios de un producto específico por lista de precios.
 * Solo se ejecuta cuando productUuid existe (modo edición).
 */
export function useProductPrices(productUuid: string | null) {
  return useQuery<ProductPrice[], Error>({
    queryKey: ["admin", "product-prices", productUuid],
    queryFn: () => priceListService.getProductPrices(productUuid!),
    enabled: !!productUuid,
    staleTime: 10 * 1000,
  });
}
