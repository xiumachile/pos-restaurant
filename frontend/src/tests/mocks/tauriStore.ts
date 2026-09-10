/**
 * Mock para @tauri-apps/plugin-store
 * Simula el comportamiento del store encriptado de Tauri
 */

import { vi } from "vitest";

const mockStore = new Map<string, any>();

export const load = vi.fn().mockImplementation(async (name: string, options?: any) => {
  return {
    get: vi.fn().mockImplementation(async (key: string) => {
      const value = mockStore.get(key);
      return value !== undefined ? [value] : undefined;
    }),
    set: vi.fn().mockImplementation(async (key: string, value: any) => {
      mockStore.set(key, value);
    }),
    delete: vi.fn().mockImplementation(async (key: string) => {
      mockStore.delete(key);
    }),
    clear: vi.fn().mockImplementation(async () => {
      mockStore.clear();
    }),
    save: vi.fn().mockResolvedValue(undefined),
  };
});

export const clearMockStore = () => {
  mockStore.clear();
};
