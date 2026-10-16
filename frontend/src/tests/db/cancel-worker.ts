import Database from 'better-sqlite3';

const dbPath = process.argv[2];
if (!dbPath) process.exit(1);

const db = new Database(dbPath);
db.pragma('busy_timeout = 10000');

try {
  db.transaction(() => {
    const start = Date.now();
    while (Date.now() - start < 150) { /* CPU block para solapar */ }
    
    const stmt = db.prepare("UPDATE local_orders SET status = 'cancelled' WHERE local_uuid = 'race-order' AND status = 'confirmed'");
    const result = stmt.run();
    
    if (result.changes === 1) {
      db.prepare("UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = 'race-table'").run();
      
      // INSERT completo con columnas NOT NULL requeridas por el esquema espejo
      db.prepare(`
        INSERT INTO table_local_mutations (
          table_uuid, company_id, branch_id, pending_status, pending_order_uuid, action, payload
        ) VALUES (
          'race-table', 'test-company', 'test-branch', 'available', NULL, 'update', '{"status":"available"}'
        )
      `).run();
      
      db.prepare(`
        INSERT INTO sync_queue (
          id, entity_type, entity_local_uuid, action, payload
        ) VALUES (
          'sync-cancel-1', 'order', 'race-order', 'update', '{"status":"cancelled"}'
        )
      `).run();
      
      console.log("WORKER_CANCEL:SUCCESS");
    } else {
      console.log("WORKER_CANCEL:CONFLICT");
    }
  })();
  process.exit(0);
} catch (e: any) {
  console.log("WORKER_CANCEL:ERROR:" + e.message);
  process.exit(1);
}
