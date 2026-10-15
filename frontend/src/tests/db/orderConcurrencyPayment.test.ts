import { describe, it, expect, beforeEach } from "vitest";
import { exec } from "child_process";
import { promisify } from "util";
import Database from 'better-sqlite3';
import * as path from "path";

const execAsync = promisify(exec);

describe("AUD: Concurrencia y Consistencia de Estado (Pago vs Cancelación)", () => {
  it("AUD-02: DEBE manejar carrera real entre procesos independientes a nivel de SO", async () => {
    const dbPath = path.join(__dirname, 'concurrency-real-test.db');
    
    // 1. Preparar base de datos compartida a nivel de sistema de archivos
    const db = new Database(dbPath);
    db.exec(`
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
    db.close();

    // 2. Ejecutar competencia real desde procesos hijos independientes del SO
    const workerDir = path.dirname(__filename);
    const payPromise = execAsync(`npx tsx ${path.join(workerDir, 'pay-worker.ts')} ${dbPath}`);
    const cancelPromise = execAsync(`npx tsx ${path.join(workerDir, 'cancel-worker.ts')} ${dbPath}`);

    const results = await Promise.allSettled([payPromise, cancelPromise]);
    
    const payOutput = results[0].status === 'fulfilled' ? results[0].value.stdout : '';
    const cancelOutput = results[1].status === 'fulfilled' ? results[1].value.stdout : '';

    // 3. Verificar que exactamente uno tuvo éxito a nivel de dominio
    const paySuccess = payOutput.includes("WORKER_PAY:SUCCESS");
    const cancelSuccess = cancelOutput.includes("WORKER_CANCEL:SUCCESS");
    
    expect(paySuccess || cancelSuccess).toBe(true);
    expect(paySuccess && cancelSuccess).toBe(false); // Exclusión mutua garantizada por SQLite IMMEDIATE

    // 4. Verificación exhaustiva de ausencia de efectos huérfanos en la DB final
    const finalDb = new Database(dbPath);
    
    const order = finalDb.prepare("SELECT status FROM local_orders WHERE local_uuid = 'race-order'").get() as any;
    const table = finalDb.prepare("SELECT status, current_order_uuid FROM local_tables WHERE uuid = 'race-table'").get() as any;
    const paymentCount = (finalDb.prepare("SELECT COUNT(*) as c FROM local_payments").get() as any).c;
    const mutationCount = (finalDb.prepare("SELECT COUNT(*) as c FROM table_local_mutations").get() as any).c;
    const syncCount = (finalDb.prepare("SELECT COUNT(*) as c FROM sync_queue").get() as any).c;

    if (paySuccess) {
      expect(order.status).toBe('paid');
      expect(paymentCount).toBe(1);
      expect(mutationCount).toBe(0); // El pago no genera mutación de mesa en este flujo
      expect(syncCount).toBe(1);
    } else {
      expect(order.status).toBe('cancelled');
      expect(table.status).toBe('available');
      expect(table.current_order_uuid).toBeNull();
      expect(paymentCount).toBe(0); // CERO pagos huérfanos del proceso perdedor
      expect(mutationCount).toBe(1);
      expect(syncCount).toBe(1);
    }
    
    finalDb.close();
    
    // Limpieza
    try { require('fs').unlinkSync(dbPath); } catch(e) {}
  });
});
