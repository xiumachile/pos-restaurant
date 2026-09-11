import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  PrinterConfigRepository,
  type PrinterType,
  type CreatePrinterConfigPayload,
  type UpdatePrinterConfigPayload,
} from "@/db/repositories/PrinterConfigRepository";

const QUERY_KEY = ["printer-configs"];

/**
 * Hook para listar todas las impresoras configuradas.
 */
export function usePrinterConfigs() {
  return useQuery({
    queryKey: QUERY_KEY,
    queryFn: () => PrinterConfigRepository.findAll(),
    staleTime: 30_000,
  });
}

/**
 * Hook para obtener la impresora default de un tipo específico.
 */
export function useDefaultPrinter(printerType: PrinterType) {
  return useQuery({
    queryKey: [...QUERY_KEY, "default", printerType],
    queryFn: () => PrinterConfigRepository.getDefault(printerType),
    staleTime: 30_000,
  });
}

/**
 * Hook para crear una impresora.
 */
export function useCreatePrinter() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreatePrinterConfigPayload) =>
      PrinterConfigRepository.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para actualizar una impresora.
 */
export function useUpdatePrinter() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      localUuid,
      payload,
    }: {
      localUuid: string;
      payload: UpdatePrinterConfigPayload;
    }) => PrinterConfigRepository.update(localUuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}

/**
 * Hook para eliminar una impresora.
 */
export function useDeletePrinter() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (localUuid: string) => PrinterConfigRepository.delete(localUuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEY });
    },
  });
}
