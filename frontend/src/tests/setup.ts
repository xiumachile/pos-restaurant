import "@testing-library/jest-dom";
import { vi } from "vitest";

// Mock funcional de localStorage para tests.
// Los métodos siguen siendo spies de Vitest, pero además
// mantienen un almacenamiento real en memoria.
const storage = new Map<string, string>();

const localStorageMock = {
  getItem: vi.fn((key: string): string | null => {
    return storage.has(key) ? storage.get(key)! : null;
  }),

  setItem: vi.fn((key: string, value: string): void => {
    storage.set(key, String(value));
  }),

  removeItem: vi.fn((key: string): void => {
    storage.delete(key);
  }),

  clear: vi.fn((): void => {
    storage.clear();
  }),

  get length(): number {
    return storage.size;
  },

  key: vi.fn((index: number): string | null => {
    return Array.from(storage.keys())[index] ?? null;
  }),
};

Object.defineProperty(window, "localStorage", {
  value: localStorageMock,
  configurable: true,
});

// Mock de window.location
delete (window as any).location;
window.location = { reload: vi.fn(), href: "" } as any;
