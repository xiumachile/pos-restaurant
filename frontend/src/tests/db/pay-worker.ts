import Database from 'better-sqlite3';

const dbPath = process.argv[2];
if (!dbPath) process.exit(1);

const db = new Database(dbPath);
db.pragma('busy_timeout = 10000'); // Esperar hasta 10s por el lock

try {
  db.transaction(() => {
    // Simular latencia de red/procesamiento para forzar la carrera
    const start = Date.now();
    while (Date.now() - start < 150) { /* CPU block para solapar */ }
    
    const stmt = db.prepare("UPDATE local_orders SET status = 'paid' WHERE local_uuid = 'race-order' AND status = 'confirmed'");
    const result = stmt.run();
    
    if (result.changes === 1) {
      // INSERT completo con columnas NOT NULL requeridas por el esquema espejo
      db.prepare(`
        INSERT INTO local_payments (
          local_uuid, company_id, branch_id, order_local_uuid, amount, 
          payment_method, idempotency_key, status, sync_status
        ) VALUES (
          'pay-race-1', 'test-company', 'test-branch', 'race-order', 10000, 
          'cash', 'idem-pay-1', 'pending', 'pending'
        )
      `).run();
      
      db.prepare(`
        INSERT INTO sync_queue (
          id, entity_type, entity_local_uuid, action, payload
        ) VALUES (
          'sync-pay-1', 'payment', 'pay-race-1', 'create', '{"type":"payment"}'
        )
      `).run();
      
      console.log("WORKER_PAY:SUCCESS");
    } else {
      console.log("WORKER_PAY:CONFLICT");
    }
  })();
  process.exit(0);
} catch (e: any) {
  console.log("WORKER_PAY:ERROR:" + e.message);
  process.exit(1);
}
