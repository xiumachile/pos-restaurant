import { localDb } from "./src/db/localDb";

async function test() {
  try {
    await localDb.getConnection();
    // Omitimos runMigrations() para evitar el error de tsx con archivos .sql
    
    const testId = "test-id-" + Date.now();
    
    console.log("📝 Insertando item de prueba...");
    await localDb.execute(
      "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, attempts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [testId, "c1", "b1", "order", "uuid-1", "create", "{}", "pending", 0]
    );
    
    console.log("✅ Antes de UPDATE:");
    const before = await localDb.select("SELECT id, attempts, next_retry_at FROM sync_queue WHERE id = ?", [testId]);
    console.log(before);
    
    console.log("\n⏳ Ejecutando UPDATE con datetime('now', '+1 hour')...");
    await localDb.execute(
      "UPDATE sync_queue SET attempts = 1, next_retry_at = datetime('now', '+1 hour') WHERE id = ?",
      [testId]
    );
    
    console.log("✅ Después de UPDATE:");
    const after = await localDb.select("SELECT id, attempts, next_retry_at FROM sync_queue WHERE id = ?", [testId]);
    console.log(after);
    
    await localDb.execute("DELETE FROM sync_queue WHERE id = ?", [testId]);
    console.log("\n🧹 Limpieza completada.");
    
  } catch (error) {
    console.error("❌ Error en la prueba:", error);
  } finally {
    process.exit(0);
  }
}

test();
