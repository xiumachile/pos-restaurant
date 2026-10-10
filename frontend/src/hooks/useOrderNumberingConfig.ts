import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { orderNumberingService, type OrderNumberingConfig } from '@/services/orderNumberingService';
import { useToastStore } from '@/store/useToastStore';

export function useOrderNumberingConfig() {
  const queryClient = useQueryClient();
  const addToast = useToastStore((s) => s.addToast);

  const { data, isLoading, error } = useQuery({
    queryKey: ['order-numbering-config'],
    queryFn: orderNumberingService.get,
    staleTime: 5 * 60 * 1000, // 5 minutos
  });

  const updateMutation = useMutation({
    mutationFn: orderNumberingService.update,
    onSuccess: (updatedConfig) => {
      queryClient.setQueryData(['order-numbering-config'], updatedConfig);
      addToast('success', 'Configuración guardada correctamente');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al guardar la configuración';
      addToast('error', message);
    },
  });

  const resetMutation = useMutation({
    mutationFn: orderNumberingService.resetSequence,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['order-numbering-config'] });
      addToast('success', `Secuencia reseteada a ${result.current_sequence}`);
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al resetear la secuencia';
      addToast('error', message);
    },
  });

  return {
    config: data,
    isLoading,
    error,
    updateConfig: updateMutation.mutate,
    isUpdating: updateMutation.isPending,
    resetSequence: resetMutation.mutate,
    isResetting: resetMutation.isPending,
  };
}
