import { describe, it, expect, beforeEach, vi } from 'vitest';

// ⚠️ Mocks de Tauri ANTES de cualquier import que toque la DB
vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('../mocks/tauriSql');
  return { default: mod.default };
});

vi.mock('@tauri-apps/api/core', () => ({
  invoke: vi.fn(),
}));

import { localDb } from '@/db/localDb';
import { runMigrations } from '@/db/schema';
import { useAuthStore } from '@/store/useAuthStore';
import { localTablesService } from '@/services/localTablesService';
import { getCashierContextSafe } from '@/services/authContext';
import { localCatalogService } from '@/services/localCatalogService';
import { PrinterConfigRepository } from '@/db/repositories/PrinterConfigRepository';

/**
 * Construye un user mockeado compatible con la interfaz User.
 * Usamos `as any` para evitar conflictos con campos opcionales del tipo real.
 */
function makeUser(companyUuid: string, branchId: string, userId: string = 'user-1'): any {
  return {
    id: 1,
    uuid: userId,
    trade_name: `User ${userId}`,
    company: { uuid: companyUuid, trade_name: `Company ${companyUuid}`, id: 1, rut: '1-9' },
    company_id: companyUuid,
    branch_id: branchId,
    terminal_id: 'terminal-1',
  };
}

/**
 * Tests de aislamiento multi-tenant (ADR-012)
 * 
 * Verifica que datos de Empresa 1 NO son accesibles por Empresa 2
 * en el mismo terminal físico.
 */
