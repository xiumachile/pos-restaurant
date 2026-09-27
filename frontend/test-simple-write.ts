import { localDb } from "./src/db/localDb";

async function testSimpleWrite() {
  console.log("=== TEST: Escritura simple sin transacción ===");
  
  try {
    // Inicializar BD
    await localDb.initialize();
    console.log("✓ BD inicializada");
    
    // Escritura simple sin transacción
    await localDb.execute(
      "INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload) VALUES (?, ?, ?, ?, ?, ?, ?)",
      ["test-" + Date.now(), "1", "1", "test", "test-uuid", "create", "{}"]
    );
    console.log("✓ Escritura simple exitosa");
    
    // Verificar
    const result = await localDb.select("SELECT COUNT(*) as count FROM sync_queue WHERE id LIKE 'test-%'");
    console.log("✓ Registros encontrados:", result[0].count);
    
    // Limpiar
    await localDb.execute("DELETE FROM sync_queue WHERE id LIKE 'test-%'");
    console.log("✓ Test completado SIN errores");
    
  } catch (error: any) {
    console.error("✗ ERROR:", error.message);
    console.error("Stack:", error.stack);
  }
}

testSimpleWrite();
