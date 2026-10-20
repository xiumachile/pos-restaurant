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
      
      -- Esquema espejo completo del dominio (AUD-02.1)
      CREATE TABLE local_orders (
        local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
        terminal_id TEXT, table_id TEXT, order_number TEXT NOT NULL, order_type TEXT DEFAULT 'dine_in',
        status TEXT DEFAULT 'draft', subtotal REAL DEFAULT 0, discount_total REAL DEFAULT 0,
        tax_total REAL DEFAULT 0, tip_amount REAL DEFAULT 0, grand_total REAL DEFAULT 0,
        amount_due REAL DEFAULT 0, guest_count INTEGER DEFAULT 1, waiter_id TEXT, waiter_name TEXT,
        notes TEXT, idempotency_key TEXT UNIQUE NOT NULL, sync_status TEXT DEFAULT 'pending',
        customer_name TEXT, customer_phone TEXT, delivery_address TEXT, delivery_notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
      );
      CREATE TABLE local_tables (
        uuid TEXT PRIMARY KEY, table_number TEXT NOT NULL, status TEXT DEFAULT 'available',
        current_order_uuid TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
        last_updated TEXT DEFAULT CURRENT_TIMESTAMP
      );
      CREATE TABLE local_payments (
        local_uuid TEXT PRIMARY KEY, company_id TEXT NOT NULL, branch_id TEXT NOT NULL,
        order_local_uuid TEXT, payment_method TEXT NOT NULL, payment_method_uuid TEXT,
        amount INTEGER NOT NULL, tip_amount INTEGER DEFAULT 0, total_amount INTEGER DEFAULT 0,
        reference_code TEXT, notes TEXT, status TEXT DEFAULT 'pending', idempotency_key TEXT UNIQUE NOT NULL,
        sync_status TEXT DEFAULT 'pending', created_at TEXT DEFAULT CURRENT_TIMESTAMP
      );
      CREATE TABLE table_local_mutations (
        table_uuid TEXT PRIMARY KEY, pending_status TEXT NOT NULL, pending_order_uuid TEXT,
        company_id TEXT NOT NULL, branch_id TEXT NOT NULL, action TEXT DEFAULT 'update',
        payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
      );
      CREATE TABLE sync_queue (
        id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, entity_type TEXT,
        entity_local_uuid TEXT, action TEXT, payload TEXT, sync_status TEXT, created_at TEXT
      );
      
      INSERT INTO local_orders (local_uuid, company_id, branch_id, table_id, order_number, status, amount_due, grand_total, idempotency_key) 
      VALUES ('race-order', 'test-company', 'test-branch', 'race-table', 'ORD-001', 'confirmed', 10000, 10000, 'idem-order-001');
      INSERT INTO local_tables (uuid, table_number, status, current_order_uuid, company_id, branch_id) 
      VALUES ('race-table', '1', 'occupied', 'race-order', 'test-company', 'test-branch');
    `);
    db.close();

    // 2. Ejecutar competencia real desde procesos hijos independientes del SO
    const workerDir = path.dirname(__filename);
    const payPromise = execAsync(`npx tsx ${path.join(workerDir, 'pay-worker.ts')} ${dbPath}`);
    const cancelPromise = execAsync(`npx tsx ${path.join(workerDir, 'cancel-worker.ts')} ${dbPath}`);

    const results = await Promise.allSettled([payPromise, cancelPromise]);
    
    // Capturar tanto stdout como stderr para depuración en CI
    const payResult = results[0].status === 'fulfilled' ? results[0].value : { stdout: '', stderr: JSON.stringify(results[0].reason) };
    const cancelResult = results[1].status === 'fulfilled' ? results[1].value : { stdout: '', stderr: JSON.stringify(results[1].reason) };

    const payOutput = payResult.stdout + (payResult.stderr ? `\nSTDERR: ${payResult.stderr}` : '');
    const cancelOutput = cancelResult.stdout + (cancelResult.stderr ? `\nSTDERR: ${cancelResult.stderr}` : '');

    // 3. Verificar que exactamente uno tuvo éxito a nivel de dominio
    const paySuccess = payOutput.includes("WORKER_PAY:SUCCESS");
    const cancelSuccess = cancelOutput.includes("WORKER_CANCEL:SUCCESS");
    
    // DEBUG: Si ambos fallan, imprimir la salida real para ver qué pasó en el CI
    if (!paySuccess && !cancelSuccess) {
      console.error("=== 🚨 DEBUG CONCURRENCY TEST FAILED 🚨 ===");
      console.error("Pay Worker Output:", payOutput);
      console.error("Cancel Worker Output:", cancelOutput);
      console.error("============================================");
    }

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
