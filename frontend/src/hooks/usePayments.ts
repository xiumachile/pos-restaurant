import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { paymentsService } from "@/services/paymentsService";
import type { PaymentMethod, CashierDashboard } from "@/types/payments";
import type { TableBill } from "@/types/tableBill";

const METHODS_KEY = ["payment-methods"];
const DASHBOARD_KEY = ["cashier", "dashboard"];
const TABLES_WITH_BILLS_KEY = ["cashier", "tables-with-bills"];

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
    refetchInterval: 30000, // 30s para stats (no crítico)
    staleTime: 10000,
  });
}

export function useTablesWithBills() {
  return useQuery<TableBill[], Error>({
    queryKey: TABLES_WITH_BILLS_KEY,
    queryFn: paymentsService.listTablesWithBills,
    // ❌ ELIMINADO refetchInterval (causaba que sobreescribiera invalidateQueries)
    // Ahora se actualiza solo cuando:
    // 1. El componente se monta
    // 2. Se hace invalidateQueries (tras cobro)
    // 3. El usuario cambia de pestaña/navega
    staleTime: 0, // Siempre considerar stale para forzar refetch al montar
    gcTime: 0, // No guardar en cache (siempre fetch fresh)
  });
}

export function useInvalidateCashier() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    queryClient.invalidateQueries({ queryKey: TABLES_WITH_BILLS_KEY });
  };
}

export function useOpenSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      openingAmount,
      notes,
    }: {
      openingAmount: number;
      notes?: string;
    }) => paymentsService.openSession(openingAmount, notes),
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

export function useChargeTable() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      tableUuid,
      payload,
    }: {
      tableUuid: string;
      payload: any;
    }) => paymentsService.chargeTable(tableUuid, payload),
    onSuccess: () => {
      // Invalidar y forzar refetch inmediato
      queryClient.invalidateQueries({ queryKey: TABLES_WITH_BILLS_KEY });
      queryClient.invalidateQueries({ queryKey: DASHBOARD_KEY });
    },
  });
}
