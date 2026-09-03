import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { paymentsService } from "@/services/paymentsService";
import type { PaymentMethod, CashierDashboard, SessionPaymentsData } from "@/types/payments";
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
    // FIX: también invalidar tables para que la mesa pase a "libre" tras pago
    queryClient.invalidateQueries({ queryKey: ["tables"] });
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

export function usePrepareTableBills() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (tableUuid: string) => paymentsService.prepareTableBills(tableUuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: TABLES_WITH_BILLS_KEY });
      queryClient.invalidateQueries({ queryKey: ["bills"] });
    },
  });
}


export function useSessionPayments(enabled: boolean = true) {
  return useQuery<SessionPaymentsData, Error>({
    queryKey: ["cashier", "session-payments"],
    queryFn: paymentsService.getSessionPayments,
    enabled,
    refetchInterval: 10000,
    staleTime: 3000,
  });
}

import { billsService } from "@/services/billsService";
import type { Bill, SplitPayload, PayBillPayload } from "@/types/bills";

export function useBills(orderUuid: string | null) {
  return useQuery<Bill[], Error>({
    queryKey: ["bills", orderUuid],
    queryFn: () => billsService.listBills(orderUuid!),
    enabled: !!orderUuid,
    staleTime: 3000,
  });
}

export function useSplitOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ orderUuid, payload }: { orderUuid: string; payload: SplitPayload }) =>
      billsService.splitOrder(orderUuid, payload),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["bills", variables.orderUuid] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "tables-with-bills"] });
    },
  });
}

export function usePayBill() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ billUuid, payload }: { billUuid: string; payload: PayBillPayload }) =>
      billsService.payBill(billUuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["bills"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "tables-with-bills"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "dashboard"] });
      // FIX: invalidar tables con refetchType 'all' para refetch inmediato
      // Sin refetchType, React Query respeta staleTime (10s) y no hace refetch
      queryClient.invalidateQueries({ 
        queryKey: ["tables"],
        refetchType: 'all'  // Forza refetch de todas las queries activas
      });
    },
  });
}

import { cashierService } from "@/services/cashierService";
import type { CashReport, SessionHistoryItem } from "@/types/cashier";

const X_REPORT_KEY = ["cashier", "x-report"];
const HISTORY_KEY = ["cashier", "sessions-history"];

export function useXReport(enabled: boolean = false) {
  return useQuery<CashReport, Error>({
    queryKey: X_REPORT_KEY,
    queryFn: cashierService.getXReport,
    enabled,
    staleTime: 5000,
  });
}

export function useZReport(sessionUuid: string | null, enabled: boolean = false) {
  return useQuery<CashReport, Error>({
    queryKey: ["cashier", "z-report", sessionUuid],
    queryFn: () => cashierService.getZReport(sessionUuid!),
    enabled: enabled && !!sessionUuid,
    staleTime: Infinity,
  });
}

export function useSessionsHistory(enabled: boolean = true) {
  return useQuery<SessionHistoryItem[], Error>({
    queryKey: HISTORY_KEY,
    queryFn: () => cashierService.getSessionsHistory(),
    enabled,
    staleTime: 30000,
  });
}

export function useInvalidateCashierReports() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: X_REPORT_KEY });
    queryClient.invalidateQueries({ queryKey: HISTORY_KEY });
  };
}

import { tipPayoutService } from "@/services/tipService";
import type { TipPayout, TipSummary } from "@/types/tips";

const TIP_PAYOUTS_KEY = ["cashier", "tip-payouts"];
const TIP_SUMMARY_KEY = ["cashier", "tips-summary"];

export function useTipPayouts(enabled: boolean = true) {
  return useQuery<TipPayout[], Error>({
    queryKey: TIP_PAYOUTS_KEY,
    queryFn: tipPayoutService.listPayouts,
    enabled,
    refetchInterval: 30000,
  });
}

export function useTipSummary(enabled: boolean = true) {
  return useQuery<TipSummary | null, Error>({
    queryKey: TIP_SUMMARY_KEY,
    queryFn: tipPayoutService.getSummary,
    enabled,
    refetchInterval: 30000,
  });
}

export function useCreateTipPayout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: tipPayoutService.createPayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: TIP_PAYOUTS_KEY });
      queryClient.invalidateQueries({ queryKey: TIP_SUMMARY_KEY });
      queryClient.invalidateQueries({ queryKey: ["cashier", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "x-report"] });
    },
  });
}

export function useVoidTipPayout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: tipPayoutService.voidPayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: TIP_PAYOUTS_KEY });
      queryClient.invalidateQueries({ queryKey: TIP_SUMMARY_KEY });
    },
  });
}
