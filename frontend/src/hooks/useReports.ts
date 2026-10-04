import { useQuery } from "@tanstack/react-query";
import { reportsService, type DateFilter } from "@/services/reportsService";

export function useDashboardKPIs(filter: DateFilter) {
  return useQuery({
    queryKey: ["reports", "dashboard", filter.preset, filter.from_date, filter.to_date],
    queryFn: () => reportsService.getDashboardKPIs(filter),
    staleTime: 60 * 1000,
    refetchInterval: 5 * 60 * 1000,
  });
}

export function useTopProducts(filter: DateFilter, limit = 10) {
  return useQuery({
    queryKey: ["reports", "top-products", filter.preset, filter.from_date, filter.to_date, limit],
    queryFn: () => reportsService.getTopProducts(filter, limit),
    staleTime: 5 * 60 * 1000,
  });
}

export function useSalesByHour(filter: DateFilter) {
  return useQuery({
    queryKey: ["reports", "sales-by-hour", filter.preset, filter.from_date, filter.to_date],
    queryFn: () => reportsService.getSalesByHour(filter),
    staleTime: 5 * 60 * 1000,
  });
}

export function usePaymentMethods(filter: DateFilter) {
  return useQuery({
    queryKey: ["reports", "payment-methods", filter.preset, filter.from_date, filter.to_date],
    queryFn: () => reportsService.getPaymentMethods(filter),
    staleTime: 10 * 60 * 1000,
  });
}
