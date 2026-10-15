import { describe, it, expect, beforeEach } from "vitest";

describe("AUD: Concurrencia y Consistencia de Estado (Pago vs Cancelación)", () => {
  it("AUD-02: DEBE manejar carrera real entre dos conexiones SQLite independientes", async () => {
    const sqlite3 = require('better-sqlite3');
    
    // Usar una base de datos en memoria compartida única para esta prueba
    const dbUri = 'file:memory_concurrency_test?mode=memory&cache=shared';
    
    const conn1 = new sqlite3(dbUri, { readonly: false });
    const conn2 = new sqlite3(dbUri, { readonly: false });

    conn1.pragma('busy_timeout = 5000');
    conn2.pragma('busy_timeout = 5000');

    try {
      // Crear esquema en la base de datos compartida
      conn1.exec(`
        DROP TABLE IF EXISTS local_orders;
        DROP TABLE IF EXISTS local_tables;
        DROP TABLE IF EXISTS local_payments;
        DROP TABLE IF EXISTS table_local_mutations;
        DROP TABLE IF EXISTS sync_queue;
        
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

      const safeRollback = (conn: any) => { 
        try { conn.exec("ROLLBACK"); } catch (e) {} 
      };

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
          } else { 
            safeRollback(conn1); 
            loserError = 'payment_conflict'; 
          }
        } catch (e: any) { 
          safeRollback(conn1); 
          loserError = 'payment_error: ' + e.message; 
        }
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
          } else { 
            safeRollback(conn2); 
            loserError = 'cancel_conflict'; 
          }
        } catch (e: any) { 
          safeRollback(conn2); 
          loserError = 'cancel_error: ' + e.message; 
        }
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
      try { 
        conn1.exec(`
          DROP TABLE IF EXISTS local_orders;
          DROP TABLE IF EXISTS local_tables;
          DROP TABLE IF EXISTS local_payments;
          DROP TABLE IF EXISTS table_local_mutations;
          DROP TABLE IF EXISTS sync_queue;
        `); 
      } catch(e) {}
      conn1.close();
      conn2.close();
    }
  });
});
