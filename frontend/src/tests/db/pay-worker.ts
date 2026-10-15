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
      db.prepare("INSERT INTO local_payments (local_uuid, order_local_uuid, amount) VALUES ('pay-race-1', 'race-order', 10000)").run();
      db.prepare("INSERT INTO sync_queue (id, entity_local_uuid, action, payload) VALUES ('sync-pay-1', 'race-order', 'create', '{\"type\":\"payment\"}')").run();
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
