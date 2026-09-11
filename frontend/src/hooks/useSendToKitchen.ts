import { useMutation, useQueryClient } from "@tanstack/react-query";
import { kitchenPrintService } from "@/services/kitchenPrintService";

/**
 * Hook para enviar orden a cocina (imprime ticket offline).
 * 
 * USO:
 *   const { sendToKitchen, isSending } = useSendToKitchen();
 *   await sendToKitchen.mutateAsync(orderUuid);
 * 
 * FUNCIONALIDAD:
 * ✓ Genera ticket de cocina 100% offline
 * ✓ Encola impresión en local_print_jobs
 * ✓ OfflinePrintEngine imprime automáticamente (polling 3s)
 * ✓ Invalida queries de cocina para refrescar UI
 * ✓ Manejo de errores con mensajes claros
 */
export function useSendToKitchen() {
  const queryClient = useQueryClient();

  const sendToKitchen = useMutation({
    mutationFn: (orderLocalUuid: string) =>
      kitchenPrintService.sendOrderToKitchen(orderLocalUuid),
    onSuccess: (result) => {
      console.log("[useSendToKitchen] ✅ Ticket enviado a cocina:", result);
      
      // Invalidar queries de cocina (para que el panel KDS se actualice)
      queryClient.invalidateQueries({ queryKey: ["kitchen", "queue"] });
      queryClient.invalidateQueries({ queryKey: ["kitchen", "stats"] });
    },
    onError: (error: any) => {
      console.error("[useSendToKitchen] ❌ Error:", error?.message);
    },
  });

  const reprintTicket = useMutation({
    mutationFn: (orderLocalUuid: string) =>
      kitchenPrintService.reprintKitchenTicket(orderLocalUuid),
    onSuccess: (result) => {
      console.log("[useSendToKitchen] 🔁 Ticket reimpreso:", result);
    },
  });

  return {
    sendToKitchen,
    reprintTicket,
    isSending: sendToKitchen.isPending,
    isReprinting: reprintTicket.isPending,
  };
}
