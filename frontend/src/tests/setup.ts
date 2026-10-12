import "@testing-library/jest-dom";
import { vi, beforeAll, afterAll } from "vitest";
import { initTestDb, resetTestDb } from "../db/nativeDb";

// Mock funcional de localStorage para tests.
const storage = new Map<string, string>();
const localStorageMock = {
  getItem: vi.fn((key: string): string | null => storage.has(key) ? storage.get(key)! : null),
  setItem: vi.fn((key: string, value: string): void => { storage.set(key, String(value)); }),
  removeItem: vi.fn((key: string): void => { storage.delete(key); }),
  clear: vi.fn((): void => { storage.clear(); }),
  get length(): number { return storage.size; },
  key: vi.fn((index: number): string | null => Array.from(storage.keys())[index] ?? null),
};
Object.defineProperty(window, "localStorage", { value: localStorageMock, configurable: true });

delete (window as any).location;
window.location = { reload: vi.fn(), href: "" } as any;

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const translations: Record<string, string> = {
        "sync.sync_status": "Estado de sincronización",
        "sync.pending": "Pendiente",
        "sync.syncing": "Sincronizando",
        "sync.synced": "Sincronizado",
        "sync.failed": "Error",
        "settings.title": "Configuración",
        "catalog_admin.cancel": "Cancelar",
      };
      return translations[key] || key;
    },
    i18n: { changeLanguage: () => new Promise(() => {}), language: "es-CL" },
  }),
}));

// Inicializar la base de datos de pruebas global antes de cualquier test
beforeAll(async () => {
  await initTestDb();
});

// Limpiar la base de datos después de todos los tests
afterAll(() => {
  resetTestDb();
});