describe('Multi-tenant isolation (ADR-012)', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    
    // Limpiar todas las tablas tenant-aware
    await localDb.execute('DELETE FROM local_tables');
    await localDb.execute('DELETE FROM table_local_mutations');
    await localDb.execute('DELETE FROM local_products');
    await localDb.execute('DELETE FROM local_categories');
    await localDb.execute('DELETE FROM local_payment_methods');
    await localDb.execute('DELETE FROM printer_configs');
  });

  describe('Diagnóstico: mock SQLite', () => {
    it('SELECT sin WHERE retorna todas las filas', async () => {
      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('diag-1', 'D1', 'Test', 4, 'available', 'company-1', 'branch-1')`
      );

      const all = await localDb.select<any>('SELECT * FROM local_tables', []);
      expect(all.length).toBeGreaterThan(0);
    });

    it('SELECT con WHERE company_id = ? funciona', async () => {
      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('diag-2', 'D2', 'Test', 4, 'available', 'company-1', 'branch-1')`
      );

      const filtered = await localDb.select<any>(
        'SELECT * FROM local_tables WHERE company_id = ?',
        ['company-1']
      );
      expect(filtered.length).toBeGreaterThan(0);
    });

    it('SELECT con WHERE doble funciona', async () => {
      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('diag-3', 'D3', 'Test', 4, 'available', 'company-1', 'branch-1')`
      );

      const filtered = await localDb.select<any>(
        'SELECT * FROM local_tables WHERE company_id = ? AND branch_id = ?',
        ['company-1', 'branch-1']
      );
      expect(filtered.length).toBeGreaterThan(0);
    });
  });



  describe('localTablesService', () => {
    it('Empresa 1 no ve mesas de Empresa 2', async () => {
      // Setup: Empresa 1 crea mesa
      const user1 = makeUser('company-1', 'branch-1');
      useAuthStore.setState({ user: user1 });

      // Verificar contexto antes del INSERT
      const ctx1 = getCashierContextSafe();
      expect(ctx1).not.toBeNull();
      expect(ctx1?.company_id).toBe('company-1');
      expect(ctx1?.branch_id).toBe('branch-1');

      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('table-1', '1', 'Principal', 4, 'available', 'company-1', 'branch-1')`
      );

      // Verificar que la mesa existe en SQLite
      const rawTables = await localDb.select<any>('SELECT * FROM local_tables');
      expect(rawTables).toHaveLength(1);
      expect(rawTables[0].company_id).toBe('company-1');
      expect(rawTables[0].branch_id).toBe('branch-1');

      // Verificar que Empresa 1 ve su mesa
      const company1Tables = await localTablesService.getAllTables();
      expect(company1Tables).toHaveLength(1);

      // Empresa 2 inicia sesión
      const user2 = makeUser('company-2', 'branch-2');
      useAuthStore.setState({ user: user2 });

      // Verificar contexto de Empresa 2
      const ctx2 = getCashierContextSafe();
      expect(ctx2?.company_id).toBe('company-2');
      expect(ctx2?.branch_id).toBe('branch-2');

      // Empresa 2 no debe ver mesas de Empresa 1
      const tables = await localTablesService.getAllTables();
      expect(tables).toHaveLength(0);

      // Empresa 2 crea su propia mesa
      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('table-2', 'A', 'Bar', 2, 'available', 'company-2', 'branch-2')`
      );

      // Verificar que ambas mesas existen
      const allTables = await localDb.select<any>('SELECT * FROM local_tables');
      expect(allTables).toHaveLength(2);

      // Empresa 2 debe ver su propia mesa
      const company2Tables = await localTablesService.getAllTables();
      expect(company2Tables).toHaveLength(1);
      expect(company2Tables[0].table_number).toBe('A');
    });

    it('Empresa 1 no puede marcar mesas de Empresa 2 como ocupadas', async () => {
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      await localDb.execute(
        `INSERT INTO local_tables (uuid, table_number, area_name, capacity, status, company_id, branch_id)
         VALUES ('table-1', '1', 'Principal', 4, 'available', 'company-1', 'branch-1')`
      );

      useAuthStore.setState({ user: makeUser('company-2', 'branch-2') });

      await expect(
        localTablesService.markOccupied('table-1', 'order-123', 'company-2', 'branch-2')
      ).rejects.toThrow(/no existe en SQLite|no pertenece al tenant actual/);
    });
  });

  describe('localCatalogService', () => {
    it('Empresa 1 no ve productos de Empresa 2', async () => {
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      await localDb.execute(
        `INSERT INTO local_products (uuid, name_translations, base_price, is_active, company_id, branch_id)
         VALUES ('prod-1', '{"es": "Hamburguesa"}', 5000, 1, 'company-1', 'branch-1')`
      );

      useAuthStore.setState({ user: makeUser('company-2', 'branch-2') });

      const products = await localCatalogService.listProducts();
      expect(products).toHaveLength(0);

      await localDb.execute(
        `INSERT INTO local_products (uuid, name_translations, base_price, is_active, company_id, branch_id)
         VALUES ('prod-2', '{"es": "Pizza"}', 8000, 1, 'company-2', 'branch-2')`
      );

      const company2Products = await localCatalogService.listProducts();
      expect(company2Products).toHaveLength(1);
      expect(company2Products[0].name_translations?.es).toBe('Pizza');
    });

    it('Empresa 1 no ve categorías de Empresa 2', async () => {
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      await localDb.execute(
        `INSERT INTO local_categories (uuid, name_translations, sort_order, is_active, company_id, branch_id)
         VALUES ('cat-1', '{"es": "Platos Principales"}', 1, 1, 'company-1', 'branch-1')`
      );

      useAuthStore.setState({ user: makeUser('company-2', 'branch-2') });

      const categories = await localCatalogService.listCategories();
      expect(categories).toHaveLength(0);
    });
  });

  describe('PrinterConfigRepository', () => {
    it('Empresa 1 no ve impresoras de Empresa 2', async () => {
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      await PrinterConfigRepository.create({
        printer_type: 'receipt',
        name: 'Caja 1',
        ip: '192.168.1.100',
        is_default: true,
      });

      useAuthStore.setState({ user: makeUser('company-2', 'branch-2') });

      const printers = await PrinterConfigRepository.findAll();
      expect(printers).toHaveLength(0);

      const defaultPrinter = await PrinterConfigRepository.getDefault('receipt');
      expect(defaultPrinter).toBeNull();
    });

    it('Empresa 1 no puede eliminar impresoras de Empresa 2', async () => {
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      const printer = await PrinterConfigRepository.create({
        printer_type: 'receipt',
        name: 'Caja 1',
        ip: '192.168.1.100',
      });

      useAuthStore.setState({ user: makeUser('company-2', 'branch-2') });

      await expect(
        PrinterConfigRepository.delete(printer.local_uuid)
      ).rejects.toThrow(/no encontrada|no pertenece al tenant actual/);

      // Verificar que la impresora sigue existiendo para Empresa 1
      useAuthStore.setState({ user: makeUser('company-1', 'branch-1') });

      const stillExists = await PrinterConfigRepository.findByLocalUuid(printer.local_uuid);
      expect(stillExists).not.toBeNull();
    });
  });
});
