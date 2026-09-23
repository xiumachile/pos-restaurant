import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

/**
 * P1-007: Validación de que el JWT NO se persiste en localStorage en producción.
 */

describe('P1-007: Seguridad de persistencia de JWT', () => {
  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
    vi.unstubAllEnvs();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('En entorno de PRODUCCIÓN (DEV = false)', () => {
    beforeEach(() => {
      vi.stubEnv('DEV', false);
    });

    it('getItemSync NO debe leer de localStorage, pero SÍ debe leer de syncCache (RAM)', async () => {
      // Preparar localStorage con un token falso
      localStorage.setItem('access_token', 'fake-prod-token');
      
      const { getItemSync, updateSyncCache } = await import('../../services/secureStorage');
      
      // Si está en cache (RAM), debe retornarlo (RAM es seguro)
      updateSyncCache('access_token', 'ram-token');
      expect(getItemSync('access_token')).toBe('ram-token');
      
      // Limpiamos cache para probar que NO cae a localStorage
      const { clearSyncCache } = await import('../../services/secureStorage');
      clearSyncCache();
      
      // Ahora debe retornar null, ignorando el localStorage
      const result = getItemSync('access_token');
      expect(result).toBeNull();
    });

    it('writeToStorage NO debe escribir en localStorage', async () => {
      localStorage.removeItem('access_token');
      const { setItem } = await import('../../services/secureStorage');
      
      await setItem('access_token', 'new-fake-token');
      
      expect(localStorage.getItem('access_token')).toBeNull();
    });
  });

  describe('En entorno de DESARROLLO (DEV = true)', () => {
    beforeEach(() => {
      vi.stubEnv('DEV', true);
    });

    it('getItemSync SÍ debe leer de localStorage como fallback', async () => {
      localStorage.setItem('access_token', 'fake-dev-token');
      const { getItemSync, clearSyncCache } = await import('../../services/secureStorage');
      clearSyncCache();
      
      const result = getItemSync('access_token');
      expect(result).toBe('fake-dev-token');
    });

    it('writeToStorage SÍ debe escribir en localStorage (fallback)', async () => {
      localStorage.removeItem('access_token');
      const { setItem } = await import('../../services/secureStorage');
      
      await setItem('access_token', 'new-dev-token');
      expect(localStorage.getItem('access_token')).toBe('new-dev-token');
    });
  });

  describe('useAuthStore NO persiste token (Verificación Estática)', () => {
    it('el archivo useAuthStore.ts NO debe incluir token en partialize', () => {
      const storePath = path.resolve(__dirname, '../../store/useAuthStore.ts');
      const content = fs.readFileSync(storePath, 'utf-8');
      
      expect(content).toMatch(/P1-007.*NO persistir token/i);
      expect(content).not.toContain('token: state.token');
      expect(content).toContain('partialize:');
      expect(content).toContain('user: state.user');
      expect(content).toContain('isAuthenticated: state.isAuthenticated');
    });
  });
});
