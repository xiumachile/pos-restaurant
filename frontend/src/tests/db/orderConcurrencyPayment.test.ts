import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";

describe("OrderRepository - Escenario 3: Pago vs. Cancelación", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  afterAll(async () => {});

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");
    
    // Crear mesas disponibles para los escenarios deterministas
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-cancel-wins', '10', '5', 1]
    );
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-pay-wins', '10', '5', 2]
    );
  });

  it("DEBE manejar correctamente cuando la CANCELACIÓN gana la carrera", async () => {
    const companyId = '10';
    const branchId = '5';
    const tableId = 'table-cancel-wins';
    const itemPrice = 10000;

    const order = await OrderRepository.create({ company_id: companyId, branch_id: branchId, table_id: tableId, order_type: 'dine_in' });
    await OrderRepository.addItem(order.local_uuid, { product_id: 'prod-1', product_name: 'Test', quantity: 1, unit_price: itemPrice });
    const bill = await BillRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_number: 'TEST-C1', subtotal: itemPrice, discount_total: 0, tax_total: 0, tip_amount: 0, grand_total: itemPrice });

    // Escenario determinista: La cancelación se ejecuta PRIMERO y de forma síncrona
    await OrderRepository.updateStatus(order.local_uuid, 'cancelled');

    // Verificar estado post-cancelación
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    const finalTable = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);

    expect(finalOrder?.status).toBe('cancelled');
    expect(finalTable[0]?.status).toBe('available');
    expect(finalTable[0]?.current_order_uuid).toBeNull();
    expect(payments.length).toBe(0);

    // Intentar pagar después debe fallar
    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'paid')
    ).rejects.toThrow("No se puede modificar un pedido ya cancelado");
  });

  it("DEBE manejar correctamente cuando el PAGO gana la carrera", async () => {
    const companyId = '10';
    const branchId = '5';
    const tableId = 'table-pay-wins';
    const itemPrice = 10000;

    const order = await OrderRepository.create({ company_id: companyId, branch_id: branchId, table_id: tableId, order_type: 'dine_in' });
    await OrderRepository.addItem(order.local_uuid, { product_id: 'prod-1', product_name: 'Test', quantity: 1, unit_price: itemPrice });
    const bill = await BillRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_number: 'TEST-P1', subtotal: itemPrice, discount_total: 0, tax_total: 0, tip_amount: 0, grand_total: itemPrice });

    // Escenario determinista: El pago se ejecuta PRIMERO dentro de una transacción
    await localDb.transaction(async (db) => {
      await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
      await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
      await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
      await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, [tableId]);
    });

    // Verificar estado post-pago
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    const finalTable = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);

    expect(finalOrder?.status).toBe('paid');
    expect(finalTable[0]?.status).toBe('available');
    expect(finalTable[0]?.current_order_uuid).toBeNull();
    expect(payments.length).toBe(1);

    // Intentar cancelar después debe fallar
    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'cancelled')
    ).rejects.toThrow("No se puede modificar un pedido ya pagado");
  });
});

  it("AUD-01: DEBE abortar toda la operación si el estado esperado no coincide (simulación de conflicto)", async () => {
    const companyId = '10';
    const branchId = '5';
    const tableId = 'table-aud01-conflict';
    const itemPrice = 10000;

    // 0. Crear la mesa explícitamente para esta prueba
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      [tableId, companyId, branchId, 99]
    );

    // 1. Crear pedido y asignar a mesa
    const order = await OrderRepository.create({ company_id: companyId, branch_id: branchId, table_id: tableId, order_type: 'dine_in' });
    
    // Verificar que la mesa está ocupada
    let table = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);
    expect(table[0]?.status).toBe('occupied');
    expect(table[0]?.current_order_uuid).toBe(order.local_uuid);

    // 2. Forzar un cambio de estado externo (simulando que otro proceso ganó la carrera)
    await localDb.execute("UPDATE local_orders SET status = 'paid' WHERE local_uuid = ?", [order.local_uuid]);

    // 3. Intentar cancelar el pedido pasando el estado esperado antiguo ('confirmed')
    // Esto debería fallar porque el estado actual ya es 'paid'
    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, 'confirmed')
    ).rejects.toThrow(/Conflicto/);

    // 4. VERIFICACIÓN OBLIGATORIA AUD-01:
    // - El estado del pedido NO debe ser 'cancelled' (debe seguir siendo 'paid')
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(finalOrder?.status).toBe('paid');

    // - La mesa NO debe haber sido liberada (sigue ocupada por el pedido pagado)
    table = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);
    expect(table[0]?.status).toBe('occupied');
    expect(table[0]?.current_order_uuid).toBe(order.local_uuid);
  });
