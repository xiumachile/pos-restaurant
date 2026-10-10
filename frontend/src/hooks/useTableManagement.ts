import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useToastStore } from '@/store/useToastStore';
import apiClient from '@/services/apiClient';

interface CreateTablePayload {
  table_number: string;
  area_code: string;
  area_name_translations: {
    es: string;
    zh: string;
  };
  capacity: number;
}

interface UpdateTablePayload {
  table_number?: string;
  area_code?: string;
  area_name_translations?: {
    es: string;
    zh: string;
  };
  capacity?: number;
}

export function useTableManagement() {
  const queryClient = useQueryClient();
  const addToast = useToastStore((s) => s.addToast);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingTable, setEditingTable] = useState<any>(null);

  const createMutation = useMutation({
    mutationFn: async (payload: CreateTablePayload) => {
      const response = await apiClient.post('/tables', payload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tables'] });
      addToast('success', 'Mesa creada correctamente');
      setIsModalOpen(false);
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al crear la mesa';
      addToast('error', message);
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ uuid, payload }: { uuid: string; payload: UpdateTablePayload }) => {
      const response = await apiClient.put(`/tables/${uuid}`, payload);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tables'] });
      addToast('success', 'Mesa actualizada correctamente');
      setIsModalOpen(false);
      setEditingTable(null);
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al actualizar la mesa';
      addToast('error', message);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (uuid: string) => {
      const response = await apiClient.delete(`/tables/${uuid}`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tables'] });
      addToast('success', 'Mesa eliminada correctamente');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Error al eliminar la mesa';
      addToast('error', message);
    },
  });

  const openCreateModal = () => {
    setEditingTable(null);
    setIsModalOpen(true);
  };

  const openEditModal = (table: any) => {
    setEditingTable(table);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setEditingTable(null);
  };

  const handleCreate = (payload: CreateTablePayload) => {
    createMutation.mutate(payload);
  };

  const handleUpdate = (uuid: string, payload: UpdateTablePayload) => {
    updateMutation.mutate({ uuid, payload });
  };

  const handleDelete = (uuid: string, tableNumber: string) => {
    if (confirm(`¿Estás seguro de eliminar la mesa ${tableNumber}?\n\nEsta acción no se puede deshacer.`)) {
      deleteMutation.mutate(uuid);
    }
  };

  return {
    isModalOpen,
    editingTable,
    isLoading: createMutation.isPending || updateMutation.isPending || deleteMutation.isPending,
    openCreateModal,
    openEditModal,
    closeModal,
    handleCreate,
    handleUpdate,
    handleDelete,
  };
}
