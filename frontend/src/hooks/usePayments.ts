import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { paymentsService } from "@/services/paymentsService";
import type { PaymentMethod, CashierDashboard, ServedOrder } from "@/types/payments";

const METHODS_KEY = ["payment-methods"];
const DASHBOARD_KEY = ["cashier", "dashboard"];
const SERVED_ORDERS_KEY = ["cashier", "served-orders"];

export function usePaymentMethods() {
  return useQuery<PaymentMethod[], Error>({
    queryKey: METHODS_KEY,
    queryFn: paymentsService.listPaymentMethods,
    staleTime: 5 * 60 * 1000,
  });
}

export function useCashierDashboard() {
  return useQuery<CashierDashboard, Error>({
    queryKey: DASHBOARD_KEY,
    queryFn: paymentsService.getDashboard,
    refetchInterval: 15000,
    staleTime: 5000,
  });
}

export function useServedOrders() {
  return useQuery<ServedOrder[], Error>({
    queryKey: SERVED_ORDERS_KEY,
    queryFn: paymentsService.listServedOrders,
    refetchInterval: 10000,
    staleTime: 3000,
  });
}

export function useInvalidateCashier() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    queryClient.invalidateQueries({ queryKey: SERVED_ORDERS_KEY });
  };
}

export function useOpenSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ openingAmount, notes }: { openingAmount: number; notes?: string }) =>
      paymentsService.openSession(openingAmount, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    },
  });
}

export function useCloseSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      sessionUuid,
      closingAmount,
      notes,
    }: {
      sessionUuid: string;
      closingAmount: number;
      notes?: string;
    }) => paymentsService.closeSession(sessionUuid, closingAmount, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    },
  });
}

export function useCreatePayment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: paymentsService.createPayment,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SERVED_ORDERS_KEY });
      queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    },
  });
}

export function useMarkOrderAsPaid() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: paymentsService.markAsPaid,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SERVED_ORDERS_KEY });
      queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    },
  });
}
