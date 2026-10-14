import { describe, it, expect, beforeEach } from "vitest";
import { localDb } from "../../db/localDb";
import { applyMigration } from "../../db/nativeDb";
import migrationsChecksumMigration from "../../db/migrations/022_add_migrations_checksum.sql?raw";

describe("AUD-04: Integridad y Compatibilidad de Migraciones (Pruebas Reales)", () => {
  beforeEach(async () => {
    // Limpiar estado de migraciones para pruebas aisladas
    await localDb.execute("DROP TABLE IF EXISTS migrations");
  });

  it("DEBE añadir la columna checksum a una tabla migrations antigua sin ella (simulando base legacy)", async () => {
    // 1. Simular base antigua: crear tabla migrations SIN checksum (como lo hacía la 000 original antes de la corrección)
    await localDb.execute(`
      CREATE TABLE migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);
    await localDb.execute("INSERT INTO migrations (version) VALUES ('000_create_migrations_table')");

    // 2. Ejecutar SOLO la migración 022 para reparar el esquema
    await applyMigration("022", migrationsChecksumMigration);

    // 3. Verificar que la columna checksum ahora existe
    const tableInfo = await localDb.select("PRAGMA table_info(migrations)");
    const checksumColumn = tableInfo.find((col: any) => col.name === 'checksum');
    
    expect(checksumColumn).toBeDefined();
    expect(checksumColumn?.type).toBe('TEXT');
    
    // 4. Verificar que los datos antiguos se conservan
    const oldRecord = await localDb.select("SELECT version FROM migrations WHERE version = '000_create_migrations_table'");
    expect(oldRecord.length).toBe(1);
  });

  it("DEBE ser idempotente: ejecutar la migración 022 dos veces no debe fallar ni duplicar columnas", async () => {
    // 1. Crear tabla base
    await localDb.execute(`
      CREATE TABLE migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);
    
    // 2. Primera ejecución
    await applyMigration("022", migrationsChecksumMigration);
    
    // 3. Segunda ejecución (debe ser ignorada gracias al manejo de 'duplicate column name' en applyMigration)
    await applyMigration("022", migrationsChecksumMigration);

    // 4. Verificar integridad: solo debe haber una columna checksum
    const tableInfo = await localDb.select("PRAGMA table_info(migrations)");
    const checksumColumns = tableInfo.filter((col: any) => col.name === 'checksum');
    expect(checksumColumns.length).toBe(1);
  });

  it("DEBE funcionar sin problemas si la tabla ya tiene la columna checksum (instalaciones nuevas)", async () => {
    // 1. Simular instalación nueva: la tabla ya tiene checksum (gracias a la 000 actualizada)
    await localDb.execute(`
      CREATE TABLE migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
        checksum TEXT
      )
    `);
    
    // 2. Ejecutar la migración 022 (debe ser ignorada silenciosamente)
    await applyMigration("022", migrationsChecksumMigration);

    // 3. Verificar integridad
    const tableInfo = await localDb.select("PRAGMA table_info(migrations)");
    const checksumColumns = tableInfo.filter((col: any) => col.name === 'checksum');
    expect(checksumColumns.length).toBe(1);
  });
});
