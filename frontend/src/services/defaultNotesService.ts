import apiClient from './apiClient';
import type { DefaultNote, CreateDefaultNotePayload, UpdateDefaultNotePayload } from '@/types/defaultNotes';

export const defaultNotesService = {
  async getAll(): Promise<DefaultNote[]> {
    const response = await apiClient.get<{ data: DefaultNote[] }>(
      '/catalog/default-notes'
    );
    const payload = response.data as any;
    return Array.isArray(payload?.data) ? payload.data : [];
  },

  async create(payload: CreateDefaultNotePayload): Promise<DefaultNote> {
    const response = await apiClient.post<{ data: DefaultNote }>(
      '/catalog/default-notes',
      payload
    );
    return (response.data as any).data;
  },

  async update(uuid: string, payload: UpdateDefaultNotePayload): Promise<DefaultNote> {
    const response = await apiClient.put<{ data: DefaultNote }>(
      `/catalog/default-notes/${uuid}`,
      payload
    );
    return (response.data as any).data;
  },

  async delete(uuid: string): Promise<void> {
    await apiClient.delete(`/catalog/default-notes/${uuid}`);
  },
};
