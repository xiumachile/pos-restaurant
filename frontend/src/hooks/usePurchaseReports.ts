import { useQuery } from "@tanstack/react-query";
import { purchasesReportService } from "@/services/purchasesReportService";
import type { DateFilter } from "@/services/reportsService";

export function usePurchaseKPIs(filter: DateFilter) {
  return useQuery({
    queryKey: ["reports", "purchases", "kpis", filter.preset, filter.from_date, filter.to_date],
    queryFn: () => purchasesReportService.getKPIs(filter),
    staleTime: 60 * 1000,
    refetchInterval: 5 * 60 * 1000,
  });
}

export function usePurchasesByDocumentType(filter: DateFilter) {
  return useQuery({
    queryKey: ["reports", "purchases", "by-document-type", filter.preset, filter.from_date, filter.to_date],
    queryFn: () => purchasesReportService.getByDocumentType(filter),
    staleTime: 5 * 60 * 1000,
  });
}

export function useTopSuppliers(filter: DateFilter, limit = 10) {
  return useQuery({
    queryKey: ["reports", "purchases", "top-suppliers", filter.preset, filter.from_date, filter.to_date, limit],
    queryFn: () => purchasesReportService.getTopSuppliers(filter, limit),
    staleTime: 5 * 60 * 1000,
  });
}

export function useTopPurchasedIngredients(filter: DateFilter, limit = 10) {
  return useQuery({
    queryKey: ["reports", "purchases", "top-ingredients", filter.preset, filter.from_date, filter.to_date, limit],
    queryFn: () => purchasesReportService.getTopIngredients(filter, limit),
    staleTime: 5 * 60 * 1000,
  });
}
