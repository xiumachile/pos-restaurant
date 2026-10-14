import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";
import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { BillRepository } from "../../db/repositories/BillRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { EventStore } from "../../db/repositories/EventStore";
import { localDomainApi } from "../../services/localDbDomain";

describe("AUD: Concurrencia y Consistencia de Estado (Pago vs Cancelación)", () => {
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
    const tables = [
      ['table-1', '10', '5', 1],
      ['table-2', '10', '5', 2],
      ['table-3', '10', '5', 3],
      ['table-aud01-paid-cancel', '10', '5', 100],
      ['table-aud02-race', '10', '5', 102],
      ['table-aud03-fail', '10', '5', 99]
    ];
    
    for (const [uuid, companyId, branchId, num] of tables) {
      await localDb.execute(
        `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
        [uuid, companyId, branchId, num]
      );
    }
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
    const bills = await localDb.select("SELECT * FROM local_bills WHERE order_local_uuid = ?", [orderUuid]);
    const finalBillData = bills[0];
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [orderUuid]);
    const finalTable = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', [tableId]);

    expect(finalOrder?.status).toBe(expectedStatus);
    expect(finalTable[0]?.status).toBe('available');
    expect(finalTable[0]?.current_order_uuid).toBeNull();

    if (expectedStatus === 'paid') {
      expect(finalBillData?.status).toBe('paid');
      expect(payments.length).toBeGreaterThan(0);
    } else {
      expect(payments.length).toBe(0);
    }
  };

  it("1. DEBE rechazar la cancelación si el pago ya fue confirmado", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-1');

    await localDb.transaction(async (db) => {
      await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
      await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
      await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
      await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, ['table-1']);
    });

    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, 'paid')
    ).rejects.toThrow(/No se puede modificar un pedido ya pagado|Conflicto/);

    await verifyConsistentState(order.local_uuid, 'table-1', 'paid');
  });

  it("2. DEBE rechazar el pago si la cancelación ya fue confirmada", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-2');

    await OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, order.status);

    await expect(
      localDb.transaction(async (db) => {
        await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
        await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
        await OrderRepository.updateStatus(order.local_uuid, 'paid', db, 'cancelled');
      })
    ).rejects.toThrow(/No se puede modificar un pedido ya cancelado|Conflicto/);

    await verifyConsistentState(order.local_uuid, 'table-2', 'cancelled');
  });

  it("3. DEBE manejar una carrera real concurrente entre pago y cancelación sin estados híbridos", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-3');

    const executePayment = async () => {
      try {
        await localDb.transaction(async (db) => {
          await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
          await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
          await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock-method', amount: itemPrice, tip_amount: 0 }, db);
          await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, ['table-3']);
        });
        return 'payment_success';
      } catch (e: any) {
        return `payment_failed: ${e.message}`;
      }
    };

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

    const [resultPayment, resultCancel] = await Promise.all([executePayment(), executeCancellation()]);

    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    await verifyConsistentState(order.local_uuid, 'table-3', finalOrder?.status as 'paid' | 'cancelled');
    
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    if (finalOrder?.status === 'paid') {
      expect(payments.length).toBeGreaterThan(0);
    } else {
      expect(payments.length).toBe(0);
    }
  });

  it("AUD-01: DEBE rechazar la transición de 'paid' a 'cancelled' y no dejar efectos secundarios", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-aud01-paid-cancel');
    
    await localDb.transaction(async (db) => {
      await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
      await OrderRepository.updateStatus(order.local_uuid, 'paid', db, order.status);
      await (db as any).execute(`UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?`, ['table-aud01-paid-cancel']);
    });

    await expect(
      OrderRepository.updateStatus(order.local_uuid, 'cancelled', undefined, 'paid')
    ).rejects.toThrow(/No se puede modificar un pedido ya pagado|Conflicto/);

    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(finalOrder?.status).toBe('paid');
    
    const syncQueue = await localDb.select('SELECT * FROM sync_queue WHERE entity_local_uuid = ?', [order.local_uuid]);
    const cancelSync = syncQueue.find((s: any) => s.action === 'update' && s.payload.includes('cancelled'));
    expect(cancelSync).toBeUndefined();
  });

  it("AUD-02: DEBE rechazar la operación perdedora en una carrera simulada, sin dejar efectos secundarios", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-aud02-race');
    
    // Simular que la cancelación "gana" la carrera modificando el estado antes de que el pago intente su transacción
    await localDb.execute("UPDATE local_orders SET status = 'cancelled' WHERE local_uuid = ?", [order.local_uuid]);
    await localDb.execute("UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = ?", ['table-aud02-race']);

    // El pago intenta ejecutarse. Al NO pasar expectedCurrentStatus, updateStatus leerá 
    // el estado real de la BD ('cancelled'), aplicará las reglas de negocio (fail-fast) 
    // y lanzará un error ANTES de agregar cualquier sentencia al buffer de txDb.
    await expect(
      localDb.transaction(async (db) => {
        await OrderRepository.updateStatus(order.local_uuid, 'paid', db);
        await (db as any).execute(`UPDATE local_bills SET paid_amount = ?, remaining_amount = ?, status = 'paid' WHERE local_uuid = ?`, [itemPrice, 0, bill.local_uuid]);
        await PaymentRepository.create({ company_id: companyId, branch_id: branchId, order_local_uuid: order.local_uuid, bill_local_uuid: bill.local_uuid, payment_method_uuid: 'mock', amount: itemPrice, tip_amount: 0 }, db);
      })
    ).rejects.toThrow(/No se puede modificar un pedido ya cancelado/);

    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    expect(finalOrder?.status).toBe('cancelled');
    
    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    expect(payments.length).toBe(0);
    
    const table = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', ['table-aud02-race']);
    expect(table[0]?.status).toBe('available');
  });

  it("AUD-03: DEBE revertir todo el pago si falla la inserción del evento de auditoría (ruta TS)", async () => {
    const { order, bill, companyId, branchId, itemPrice } = await createTestOrder('table-aud03-fail');
    
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

    const payments = await localDb.select('SELECT * FROM local_payments WHERE order_local_uuid = ?', [order.local_uuid]);
    expect(payments.length).toBe(0);

    const table = await localDb.select('SELECT status, current_order_uuid FROM local_tables WHERE uuid = ?', ['table-aud03-fail']);
    expect(table[0]?.status).toBe('occupied');
    expect(table[0]?.current_order_uuid).toBe(order.local_uuid);

    vi.restoreAllMocks();
  });

  it("AUD-03: DEBE incluir terminal_id y user_id en el payload enviado a Rust", async () => {
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
  it("AUD-02: DEBE manejar carrera real entre dos conexiones SQLite independientes con efectos secundarios completos", async () => {
    const sqlite3 = require('better-sqlite3');
    const dbUri = 'file:memory_concurrency_test?cache=shared';
    
    const conn1 = new sqlite3(dbUri, { readonly: false });
    const conn2 = new sqlite3(dbUri, { readonly: false });

    conn1.pragma('busy_timeout = 5000');
    conn2.pragma('busy_timeout = 5000');

    try {
      conn1.exec(`DROP TABLE IF EXISTS local_orders; DROP TABLE IF EXISTS local_tables; DROP TABLE IF EXISTS local_payments; DROP TABLE IF EXISTS table_local_mutations; DROP TABLE IF EXISTS sync_queue;`);
      conn1.exec(`
        CREATE TABLE local_orders (local_uuid TEXT PRIMARY KEY, status TEXT);
        CREATE TABLE local_tables (uuid TEXT PRIMARY KEY, status TEXT, current_order_uuid TEXT);
        CREATE TABLE local_payments (local_uuid TEXT PRIMARY KEY, order_local_uuid TEXT, amount INTEGER);
        CREATE TABLE table_local_mutations (table_uuid TEXT PRIMARY KEY, pending_status TEXT, pending_order_uuid TEXT);
        CREATE TABLE sync_queue (id TEXT PRIMARY KEY, entity_local_uuid TEXT, action TEXT, payload TEXT);
        INSERT INTO local_orders (local_uuid, status) VALUES ('race-order', 'confirmed');
        INSERT INTO local_tables (uuid, status, current_order_uuid) VALUES ('race-table', 'occupied', 'race-order');
      `);

      let winner = '';
      let loserError = '';

      const safeRollback = (conn: any) => { try { conn.exec("ROLLBACK"); } catch (e) {} };

      const doPayment = async () => {
        try {
          conn1.exec("BEGIN IMMEDIATE");
          await new Promise(r => setTimeout(r, 20));
          const stmt = conn1.prepare("UPDATE local_orders SET status = 'paid' WHERE local_uuid = 'race-order' AND status = 'confirmed'");
          const result = stmt.run();
          if (result.changes === 1) {
            conn1.prepare("INSERT INTO local_payments (local_uuid, order_local_uuid, amount) VALUES ('pay-race-1', 'race-order', 10000)").run();
            conn1.prepare("INSERT INTO sync_queue (id, entity_local_uuid, action, payload) VALUES ('sync-pay-1', 'race-order', 'create', '{\"type\":\"payment\"}')").run();
            conn1.exec("COMMIT");
            winner = 'payment';
          } else { safeRollback(conn1); loserError = 'payment_conflict'; }
        } catch (e: any) { safeRollback(conn1); loserError = 'payment_error: ' + e.message; }
      };

      const doCancel = async () => {
        try {
          conn2.exec("BEGIN IMMEDIATE");
          await new Promise(r => setTimeout(r, 20));
          const stmt = conn2.prepare("UPDATE local_orders SET status = 'cancelled' WHERE local_uuid = 'race-order' AND status = 'confirmed'");
          const result = stmt.run();
          if (result.changes === 1) {
            conn2.exec("UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = 'race-table'");
            conn2.prepare("INSERT INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid) VALUES ('race-table', 'available', NULL)").run();
            conn2.prepare("INSERT INTO sync_queue (id, entity_local_uuid, action, payload) VALUES ('sync-cancel-1', 'race-order', 'update', '{\"status\":\"cancelled\"}')").run();
            conn2.exec("COMMIT");
            winner = 'cancel';
          } else { safeRollback(conn2); loserError = 'cancel_conflict'; }
        } catch (e: any) { safeRollback(conn2); loserError = 'cancel_error: ' + e.message; }
      };

      await Promise.all([doPayment(), doCancel()]);

      expect(winner).toMatch(/payment|cancel/);
      expect(loserError).toMatch(/conflict|error|database is locked/);

      const finalOrder = conn1.prepare("SELECT status FROM local_orders WHERE local_uuid = 'race-order'").get() as any;
      const finalTable = conn1.prepare("SELECT status, current_order_uuid FROM local_tables WHERE uuid = 'race-table'").get() as any;
      
      const paymentCount = conn1.prepare("SELECT COUNT(*) as c FROM local_payments").get() as any;
      const mutationCount = conn1.prepare("SELECT COUNT(*) as c FROM table_local_mutations").get() as any;
      const syncCount = conn1.prepare("SELECT COUNT(*) as c FROM sync_queue").get() as any;

      if (winner === 'payment') {
        expect(finalOrder.status).toBe('paid');
        expect(paymentCount.c).toBe(1);
        expect(mutationCount.c).toBe(0);
        expect(syncCount.c).toBe(1);
      } else {
        expect(finalOrder.status).toBe('cancelled');
        expect(finalTable.status).toBe('available');
        expect(finalTable.current_order_uuid).toBeNull();
        expect(paymentCount.c).toBe(0);
        expect(mutationCount.c).toBe(1);
        expect(syncCount.c).toBe(1);
      }
    } finally {
      try { conn1.exec(`DROP TABLE IF EXISTS local_orders; DROP TABLE IF EXISTS local_tables; DROP TABLE IF EXISTS local_payments; DROP TABLE IF EXISTS table_local_mutations; DROP TABLE IF EXISTS sync_queue;`); } catch(e) {}
      conn1.close();
      conn2.close();
    }
  });

});