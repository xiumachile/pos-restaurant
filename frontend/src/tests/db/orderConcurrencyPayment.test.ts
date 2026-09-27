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
    
    // Crear una mesa disponible
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-3', '10', '5', 3]
    );
  });

  it("debe mantener la consistencia si se intenta pagar y cancelar el mismo pedido simultáneamente", async () => {
    const companyId = '10';
    const branchId = '5';
    const tableId = 'table-3';
    const itemPrice = 10000;

    // 1. Crear pedido y agregar un item
    const order = await OrderRepository.create({
      company_id: companyId,
      branch_id: branchId,
      table_id: tableId,
      order_type: 'dine_in',
    });

    await OrderRepository.addItem(order.local_uuid, {
      product_id: 'prod-1',
      product_name: 'Producto de Prueba',
      quantity: 1,
      unit_price: itemPrice,
    });

    // 2. Crear la bill manualmente para el test
    const bill = await BillRepository.create({
      company_id: companyId,
      branch_id: branchId,
      order_local_uuid: order.local_uuid,
      bill_number: 'TEST-001',
      subtotal: itemPrice,
      discount_total: 0,
      tax_total: 0,
      tip_amount: 0,
      grand_total: itemPrice,
    });

    console.log(`📦 Pedido y Bill creados. Estado inicial order: ${order.status}, bill: ${bill.status}`);

    // 3. Simular concurrencia: Pago vs Cancelación
    const promisePayment = (async () => {
      try {
        // Simulamos el núcleo de la transacción de pago
        await localDb.transaction(async (db) => {
          // Actualizar bill
          await (db as any).execute(
            `UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`,
            [itemPrice, 0, bill.local_uuid]
          );
          // Crear pago
          await PaymentRepository.create({
            company_id: companyId,
            branch_id: branchId,
            order_local_uuid: order.local_uuid,
            bill_local_uuid: bill.local_uuid,
            payment_method: 'cash',
            amount: itemPrice,
            tip_amount: 0,
          }, db);
          // Actualizar orden
          await OrderRepository.updateStatus(order.local_uuid, 'paid', db);
          // Liberar mesa
          await (db as any).execute(
            `UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`,
            [tableId]
          );
        });
        return 'payment_success';
      } catch (e: any) {
        return `payment_failed: ${e.message}`;
      }
    })();

    const promiseCancel = (async () => {
      try {
        // Pequeño delay para aumentar la probabilidad de solapamiento real
        await new Promise(r => setTimeout(r, 5));
        await OrderRepository.updateStatus(order.local_uuid, 'cancelled');
        return 'cancel_success';
      } catch (e: any) {
        return `cancel_failed: ${e.message}`;
      }
    })();

    // 4. Ejecutar simultáneamente
    const [resultPayment, resultCancel] = await Promise.all([promisePayment, promiseCancel]);
    
    console.log(`💳 Resultado Pago: ${resultPayment}`);
    console.log(`🚫 Resultado Cancelación: ${resultCancel}`);

    // 5. Verificar integridad final
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    const finalBill = await BillRepository.findByLocalUuid(bill.local_uuid);
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    const finalTable = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);

    console.log(`📊 Estado final Order: ${finalOrder?.status}`);
    console.log(`📊 Estado final Bill: ${finalBill?.status}`);
    console.log(`📊 Pagos registrados: ${payments.length}`);
    console.log(`🪑 Estado final Mesa: ${finalTable[0]?.status} (current_order: ${finalTable[0]?.current_order_uuid})`);

    // ==========================================
    // EXPECTATIVAS DE CONSISTENCIA
    // ==========================================
    
    // Escenario A: El pago ganó la carrera
    if (resultPayment === 'payment_success') {
      expect(finalOrder?.status).toBe('paid');
      expect(finalBill?.status).toBe('paid');
      expect(payments.length).toBe(1);
      expect(finalTable[0]?.status).toBe('available');
      expect(finalTable[0]?.current_order_uuid).toBeNull();
    } 
    // Escenario B: La cancelación ganó la carrera (el pago debería haber fallado o revertido)
    else {
      expect(finalOrder?.status).toBe('cancelled');
      expect(finalBill?.status).toBe('open'); // O 'cancelled' si la lógica lo maneja, pero no 'paid'
      expect(payments.length).toBe(0); // No debe haber pagos huérfanos
      // La mesa podría seguir ocupada o no, dependiendo de la lógica de cancelación, pero no debe haber inconsistencia de pago
    }

    // Regla de oro: NUNCA debe haber un estado híbrido (pagado pero con pagos = 0, o pagado pero mesa ocupada por ese order)
    if (finalOrder?.status === 'paid') {
      expect(payments.length).toBeGreaterThan(0);
    }
  });
});
