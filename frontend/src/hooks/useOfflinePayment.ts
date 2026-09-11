import { useMutation, useQueryClient } from "@tanstack/react-query";
import { offlinePaymentService } from "@/services/offlinePaymentService";
import { BillRepository } from "@/db/repositories/BillRepository";
import { usePaymentMethods } from "@/hooks/usePayments";
import type { PayBillPayload, PayBillResponse } from "@/types/bills";
import type { PaymentMethodType } from "@/types/payments";

/**
 * Hook para cobrar una bill en modo offline-first.
 * 
 * MISMA INTERFAZ QUE usePayBill:
 * - Recibe: { billUuid, payload: PayBillPayload }
 * - Retorna: PayBillResponse
 * 
 * Esto permite usarlo como drop-in replacement en BillPaymentModalV2
 * cuando el usuario está en modo offline.
 * 
 * FLUJO:
 * 1. Busca la bill por UUID (cloud_id o local_uuid)
 * 2. Traduce payment_method_uuid → paymentMethod (type string)
 * 3. Llama a offlinePaymentService.createPaymentOffline
 * 4. Transforma resultado al formato de PayBillResponse
 * 5. Invalida queries para refrescar UI
 */
export function useOfflinePayment() {
  const queryClient = useQueryClient();
  const { data: paymentMethods = [] } = usePaymentMethods();

  return useMutation({
    mutationFn: async ({
      billUuid,
      payload,
    }: {
      billUuid: string;
      payload: PayBillPayload;
    }): Promise<PayBillResponse> => {
      // 1. Buscar la bill por UUID (puede ser cloud_id o local_uuid)
      let bill = await BillRepository.findByCloudId(billUuid);
      if (!bill) {
        bill = await BillRepository.findByLocalUuid(billUuid);
      }
      if (!bill) {
        throw new Error(`Bill no encontrada: ${billUuid}`);
      }

      // 2. Validar que tenga order_local_uuid (necesario para offlinePaymentService)
      if (!bill.order_local_uuid) {
        throw new Error(`Bill sin order asociado: ${billUuid}`);
      }

      // 3. Traducir payment_method_uuid → paymentMethod (type string)
      const paymentMethod = paymentMethods.find(
        (pm) => pm.uuid === payload.payment_method_uuid
      );
      if (!paymentMethod) {
        throw new Error(
          `Método de pago no encontrado: ${payload.payment_method_uuid}`
        );
      }

      // 3b. Validar que el método de pago sea soportado en offline
      // offlinePaymentService solo acepta: cash, card, transfer, gift_card
      const SUPPORTED_OFFLINE_METHODS = ["cash", "card", "transfer", "gift_card"] as const;
      if (!SUPPORTED_OFFLINE_METHODS.includes(paymentMethod.type as any)) {
        throw new Error(
          `Método de pago '${paymentMethod.type}' no soportado en modo offline. ` +
          `Métodos soportados: ${SUPPORTED_OFFLINE_METHODS.join(", ")}`
        );
      }
      const paymentType = paymentMethod.type as "cash" | "card" | "transfer" | "gift_card";

      // 4. Llamar a offlinePaymentService
      const result = await offlinePaymentService.createPaymentOffline({
        orderLocalUuid: bill.order_local_uuid,
        paymentMethod: paymentType,
        amount: payload.amount ?? bill.remaining_amount,
        tipAmount: payload.tip_amount,
        referenceCode: payload.reference_code,
        notes: payload.notes,
        autoCreateBill: false, // Ya tenemos la bill
      });

      // 5. Transformar resultado al formato de PayBillResponse
      const updatedBill = result.bill;
      if (!updatedBill) {
        throw new Error("Bill no retornada por offlinePaymentService");
      }

      return {
        success: true,
        bill_uuid: updatedBill.local_uuid,
        bill_paid: updatedBill.status === "paid",
        paid_amount: updatedBill.paid_amount,
        remaining_amount: updatedBill.remaining_amount,
        order_transitioned_to_paid: result.orderPaid,
        amount_paid: result.payment.amount,
        tip_amount: result.payment.tip_amount,
      };
    },
    onSuccess: (response) => {
      console.log("[useOfflinePayment] Pago offline exitoso:", response);
      console.log(
        "[useOfflinePayment] Order transitioned to paid:",
        response.order_transitioned_to_paid
      );

      // Invalidar queries para refrescar UI
      // (igual que usePayBill para mantener consistencia)
      queryClient.invalidateQueries({ queryKey: ["bills"] });
      queryClient.invalidateQueries({
        queryKey: ["cashier", "tables-with-bills"],
      });
      queryClient.invalidateQueries({ queryKey: ["cashier", "dashboard"] });
      queryClient.invalidateQueries({
        queryKey: ["tables"],
        refetchType: "all",
      });
    },
  });
}
