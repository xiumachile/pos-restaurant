/**
 * P1-007: Validación de seguridad de secureStorage en modo Tauri (Producción).
 */

// 0. FORZAR ENTORNO TAURI EN globalThis ANTES DE CUALQUIER IMPORTACIÓN
(globalThis as any).__TAURI_INTERNALS__ = {
  invoke: vi.fn(),
  transformCallback: vi.fn(),
};

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { isDev, isProd } from '../../lib/env';
import { setItem, getItem, removeItem, getItemSync, preloadAuthToken, clearSyncCache } from '../../services/secureStorage';

// 1. Mock del módulo de entorno
vi.mock('../../lib/env', () => ({
  isDev: vi.fn(() => true),
  isProd: vi.fn(() => false),
}));

// 2. Mock simplificado y robusto del plugin-store
const mocks = vi.hoisted(() => {
  const data = new Map<string, any>();
  return {
    data,
    store: {
      get: vi.fn((key: string) => Promise.resolve(data.get(key) ?? null)),
      set: vi.fn((key: string, value: any) => {
        data.set(key, value);
        return Promise.resolve();
      }),
      delete: vi.fn((key: string) => {
        data.delete(key);
        return Promise.resolve();
      }),
      save: vi.fn(() => Promise.resolve()),
    }
  };
});

vi.mock('@tauri-apps/plugin-store', () => ({
  load: vi.fn().mockResolvedValue(mocks.store),
}));

const clearMockStore = () => mocks.data.clear();
const setMockStore = (key: string, value: any) => mocks.data.set(key, value);

describe('secureStorage (modo Tauri) - P1-007 Security Contract', () => {
  beforeEach(() => {
    localStorage.clear();
    clearMockStore();
    clearSyncCache();
    vi.mocked(isDev).mockReturnValue(true);
    vi.mocked(isProd).mockReturnValue(false);
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('PRODUCCIÓN: Tauri Store exclusivo (localStorage PROHIBIDO)', () => {
    beforeEach(() => {
      vi.mocked(isDev).mockReturnValue(false);
      vi.mocked(isProd).mockReturnValue(true);
    });

    it('debe escribir en Tauri Store y NO en localStorage', async () => {
      await setItem('auth_token', 'SECURE_TOKEN_123');
      
      // Verificamos que el mock de set fue llamado con un Array (como lo hace la implementación real)
      expect(mocks.store.set).toHaveBeenCalledWith('auth_token', ['SECURE_TOKEN_123']);
      expect(localStorage.getItem('auth_token')).toBeNull();
      
      // Y que getItem puede leerlo
      expect(await getItem('auth_token')).toBe('SECURE_TOKEN_123');
    });

    it('debe leer de Tauri Store y NO de localStorage (incluso si hay residuo)', async () => {
      localStorage.setItem('auth_token', 'INJECTED_JWT');
      setMockStore('auth_token', ['VALID_STORE_TOKEN']);
      
      const token = await getItem('auth_token');
      expect(token).toBe('VALID_STORE_TOKEN');
      expect(localStorage.getItem('auth_token')).toBe('INJECTED_JWT');
    });

    it('REGRESIÓN CRÍTICA: NO usa localStorage como fallback en producción', async () => {
      clearMockStore();
      localStorage.setItem('access_token', 'JWT-SECRETO-EXPUERTO');
      clearSyncCache();
      
      await preloadAuthToken();
      
      expect(getItemSync('access_token')).toBeNull();
      expect(localStorage.getItem('access_token')).toBe('JWT-SECRETO-EXPUERTO');
    });

    it('debe eliminar de Tauri Store y NO tocar localStorage', async () => {
      setMockStore('to_delete', ['value']);
      localStorage.setItem('to_delete', 'legacy_value');
      
      await removeItem('to_delete');
      
      expect(await getItem('to_delete')).toBeNull();
      expect(localStorage.getItem('to_delete')).toBe('legacy_value');
    });

    it('getItemSync debe leer de caché RAM, NO de localStorage en PROD', async () => {
      localStorage.setItem('sync_key', 'RAM_BYPASS_ATTEMPT');
      expect(getItemSync('sync_key')).toBeNull();
    });
  });

  describe('DESARROLLO: localStorage permitido', () => {
    beforeEach(() => {
      vi.mocked(isDev).mockReturnValue(true);
      vi.mocked(isProd).mockReturnValue(false);
    });

    it('debe hacer mirror en localStorage para getItemSync', async () => {
      await setItem('dev_key', 'dev_value');
      expect(localStorage.getItem('dev_key')).toBe('dev_value');
      expect(getItemSync('dev_key')).toBe('dev_value');
    });

    it('debe eliminar de Tauri Store y de localStorage en DEV', async () => {
      await setItem('dev_delete', 'dev_val');
      expect(localStorage.getItem('dev_delete')).toBe('dev_val');
      
      await removeItem('dev_delete');
      
      expect(await getItem('dev_delete')).toBeNull();
      expect(localStorage.getItem('dev_delete')).toBeNull();
    });
  });

  describe('preloadAuthToken', () => {
    it('debe precargar token desde Tauri Store a caché síncrona', async () => {
      vi.mocked(isDev).mockReturnValue(false);
      setMockStore('access_token', ['jwt-tauri-xyz']);
      clearSyncCache();
      
      await preloadAuthToken();
      expect(getItemSync('access_token')).toBe('jwt-tauri-xyz');
    });

    it('REGRESIÓN: NO debe cargar token desde localStorage en producción', async () => {
      vi.mocked(isDev).mockReturnValue(false);
      localStorage.setItem('access_token', 'legacy-jwt-injection');
      clearSyncCache();
      clearMockStore();
      
      await preloadAuthToken();
      expect(getItemSync('access_token')).toBeNull();
    });
  });
});
