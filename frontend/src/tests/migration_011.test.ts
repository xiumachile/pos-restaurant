import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mocks de Tauri ANTES de importar localDb
vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('./mocks/tauriSql');
  return { default: mod.default };
});

vi.mock('@tauri-apps/api/core', () => ({
  invoke: vi.fn(),
}));

import { localDb } from '../db/localDb';
import { runMigrations } from '../db/schema';
import { OrderRepository } from '../db/repositories/OrderRepository';
import { BillRepository } from '../db/repositories/BillRepository';
import { PaymentRepository } from '../db/repositories/PaymentRepository';

describe('Migration 011: Chilean POS model (ADR-011)', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute('DELETE FROM local_payments');
    await localDb.execute('DELETE FROM local_bills');
    await localDb.execute('DELETE FROM local_order_items');
    await localDb.execute('DELETE FROM local_orders');
  });

  it('Order calcula net_amount y tax_amount correctamente (IVA incluido)', async () => {
    const order = await OrderRepository.create({
      company_id: 'test',
      branch_id: 'test',
    });

    // Agregar item de $10.000 IVA incluido
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Hamburguesa',
      quantity: 1,
      unit_price: 10000,
    });

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    // ADR-011: Precios IVA incluido
    expect(updated?.subtotal).toBe(10000);  // subtotal_gross
    expect(updated?.net_amount).toBe(8403); // 10000 / 1.19
    expect(updated?.tax_total).toBe(1597);  // 10000 - 8403
    expect(updated?.grand_total).toBe(10000); // = subtotal - discount
    expect(updated?.amount_due).toBe(10000);  // = grand_total + tip (sin propina)
  });

  it('Order con propina calcula amount_due correctamente', async () => {
    const order = await OrderRepository.create({
      company_id: 'test',
      branch_id: 'test',
    });

    // Agregar item de $10.000 IVA incluido
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Hamburguesa',
      quantity: 1,
      unit_price: 10000,
    });

    // Agregar propina de $1.000
    await OrderRepository.updateTipAmount(order.local_uuid, 1000);

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    expect(updated?.grand_total).toBe(10000);
    expect(updated?.tip_amount).toBe(1000);
    expect(updated?.amount_due).toBe(11000); // grand_total + tip
  });

  it('Payment calcula sale_amount correctamente', async () => {
    const payment = await PaymentRepository.create({
      company_id: 'test',
      branch_id: 'test',
      amount: 11000,
      tip_amount: 1000,
      payment_method: 'cash',
    });

    expect(payment.amount).toBe(11000);
    expect(payment.sale_amount).toBe(10000); // amount - tip
    expect(payment.tip_amount).toBe(1000);
  });

  it('Bill calcula amount_due correctamente', async () => {
    const order = await OrderRepository.create({
      company_id: 'test',
      branch_id: 'test',
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Hamburguesa',
      quantity: 1,
      unit_price: 10000,
    });

    await OrderRepository.updateTipAmount(order.local_uuid, 1000);

    const bill = await BillRepository.create({
      company_id: 'test',
      branch_id: 'test',
      order_local_uuid: order.local_uuid,
      bill_number: 'BILL-001',
      subtotal: 10000,
      grand_total: 10000,
      tip_amount: 1000,
    });

    expect(bill.grand_total).toBe(10000);
    expect(bill.tip_amount).toBe(1000);
    expect(bill.amount_due).toBe(11000);
    expect(bill.remaining_amount).toBe(11000); // Sin pagos aún
  });

  it('Validación: net_amount + tax_amount = grand_total', async () => {
    const order = await OrderRepository.create({
      company_id: 'test',
      branch_id: 'test',
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Item',
      quantity: 1,
      unit_price: 10000,
    });

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    const validation = updated!.net_amount + updated!.tax_total;
    expect(validation).toBe(updated!.grand_total);
  });

  it('Validación: amount_due = grand_total + tip_amount', async () => {
    const order = await OrderRepository.create({
      company_id: 'test',
      branch_id: 'test',
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Item',
      quantity: 1,
      unit_price: 10000,
    });

    await OrderRepository.updateTipAmount(order.local_uuid, 1000);

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    const validation = updated!.grand_total + updated!.tip_amount;
    expect(validation).toBe(updated!.amount_due);
  });
});
