import { useMutation, useQueryClient } from "@tanstack/react-query";
import { offlineCashCloseService } from "@/services/offlineCashCloseService";

/**
 * Hook para cerrar sesión de caja offline (con impresión automática).
 * 
 * USO:
 *   const { closeSession, isClosing } = useOfflineCloseCashier();
 *   await closeSession.mutateAsync({
 *     sessionUuid: "...",
 *     closingAmount: 150000,
 *     notes: "Cierre normal",
 *   });
 * 
 * FUNCIONALIDAD:
 * ✓ Cierra sesión en SQLite (offline-first)
 * ✓ Calcula balance y diferencia
 * ✓ Genera copia de caja automáticamente
 * ✓ Encola impresión en local_print_jobs
 * ✓ OfflinePrintEngine imprime automáticamente (polling 3s)
 * ✓ Invalida queries de caja para refrescar UI
 * 
 * DROP-IN REPLACEMENT:
 * Este hook puede reemplazar useCloseSession en CashCloseWizard
 * para habilitar cierre offline con impresión automática.
 */
export function useOfflineCloseCashier() {
  const queryClient = useQueryClient();

  const closeSession = useMutation({
    mutationFn: (params: {
      sessionUuid: string;
      closingAmount: number;
      notes?: string;
    }) => offlineCashCloseService.closeSession(params),
    onSuccess: (result) => {
      console.log("[useOfflineCloseCashier] ✅ Sesión cerrada:", result);
      console.log(`   Diferencia: $${result.difference}`);
      
      // Invalidar queries de caja
      queryClient.invalidateQueries({ queryKey: ["cashier", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "sessions"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "movements"] });
    },
    onError: (error: any) => {
      console.error("[useOfflineCloseCashier] ❌ Error:", error?.message);
    },
  });

  return {
    closeSession,
    isClosing: closeSession.isPending,
  };
}
