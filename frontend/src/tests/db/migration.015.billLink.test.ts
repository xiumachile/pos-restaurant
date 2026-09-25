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
 * Tests de la migración 015 (ADR-019: Split bill offline contract).
 *
 * Verifica que la columna bill_local_uuid existe en local_payments
 * y permite vincular payments a bills específicas para preservar
 * la estructura del split bill durante sincronización.
 */
describe('Migration 015: bill_local_uuid in local_payments (ADR-019)', () => {
  beforeEach(async () => {
    localDb;
    await runMigrations();

    await localDb.execute('DELETE FROM local_payments');
    await localDb.execute('DELETE FROM local_bills');
    await localDb.execute('DELETE FROM local_orders');
  });

  it('columna bill_local_uuid existe en local_payments', async () => {
    const columns = await localDb.select<{ name: string }>(
      "PRAGMA table_info(local_payments)"
    );
    const columnNames = columns.map(c => c.name);
    expect(columnNames).toContain('bill_local_uuid');
  });

  it('permite payment sin bill (bill_local_uuid NULL)', async () => {
    await localDb.execute(`
      INSERT INTO local_orders (
        local_uuid, order_number, company_id, branch_id, order_type, status,
        subtotal, grand_total, idempotency_key, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft',
        0, 0, 'idem-order-1', 'pending']);

    await localDb.execute(`
      INSERT INTO local_payments (
        local_uuid, company_id, branch_id, order_local_uuid, bill_local_uuid,
        payment_method, amount, sale_amount, tip_amount, idempotency_key,
        status, sync_status
      ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)
    `, ['pay-1', 'company-1', 'branch-1', 'order-1', 'cash',
        10000, 9000, 1000, 'idem-pay-1', 'pending', 'pending']);

    const result = await localDb.select(
      'SELECT bill_local_uuid FROM local_payments WHERE local_uuid = ?',
      ['pay-1']
    );

    expect(result[0].bill_local_uuid).toBeNull();
  });

  it('permite payment con bill_local_uuid específico', async () => {
    await localDb.execute(`
      INSERT INTO local_orders (
        local_uuid, order_number, company_id, branch_id, order_type, status,
        subtotal, grand_total, idempotency_key, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['order-1', 'ORD-001', 'company-1', 'branch-1', 'dine_in', 'draft',
        0, 0, 'idem-order-1', 'pending']);

    await localDb.execute(`
      INSERT INTO local_bills (
        local_uuid, company_id, branch_id, order_local_uuid, bill_number,
        subtotal, grand_total, status, sync_status, idempotency_key
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['bill-A', 'company-1', 'branch-1', 'order-1', 'BILL-A',
        10000, 10000, 'open', 'pending', 'idem-bill-A']);

    await localDb.execute(`
      INSERT INTO local_payments (
        local_uuid, company_id, branch_id, order_local_uuid, bill_local_uuid,
        payment_method, amount, sale_amount, tip_amount, idempotency_key,
        status, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['pay-1', 'company-1', 'branch-1', 'order-1', 'bill-A', 'cash',
        10000, 9000, 1000, 'idem-pay-1', 'pending', 'pending']);

    const result = await localDb.select(
      'SELECT bill_local_uuid FROM local_payments WHERE local_uuid = ?',
      ['pay-1']
    );

    expect(result[0].bill_local_uuid).toBe('bill-A');
  });

  it('preserva bill_local_uuid en escenario split bill (2 bills, 2 payments)', async () => {
    await localDb.execute(`
      INSERT INTO local_orders (
        local_uuid, order_number, company_id, branch_id, order_type, status,
        subtotal, grand_total, idempotency_key, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['order-1', 'ORD-SPLIT', 'company-1', 'branch-1', 'dine_in', 'served',
        20000, 20000, 'idem-order-1', 'pending']);

    await localDb.execute(`
      INSERT INTO local_bills (
        local_uuid, company_id, branch_id, order_local_uuid, bill_number,
        subtotal, grand_total, status, sync_status, idempotency_key
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['bill-A', 'company-1', 'branch-1', 'order-1', 'BILL-A',
        10000, 10000, 'open', 'pending', 'idem-bill-A']);

    await localDb.execute(`
      INSERT INTO local_bills (
        local_uuid, company_id, branch_id, order_local_uuid, bill_number,
        subtotal, grand_total, status, sync_status, idempotency_key
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['bill-B', 'company-1', 'branch-1', 'order-1', 'BILL-B',
        10000, 10000, 'open', 'pending', 'idem-bill-B']);

    // Payment 1 → Bill A
    await localDb.execute(`
      INSERT INTO local_payments (
        local_uuid, company_id, branch_id, order_local_uuid, bill_local_uuid,
        payment_method, amount, sale_amount, tip_amount, idempotency_key,
        status, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['pay-A', 'company-1', 'branch-1', 'order-1', 'bill-A', 'cash',
        10000, 10000, 0, 'idem-pay-A', 'pending', 'pending']);

    // Payment 2 → Bill B
    await localDb.execute(`
      INSERT INTO local_payments (
        local_uuid, company_id, branch_id, order_local_uuid, bill_local_uuid,
        payment_method, amount, sale_amount, tip_amount, idempotency_key,
        status, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, ['pay-B', 'company-1', 'branch-1', 'order-1', 'bill-B', 'card',
        10000, 10000, 0, 'idem-pay-B', 'pending', 'pending']);

    const results = await localDb.select(
      'SELECT local_uuid, bill_local_uuid FROM local_payments WHERE order_local_uuid = ? ORDER BY local_uuid',
      ['order-1']
    );

    expect(results).toHaveLength(2);
    expect(results[0].bill_local_uuid).toBe('bill-A');
    expect(results[1].bill_local_uuid).toBe('bill-B');
  });

  it('índice idx_local_payments_bill_local_uuid existe', async () => {
    const indexes = await localDb.select<{ name: string }>(
      "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='local_payments'"
    );
    const indexNames = indexes.map(i => i.name);
    expect(indexNames).toContain('idx_local_payments_bill_local_uuid');
  });
});
