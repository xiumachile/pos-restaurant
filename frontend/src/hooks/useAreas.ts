import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useToastStore } from '@/store/useToastStore';
import apiClient from '@/services/apiClient';

export interface Area {
  uuid: string;
  code: string;
  name_translations: {
    es: string;
    zh: string;
  };
  sort_order: number;
  is_active: boolean;
}

export function useAreas() {
  const queryClient = useQueryClient();
  const addToast = useToastStore((s) => s.addToast);

  const { data: areas = [], isLoading } = useQuery<Area[]>({
    queryKey: ['areas'],
    queryFn: async () => {
      const response = await apiClient.get('/areas');
      return response.data?.data ?? [];
    },
  });

  const createMutation = useMutation({
    mutationFn: async (payload: Omit<Area, 'uuid' | 'is_active'>) => {
      const response = await apiClient.post('/areas', payload);
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['areas'] });
      addToast('success', 'Área creada correctamente');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al crear el área';
      addToast('error', message);
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ uuid, payload }: { uuid: string; payload: Partial<Area> }) => {
      const response = await apiClient.put(`/areas/${uuid}`, payload);
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['areas'] });
      addToast('success', 'Área actualizada correctamente');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al actualizar el área';
      addToast('error', message);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (uuid: string) => {
      await apiClient.delete(`/areas/${uuid}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['areas'] });
      addToast('success', 'Área eliminada correctamente');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al eliminar el área';
      addToast('error', message);
    },
  });

  return {
    areas,
    isLoading,
    createArea: createMutation.mutate,
    updateArea: (uuid: string, payload: Partial<Area>) => updateMutation.mutate({ uuid, payload }),
    deleteArea: deleteMutation.mutate,
    isCreating: createMutation.isPending,
    isUpdating: updateMutation.isPending,
    isDeleting: deleteMutation.isPending,
  };
}
