import { describe, it, expect, beforeEach, vi } from 'vitest';
import { localDb } from '@/db/localDb';
import { OrderRepository } from '@/db/repositories/OrderRepository';

// Mock Tauri SQL antes de importar localDb
vi.mock('@tauri-apps/plugin-sql', async () => {
  const mod = await import('../mocks/tauriSql');
  return { default: mod.default };
});

vi.mock('@tauri-apps/api/core', () => ({
  invoke: vi.fn(),
}));

describe('OrderRepository - Money integrity (ADR-011: Modelo chileno)', () => {
  beforeEach(async () => {
    await localDb.getConnection();
    await localDb.execute('DELETE FROM local_order_items');
    await localDb.execute('DELETE FROM local_orders');
  });

  it('produce totales enteros exactos con IVA incluido', async () => {
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Item 1: $9.998 IVA incluido (2 * $4.999)
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Hamburguesa',
      quantity: 2,
      unit_price: 4999,
    });

    // Item 2: $4.500 IVA incluido (3 * $1.500)
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-2',
      product_name: 'Bebida',
      quantity: 3,
      unit_price: 1500,
    });

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    // ADR-011: Precios IVA incluido
    expect(updated?.subtotal).toBe(14498);  // 9998 + 4500
    expect(updated?.net_amount).toBe(12183); // 14498 / 1.19
    expect(updated?.tax_total).toBe(2315);   // 14498 - 12183
    expect(updated?.grand_total).toBe(14498); // = subtotal
    expect(updated?.amount_due).toBe(14498);  // = grand_total (sin propina)
  });

  it('evita errores acumulados con muchos items', async () => {
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Agregar 10 items de $999 IVA incluido
    for (let i = 0; i < 10; i++) {
      await OrderRepository.addItem(order.local_uuid, {
        product_id: `prod-${i}`,
        product_name: `Item ${i}`,
        quantity: 1,
        unit_price: 999,
      });
    }

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    expect(updated?.subtotal).toBe(9990);    // 999 * 10
    expect(updated?.net_amount).toBe(8395);  // 9990 / 1.19
    expect(updated?.tax_total).toBe(1595);   // 9990 - 8395
    expect(updated?.grand_total).toBe(9990);
  });

  it('maneja correctamente precios exactos sin redondeo', async () => {
    const order = await OrderRepository.create({
      company_id: 'company-1',
      branch_id: 'branch-1',
      order_type: 'dine_in',
    });

    // Item de $1.190 IVA incluido (divisible exactamente por 1.19)
    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Item exacto',
      quantity: 1,
      unit_price: 1190,
    });

    const updated = await OrderRepository.findByLocalUuid(order.local_uuid);

    expect(updated?.subtotal).toBe(1190);
    expect(updated?.net_amount).toBe(1000);  // 1190 / 1.19 = 1000 exacto
    expect(updated?.tax_total).toBe(190);    // 1190 - 1000 = 190 exacto
    expect(updated?.grand_total).toBe(1190);
  });
});
