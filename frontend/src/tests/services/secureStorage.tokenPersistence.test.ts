import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

/**
 * P1-007: Validación de que el JWT NO se persiste en localStorage en producción.
 */

describe('P1-007: Seguridad de persistencia de JWT', () => {
  const originalEnv = import.meta.env;

  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
  });

  afterEach(() => {
    import.meta.env = originalEnv;
    vi.restoreAllMocks();
  });

  describe('En entorno de PRODUCCIÓN (import.meta.env.DEV = false)', () => {
    beforeEach(() => {
      vi.stubEnv('DEV', false);
    });

    it('getItemSync NO debe leer de localStorage y debe retornar null', async () => {
      localStorage.setItem('access_token', 'fake-prod-token');
      const { getItemSync } = await import('../../services/secureStorage');
      
      const result = getItemSync('access_token');
      
      expect(result).toBeNull();
      expect(localStorage.getItem('access_token')).toBe('fake-prod-token');
    });

    it('writeToStorage NO debe escribir en localStorage', async () => {
      localStorage.removeItem('access_token');
      const { setItem } = await import('../../services/secureStorage');
      
      await setItem('access_token', 'new-fake-token');
      
      expect(localStorage.getItem('access_token')).toBeNull();
    });
  });

  describe('En entorno de DESARROLLO (import.meta.env.DEV = true)', () => {
    beforeEach(() => {
      vi.stubEnv('DEV', true);
    });

    it('getItemSync SÍ debe leer de localStorage', async () => {
      localStorage.setItem('access_token', 'fake-dev-token');
      const { getItemSync } = await import('../../services/secureStorage');
      
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
      
      // 1. Verificar que existe el comentario de P1-007
      expect(content).toMatch(/P1-007.*NO persistir token/i);
      
      // 2. Verificar que NO se está persistiendo el token en el estado
      // Buscamos la línea exacta que NO debe existir
      expect(content).not.toContain('token: state.token');
      
      // 3. Verificar que partialize existe y contiene user e isAuthenticated
      expect(content).toContain('partialize:');
      expect(content).toContain('user: state.user');
      expect(content).toContain('isAuthenticated: state.isAuthenticated');
    });
  });
});
