import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  priceListService,
  type PriceList,
  type PriceListCreatePayload,
  type PriceListUpdatePayload,
  type ProductPriceUpsertPayload,
} from "@/services/priceListService";

const PRICE_LISTS_KEY = ["admin", "price-lists"];

export function usePriceLists() {
  return useQuery<PriceList[], Error>({
    queryKey: PRICE_LISTS_KEY,
    queryFn: priceListService.listAll,
    staleTime: 30 * 1000,
  });
}

export function useCreatePriceList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: PriceListCreatePayload) =>
      priceListService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRICE_LISTS_KEY });
    },
  });
}

export function useUpdatePriceList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: PriceListUpdatePayload }) =>
      priceListService.update(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRICE_LISTS_KEY });
    },
  });
}

export function useDeletePriceList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (uuid: string) => priceListService.delete(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRICE_LISTS_KEY });
    },
  });
}

export function useUpsertProductPrices() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      productUuid,
      payload,
    }: {
      productUuid: string;
      payload: ProductPriceUpsertPayload;
    }) => priceListService.upsertProductPrices(productUuid, payload),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({
        queryKey: ["admin", "products"],
      });
    },
  });
}
