import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mocks de Tauri ANTES de importar localDb
vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('../mocks/tauriSql');
  return { default: mod.default };
});

vi.mock('@tauri-apps/api/core', () => ({
  invoke: vi.fn(),
}));

vi.mock('@tauri-apps/plugin-shell', () => ({
  Command: {
    create: vi.fn(),
  },
}));

import { localDb } from '@/db/localDb';
import { runMigrations } from '@/db/schema';

/**
 * Tests de la migración 010 (Money columns INTEGER).
 * 
 * ESTRATEGIA DE VALIDACIÓN:
 * En lugar de PRAGMA table_info (que el mock de Tauri SQL no soporta),
 * validamos el comportamiento de las columnas de dinero:
 * 
 * 1. Las tablas existen y aceptan valores enteros
 * 2. Los valores enteros se preservan sin conversión
 * 3. Los helpers de Money producen valores que la DB acepta
 * 
 * La migración real (en producción) convertirá REAL → INTEGER.
 * El mock acepta cualquier tipo, pero al menos validamos que el
 * schema nuevo funciona correctamente con valores enteros.
 */
describe('Migration 010: Money columns as INTEGER', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    
    // Limpiar tablas antes de cada test
    await localDb.execute('DELETE FROM local_orders');
    await localDb.execute('DELETE FROM local_order_items');
    await localDb.execute('DELETE FROM local_payments');
    await localDb.execute('DELETE FROM local_cash_sessions');
    await localDb.execute('DELETE FROM local_bills');
    await localDb.execute('DELETE FROM local_cash_movements');
  });

  describe('Integridad de valores enteros en tablas', () => {
    it('local_orders acepta y preserva valores enteros', async () => {
      await localDb.execute(`
        INSERT INTO local_orders (
          local_uuid, order_number, company_id, branch_id, order_type, status,
          subtotal, discount_total, tax_total, tip_amount, grand_total, sync_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, [
        'test-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft',
        10000, 1000, 1900, 500, 12400, 'pending'
      ]);

      const result = await localDb.select(
        'SELECT subtotal, discount_total, tax_total, tip_amount, grand_total FROM local_orders WHERE local_uuid = ?',
        ['test-1']
      );

      expect(result[0].subtotal).toBe(10000);
      expect(result[0].discount_total).toBe(1000);
      expect(result[0].tax_total).toBe(1900);
      expect(result[0].tip_amount).toBe(500);
      expect(result[0].grand_total).toBe(12400);
    });

    it('local_order_items acepta y preserva valores enteros', async () => {
      // Primero crear una orden (FK requirement)
      await localDb.execute(`
        INSERT INTO local_orders (local_uuid, order_number, company_id, branch_id, order_type, status, subtotal, grand_total, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft', 0, 0, 'pending']);

      await localDb.execute(`
        INSERT INTO local_order_items (local_uuid, order_local_uuid, product_id, product_name, quantity, unit_price, subtotal, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      `, ['item-1', 'order-1', 'prod-1', 'Hamburguesa', 2, 5000, 10000, 'pending']);

      const result = await localDb.select(
        'SELECT unit_price, subtotal FROM local_order_items WHERE local_uuid = ?',
        ['item-1']
      );

      expect(result[0].unit_price).toBe(5000);
      expect(result[0].subtotal).toBe(10000);
    });

    it('local_payments acepta y preserva valores enteros', async () => {
      await localDb.execute(`
        INSERT INTO local_orders (local_uuid, order_number, company_id, branch_id, order_type, status, subtotal, grand_total, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft', 0, 0, 'pending']);

      await localDb.execute(`
        INSERT INTO local_payments (local_uuid, order_local_uuid, payment_method, amount, tip_amount, status, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `, ['pay-1', 'order-1', 'cash', 10000, 1000, 'pending', 'pending']);

      const result = await localDb.select(
        'SELECT amount, tip_amount FROM local_payments WHERE local_uuid = ?',
        ['pay-1']
      );

      expect(result[0].amount).toBe(10000);
      expect(result[0].tip_amount).toBe(1000);
    });

    it('local_cash_sessions acepta y preserva valores enteros', async () => {
      await localDb.execute(`
        INSERT INTO local_cash_sessions (local_uuid, company_id, branch_id, user_id, status, opening_amount, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `, ['session-1', 'company-1', 'branch-1', 'user-1', 'open', 50000, 'pending']);

      const result = await localDb.select(
        'SELECT opening_amount FROM local_cash_sessions WHERE local_uuid = ?',
        ['session-1']
      );

      expect(result[0].opening_amount).toBe(50000);
    });

    it('local_bills acepta y preserva valores enteros', async () => {
      await localDb.execute(`
        INSERT INTO local_orders (local_uuid, order_number, company_id, branch_id, order_type, status, subtotal, grand_total, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft', 0, 0, 'pending']);

      await localDb.execute(`
        INSERT INTO local_bills (
          local_uuid, order_local_uuid, bill_number,
          subtotal, discount_total, tax_total, tip_amount, grand_total,
          paid_amount, remaining_amount, status, sync_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, ['bill-1', 'order-1', 'BILL-001', 10000, 500, 1900, 1000, 12400, 5000, 7400, 'partial', 'pending']);

      const result = await localDb.select(
        `SELECT subtotal, discount_total, tax_total, tip_amount, grand_total,
                paid_amount, remaining_amount
         FROM local_bills WHERE local_uuid = ?`,
        ['bill-1']
      );

      expect(result[0].subtotal).toBe(10000);
      expect(result[0].discount_total).toBe(500);
      expect(result[0].tax_total).toBe(1900);
      expect(result[0].tip_amount).toBe(1000);
      expect(result[0].grand_total).toBe(12400);
      expect(result[0].paid_amount).toBe(5000);
      expect(result[0].remaining_amount).toBe(7400);
    });

    it('local_cash_movements acepta y preserva valores enteros', async () => {
      // Primero crear una sesión (FK requirement)
      await localDb.execute(`
        INSERT INTO local_cash_sessions (local_uuid, company_id, branch_id, user_id, status, opening_amount, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `, ['session-1', 'company-1', 'branch-1', 'user-1', 'open', 50000, 'pending']);

      await localDb.execute(`
        INSERT INTO local_cash_movements (local_uuid, cash_session_local_uuid, movement_type, amount, balance_after, sync_status)
        VALUES (?, ?, ?, ?, ?, ?)
      `, ['mov-1', 'session-1', 'payment', 10000, 60000, 'pending']);

      const result = await localDb.select(
        'SELECT amount, balance_after FROM local_cash_movements WHERE local_uuid = ?',
        ['mov-1']
      );

      expect(result[0].amount).toBe(10000);
      expect(result[0].balance_after).toBe(60000);
    });

    it('local_products acepta y preserva valores enteros (base_price)', async () => {
      await localDb.execute(`
        INSERT INTO local_products (local_uuid, name, base_price, tax_rate, sync_status)
        VALUES (?, ?, ?, ?, ?)
      `, ['prod-1', 'Hamburguesa', 5000, 19.00, 'pending']);

      const result = await localDb.select(
        'SELECT base_price, tax_rate FROM local_products WHERE local_uuid = ?',
        ['prod-1']
      );

      // base_price debe ser entero (migrado a INTEGER)
      expect(result[0].base_price).toBe(5000);
      // tax_rate se mantiene REAL porque es porcentaje, no dinero
      expect(result[0].tax_rate).toBe(19.00);
    });
  });

  describe('Integración con helpers de Money', () => {
    it('helpers de Money producen valores compatibles con la DB', async () => {
      const { roundToCents, calculateTax, splitAmount } = await import('@/utils/money');

      // Helpers producen enteros
      expect(roundToCents(9999.999999)).toBe(10000);
      expect(calculateTax(10000, 0.19)).toBe(1900);
      expect(splitAmount(10000, 3)).toEqual([3334, 3333, 3333]);

      // Los valores pueden insertarse en la DB
      await localDb.execute(`
        INSERT INTO local_orders (local_uuid, order_number, company_id, branch_id, order_type, status, subtotal, grand_total, sync_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft', roundToCents(9999.999999), roundToCents(9999.999999), 'pending']);

      const result = await localDb.select(
        'SELECT subtotal, grand_total FROM local_orders WHERE local_uuid = ?',
        ['order-1']
      );

      expect(result[0].subtotal).toBe(10000);
      expect(result[0].grand_total).toBe(10000);
    });
  });
});
