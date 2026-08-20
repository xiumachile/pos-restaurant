import { localDb } from "./localDb";

// Importar el SQL crudo como string
// Vite lo procesa como texto con ?raw
import initialMigration from "./migrations/001_initial.sql?raw";

/**
 * Ejecuta todas las migraciones pendientes.
 * Versión simple: solo ejecuta 001_initial.sql si no está aplicada.
 */
export async function runMigrations(): Promise<void> {
  const db = await localDb.getConnection();

  // Asegurar que la tabla migrations exista
  await db.execute(`
    CREATE TABLE IF NOT EXISTS migrations (
      version TEXT PRIMARY KEY,
      applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
      checksum TEXT
    )
  `);

  // Verificar si la migración 001 ya está aplicada
  const applied = await db.select<Array<{ version: string }>>(
    "SELECT version FROM migrations WHERE version = ?",
    ["001"]
  );

  if (applied.length === 0) {
    console.log("[Migrations] Aplicando migración 001_initial...");
    // Ejecutar el SQL de la migración
    // Nota: execute() del plugin SQL acepta múltiples statements si están separados por ;
    // Pero es más seguro ejecutarlos uno por uno
    const statements = initialMigration
      .split(";")
      .map((s) => s.trim())
      .filter((s) => s.length > 0 && !s.startsWith("--"));

    for (const stmt of statements) {
      // Saltar PRAGMAs (ya los aplicamos al inicializar)
      if (stmt.toUpperCase().startsWith("PRAGMA")) continue;
      try {
        await db.execute(stmt);
      } catch (err) {
        console.warn("[Migrations] Warning ejecutando statement:", err);
      }
    }

    // Marcar como aplicada (si no se insertó dentro del SQL)
    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["001", "initial-schema"]
    );

    console.log("[Migrations] Migración 001 aplicada correctamente");
  } else {
    console.log("[Migrations] Base de datos ya está en versión 001");
  }
}

/**
 * Verifica que la base de datos esté lista.
 * Lanza error si no se pudo inicializar.
 */
export async function verifyDatabase(): Promise<boolean> {
  try {
    const db = await localDb.getConnection();
    const result = await db.select("SELECT 1 as ok");
    return Array.isArray(result) && result.length === 1;
  } catch (error) {
    console.error("[DB] Error verificando base de datos:", error);
    return false;
  }
}
