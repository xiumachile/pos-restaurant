import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LocalPrintJobRepository, type CreatePrintJobPayload } from "@/db/repositories/LocalPrintJobRepository";
import { getCashierContextSafe } from "@/services/authContext";

const PRINT_JOBS_KEY = "local-print-jobs";

/**
 * Hook para encolar impresiones offline-first.
 * 
 * USO:
 *   const { enqueueReceipt } = useOfflinePrintJob();
 *   await enqueueReceipt.mutateAsync({
 *     entity_uuid: bill.local_uuid,
 *     payload: { bill_number: "42-1", total: 15000, items: [...] },
 *     reference_number: "Cuenta #42-1",
 *   });
 * 
 * FLUJO:
 * 1. Hook crea job en SQLite (instantáneo)
 * 2. OfflinePrintEngine detecta el job (polling cada 3s)
 * 3. Job se imprime automáticamente
 * 4. Si falla: retry automático hasta max_attempts
 * 
 * BENEFICIOS:
 * ✓ No bloquea la UI (impresión en background)
 * ✓ Funciona offline (cola persistente en SQLite)
 * ✓ Auditoría: cada job tiene user_id + timestamps
 * ✓ Retry automático si falla la impresora
 */
export function useOfflinePrintJob() {
  const queryClient = useQueryClient();

  // Invalidar queries después de cada mutación
  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: [PRINT_JOBS_KEY] });
  };

  // Encolar impresión de ticket (receipt)
  const enqueueReceipt = useMutation({
    mutationFn: async (params: {
      entity_uuid: string;
      entity_type?: string;
      payload: any;
      reference_number?: string;
      escpos_base64?: string;
      printer_name?: string;
    }) => {
      const ctx = getCashierContextSafe();
      if (!ctx) {
        throw new Error("No hay contexto de cajero (usuario no autenticado)");
      }

      const jobData: CreatePrintJobPayload = {
        job_type: "receipt",
        entity_type: params.entity_type || "bill",
        entity_uuid: params.entity_uuid,
        payload: params.payload,
        escpos_base64: params.escpos_base64,
        printer_name: params.printer_name || "receipt-printer",
        printer_type: "receipt",
        company_id: ctx.company_id,
        branch_id: ctx.branch_id,
        terminal_id: ctx.terminal_id,
        user_id: ctx.user_id,
        user_name: ctx.user_name || undefined,
        reference_number: params.reference_number,
      };

      return await LocalPrintJobRepository.create(jobData);
    },
    onSuccess: invalidate,
  });

  // Encolar impresión de comanda (kitchen/bar)
  const enqueueCommand = useMutation({
    mutationFn: async (params: {
      job_type: "kitchen_command" | "bar_command";
      entity_uuid: string;
      entity_type?: string;
      payload: any;
      reference_number?: string;
      escpos_base64?: string;
      printer_name?: string;
    }) => {
      const ctx = getCashierContextSafe();
      if (!ctx) {
        throw new Error("No hay contexto de cajero (usuario no autenticado)");
      }

      const jobData: CreatePrintJobPayload = {
        job_type: params.job_type,
        entity_type: params.entity_type || "order",
        entity_uuid: params.entity_uuid,
        payload: params.payload,
        escpos_base64: params.escpos_base64,
        printer_name: params.printer_name || (params.job_type === "kitchen_command" ? "kitchen-printer" : "bar-printer"),
        printer_type: params.job_type === "kitchen_command" ? "kitchen" : "bar",
        company_id: ctx.company_id,
        branch_id: ctx.branch_id,
        terminal_id: ctx.terminal_id,
        user_id: ctx.user_id,
        user_name: ctx.user_name || undefined,
        reference_number: params.reference_number,
      };

      return await LocalPrintJobRepository.create(jobData);
    },
    onSuccess: invalidate,
  });

  // Query para contar jobs pendientes (para mostrar badge en UI)
  const pendingCount = useQuery({
    queryKey: [PRINT_JOBS_KEY, "count"],
    queryFn: () => LocalPrintJobRepository.countPending(),
    refetchInterval: 3000, // Polling cada 3s (igual que el engine)
    staleTime: 1000,
  });

  return {
    enqueueReceipt,
    enqueueCommand,
    pendingCount: pendingCount.data ?? 0,
  };
}
