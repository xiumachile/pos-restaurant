import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { defaultNotesService } from '@/services/defaultNotesService';
import type { CreateDefaultNotePayload, UpdateDefaultNotePayload } from '@/types/defaultNotes';

export function useDefaultNotes() {
  const queryClient = useQueryClient();

  const { data: notes = [], isLoading, error, refetch } = useQuery({
    queryKey: ['default-notes'],
    queryFn: () => defaultNotesService.getAll(),
    staleTime: 5 * 60 * 1000, // 5 minutos
  });

  const createMutation = useMutation({
    mutationFn: (payload: CreateDefaultNotePayload) => defaultNotesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['default-notes'] });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: UpdateDefaultNotePayload }) =>
      defaultNotesService.update(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['default-notes'] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (uuid: string) => defaultNotesService.delete(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['default-notes'] });
    },
  });

  return {
    notes,
    isLoading,
    error,
    refetch,
    create: createMutation.mutateAsync,
    update: updateMutation.mutateAsync,
    delete: deleteMutation.mutateAsync,
    isCreating: createMutation.isPending,
    isUpdating: updateMutation.isPending,
    isDeleting: deleteMutation.isPending,
  };
}
