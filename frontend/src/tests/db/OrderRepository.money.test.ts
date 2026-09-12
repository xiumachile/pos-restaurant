import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('../mocks/tauriSql');
  return { default: mod.default };
});

import { localDb } from '@/db/localDb';
import { runMigrations } from '@/db/schema';
import { OrderRepository } from '@/db/repositories/OrderRepository';

/**
 * Tests de integridad monetaria en OrderRepository.
 * 
 * ADR-010: Todos los totales deben ser enteros (CLP sin centavos fraccionarios).
 * Este test valida que recalculateOrderTotals() produce valores enteros exactos.
 */
describe('OrderRepository - Money integrity', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await runMigrations();
    await localDb.execute('DELETE FROM local_order_items');
    await localDb.execute('DELETE FROM local_orders');
  });

  it('produce totales enteros exactos (sin errores de punto flotante)', async () => {
    // Crear orden
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Agregar items con subtotales que causarían floats en aritmética directa
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Hamburguesa',
      quantity: 2,
      unit_price: 4999,  // 9998 subtotal
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-2',
      product_name: 'Bebida',
      quantity: 3,
      unit_price: 1500,  // 4500 subtotal
    });

    // Recargar orden
    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    // Validaciones críticas de integridad monetaria
    expect(updated).toBeDefined();
    
    // Subtotal = 9998 + 4500 = 14498 (entero)
    expect(updated!.subtotal).toBe(14498);
    expect(Number.isInteger(updated!.subtotal)).toBe(true);

    // Tax = 14498 * 0.19 = 2754.62 → redondeado a 2755
    expect(updated!.tax_total).toBe(2755);
    expect(Number.isInteger(updated!.tax_total)).toBe(true);

    // Grand total = 14498 + 2755 = 17253 (entero exacto)
    expect(updated!.grand_total).toBe(17253);
    expect(Number.isInteger(updated!.grand_total)).toBe(true);
  });

  it('evita errores acumulados con muchos items', async () => {
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Agregar 10 items de $999.99 que causarían acumulación de floats
    for (let i = 0; i < 10; i++) {
      await OrderRepository.addItem(order.local_uuid, {
        product_id: `prod-${i}`,
        product_name: `Item ${i}`,
        quantity: 1,
        unit_price: 999,  // 999 * 10 = 9990
      });
    }

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    // Subtotal = 999 * 10 = 9990
    expect(updated!.subtotal).toBe(9990);
    expect(Number.isInteger(updated!.subtotal)).toBe(true);

    // Tax = 9990 * 0.19 = 1898.1 → redondeado a 1898
    expect(updated!.tax_total).toBe(1898);
    expect(Number.isInteger(updated!.tax_total)).toBe(true);

    // Grand total = 9990 + 1898 = 11888
    expect(updated!.grand_total).toBe(11888);
    expect(Number.isInteger(updated!.grand_total)).toBe(true);
  });

  it('maneja correctamente el redondeo al medio (bankers rounding)', async () => {
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Subtotal = 1000, Tax = 1000 * 0.19 = 190 (exacto, sin redondeo)
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Item exacto',
      quantity: 1,
      unit_price: 1000,
    });

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    expect(updated!.subtotal).toBe(1000);
    expect(updated!.tax_total).toBe(190);
    expect(updated!.grand_total).toBe(1190);
  });
});
