import { useQuery } from "@tanstack/react-query";
import { reportsService } from "@/services/reportsService";

export function useDashboardKPIs() {
  return useQuery({
    queryKey: ["reports", "dashboard"],
    queryFn: reportsService.getDashboardKPIs,
    staleTime: 60 * 1000, // 1 minuto
    refetchInterval: 5 * 60 * 1000, // Refrescar cada 5 minutos
  });
}

export function useTopProducts(days = 7, limit = 10) {
  return useQuery({
    queryKey: ["reports", "top-products", days, limit],
    queryFn: () => reportsService.getTopProducts(days, limit),
    staleTime: 5 * 60 * 1000, // 5 minutos
  });
}

export function useSalesByHour(days = 7) {
  return useQuery({
    queryKey: ["reports", "sales-by-hour", days],
    queryFn: () => reportsService.getSalesByHour(days),
    staleTime: 5 * 60 * 1000, // 5 minutos
  });
}

export function usePaymentMethods(days = 30) {
  return useQuery({
    queryKey: ["reports", "payment-methods", days],
    queryFn: () => reportsService.getPaymentMethods(days),
    staleTime: 10 * 60 * 1000, // 10 minutos
  });
}
