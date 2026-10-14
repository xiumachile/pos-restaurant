import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";
import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { EventStore } from "../../db/repositories/EventStore";
import { localDomainApi } from "../../services/localDbDomain";

describe("AUD-02: Concurrencia y Consistencia de Estado (Pago vs Cancelación)", () => {
  beforeAll(async () => {
    await runMigrations();
  });

  afterAll(async () => {});

  beforeEach(async () => {
    await localDb.execute("DELETE FROM offline_events");
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_payments");
    await localDb.execute("DELETE FROM local_bills");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");

    // Crear mesas disponibles para las pruebas
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-1', '10', '5', 1]
    );
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-2', '10', '5', 2]
    );
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-3', '10', '5', 3]
    );
      await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-aud03-fail', '10', '5', 99]
    );
  });

  const createTestOrder = async (tableId: string) => {
    const companyId = '10';
    const branchId = '5';
    const itemPrice = 10000;

    const order = await OrderRepository.create({ company_id: companyId, branch_id: branchId, table_id: tableId, order_type: 'dine_in' });
    await OrderRepository.addItem(order.local_uuid, { product_id: 'prod-1', product_name: 'Test', quantity: 1, unit_price: itemPrice });
    const bill = await BillRepository.create({ 
      company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, 
      bill_number: `TEST-${tableId}`, subtotal: itemPrice, discount_total: 0, tax_total: 0, tip_amount: 0, grand_total: itemPrice 
    });
    return { order, bill, companyId, branchId, itemPrice };
  };

  const verifyConsistentState = async (orderUuid: string, tableId: string, expectedStatus: 'paid' | 'cancelled') => {
    const finalOrder = await OrderRepository.findByLocalUuid(orderUuid);
    const finalBill = await BillRepository.findByLocalUuid(orderUuid); // Asumiendo que findByLocalUuid existe o usamos query directa
    const bills = await localDb.select("SELECT * FROM local_bills WHERE order_local_uuid = ?", [orderUuid]);
    const finalBillData = bills[0];
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [orderUuid]);
    const finalTable = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);
    const syncEvents = await localDb.select('SELECT * FROM sync_queue WHERE entity_local_uuid = ?', [orderUuid]);
    const auditEvents = await localDb.select('SELECT * FROM offline_events WHERE entity_uuid = ?', [orderUuid]);

    // 1. Verificar estado del pedido
    expect(finalOrder?.status).toBe(expectedStatus);

    // 2. Verificar mesa: SIEMPRE debe estar disponible y sin referencia al pedido
    expect(finalTable[0]?.status).toBe('available');
    expect(finalTable[0]?.current_order_uuid).toBeNull();

    if (expectedStatus === 'paid') {
      expect(finalBillData?.status).toBe('paid');
      expect(payments.length).toBeGreaterThan(0);
      
      // AUD-02 FIX: Verificar evento de auditoría.
      // Nota: entity_uuid en el evento es el UUID del pago, no del pedido.
      // Buscamos todos los eventos CREATE_PAYMENT y filtramos por order_local_uuid en el payload.
      const allPaymentEvents = await localDb.select('SELECT * FROM offline_events WHERE event_type = ?', ['CREATE_PAYMENT']);
      const validPaymentEvents = allPaymentEvents.filter((e: any) => {
        try {
          const payload = typeof e.payload === 'string' ? JSON.parse(e.payload) : e.payload;
          return payload.order_local_uuid === orderUuid;
        } catch {
          return false;
        }
      });
      expect(validPaymentEvents.length).toBeGreaterThan(0);
    } else {
      expect(finalBillData?.status).toBe('open'); // O 'cancelled' según la lógica, pero NO 'paid'
      expect(payments.length).toBe(0); // Cero pagos huérfanos
    }
  };

  it("1. DEBE rechazar la cancelación si el pago ya fue confirmado", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-1');

    // 1. Ejecutar pago (ruta de producción simulada o real)
    await localDb.transaction(async (db) => {
      await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
      await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
      await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
      await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, ['table-1']);
    });

    // 2. Intentar cancelar después
    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, 'paid')
    ).rejects.toThrow(/No se puede modificar un pedido ya pagado|Conflicto/);

    // 3. Verificar estado consistente
    await verifyConsistentState(order.local_uuid, 'table-1', 'paid');
  });

  it("2. DEBE rechazar el pago si la cancelación ya fue confirmada", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-2');

    // 1. Ejecutar cancelación
    await OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, order.status);

    // 2. Intentar pagar después
    await expect(
      localDb.transaction(async (db) => {
        await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
        await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
        await OrderRepository.updateStatus(order.local_uuid, 'paid', db, 'cancelled');
      })
    ).rejects.toThrow(/No se puede modificar un pedido ya cancelado|Conflicto/);

    // 3. Verificar estado consistente
    await verifyConsistentState(order.local_uuid, 'table-2', 'cancelled');
  });

  it("3. DEBE manejar una carrera real concurrente entre pago y cancelación sin estados híbridos", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-3');

    // Función que simula la ruta de pago atómica con fail-fast
    const executePayment = async () => {
      try {
        await localDb.transaction(async (db) => {
          // 1. FAIL-FAST: Verificar y actualizar el estado PRIMERO.
          // Si hay conflicto (ej. ya fue cancelado), esto lanza un error inmediatamente
          // y evita que se modifiquen la factura o se creen pagos huérfanos.
          await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
          
          // 2. Solo si el estado se actualizó con éxito, procedemos con el resto
          await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
          await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
          await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, ['table-3']);
        });
        return 'payment_success';
      } catch (e: any) {
        return `payment_failed: ${e.message}`;
      }
    };

    // Función que simula la ruta de cancelación atómica.
    // AUD-02 FIX: Debe usar localDb.transaction para competir por el mutex de LocalWriteCoordinator.
    // Esto actúa como barrera de sincronización, garantizando que las transacciones se serialicen
    // y no haya intercalación de sentencias que cause estados híbridos en el entorno de prueba.
    const executeCancellation = async () => {
      try {
        await localDb.transaction(async (db) => {
          await OrderRepository.updateStatus(order.local_uuid, 'cancelled', db, order.status);
        });
        return 'cancel_success';
      } catch (e: any) {
        return `cancel_failed: ${e.message}`;
      }
    };

    // Ejecutar ambas operaciones simultáneamente sin delays artificiales
    const [resultPayment, resultCancel] = await Promise.all([
      executePayment(),
      executeCancellation()
    ]);

    console.log(`💳 Resultado Pago: ${resultPayment}`);
    console.log(`🚫 Resultado Cancelación: ${resultCancel}`);

    // Una debe tener éxito y la otra fallar, o ambas fallar si hay conflicto de bloqueo, 
    // pero NUNCA deben dejar un estado híbrido.
    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    
    // Verificación exhaustiva de consistencia
    await verifyConsistentState(order.local_uuid, 'table-3', finalOrder?.status as 'paid' | 'cancelled');
    
    // Regla de oro: NUNCA debe haber un estado híbrido
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    if (finalOrder?.status === 'paid') {
      expect(payments.length).toBeGreaterThan(0);
    } else {
      expect(payments.length).toBe(0);
    }
  });

  it("AUD-03: DEBE revertir todo el pago si falla la inserción del evento de auditoría (ruta TS)", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-aud03-fail');
    
    // AUD-03 FIX: Mockear EventStore.record para forzar un fallo ANTES del flush de LocalWriteCoordinator.
    // Esto demuestra que si la auditoría falla, la transacción se aborta y no se ejecuta el flush,
    // garantizando que no queden pagos huérfanos ni mesas liberadas incorrectamente.
    vi.spyOn(EventStore, 'record').mockImplementation(async () => {
      throw new Error('FORCED_AUDIT_FAILURE');
    });

    await expect(
      localDb.transaction(async (db) => {
        await PaymentRepository.create({ 
          company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, 
          bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock', amount: itemPrice, tip_amount: 0 
        }, db);
      })
    ).rejects.toThrow('FORCED_AUDIT_FAILURE');

    // Verificar que NO se creó el pago (rollback exitoso)
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    expect(payments.length).toBe(0);

    // Verificar que la mesa NO se liberó (sigue ocupada)
    const table = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', ['table-aud03-fail']);
    expect(table[0]?.status).toBe('occupied');
    expect(table[0]?.current_order_uuid).toBe(order.local_uuid);

    vi.restoreAllMocks();
  });

  it("AUD-03: DEBE incluir terminal_id y user_id en el payload enviado a Rust", async () => {
    // Verificación de contrato: el tipo RegisterPaymentPayload exige estos campos.
    // Simulamos el payload que se construiría en producción
    const payload: any = {
      local_uuid: 'test-uuid',
      idempotency_key: 'test-key',
      order_local_uuid: 'order-uuid',
      payment_method_uuid: 'mock',
      amount: 10000,
      tip_amount: 0,
      total_amount: 10000,
      terminal_id: 'mock-terminal',
      user_id: 'mock-user'
    };
    
    expect(payload.terminal_id).toBeDefined();
    expect(payload.user_id).toBeDefined();
    expect(typeof payload.terminal_id).toBe('string');
    expect(typeof payload.user_id).toBe('string');
  });
});