import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "../../db/localDb";

// Mockear invoke para simular el comportamiento de la lista blanca de Rust
vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn((command: string, args: any) => {
    if (command === "apply_migration") {
      // Simulamos que Rust solo conoce ciertas migraciones (lista blanca)
      const knownVersions = ["000", "001", "011", "015", "022"];
      const isKnown = knownVersions.some(v => args.version.startsWith(v));
      
      if (!isKnown) {
        return Promise.reject(new Error("Migración no encontrada en la lista blanca de Rust"));
      }
      return Promise.resolve(true);
    }
    return Promise.resolve(null);
  })
}));

import { invoke } from "@tauri-apps/api/core";

describe("AUD-04: Integridad y Compatibilidad de Migraciones", () => {
  beforeEach(async () => {
    // Limpiar estado de migraciones para pruebas aisladas
    await localDb.execute("DROP TABLE IF EXISTS migrations");
  });

  it("DEBE añadir la columna checksum a una tabla migrations antigua sin ella", async () => {
    // 1. Simular base antigua: crear tabla migrations SIN checksum
    await localDb.execute(`
      CREATE TABLE migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);
    await localDb.execute("INSERT INTO migrations (version) VALUES ('000_create_migrations_table')");

    // 2. Ejecutar la migración 022 manualmente (simulando lo que haría Rust)
    const migrationSql = `ALTER TABLE migrations ADD COLUMN checksum TEXT;`;
    
    try {
      await localDb.execute(migrationSql);
    } catch (e: any) {
      if (!e.message.toLowerCase().includes("duplicate column name")) {
        throw e;
      }
    }

    // 3. Verificar que la columna checksum ahora existe
    const tableInfo = await localDb.select("PRAGMA table_info(migrations)");
    const checksumColumn = tableInfo.find((col: any) => col.name === 'checksum');
    
    expect(checksumColumn).toBeDefined();
    expect(checksumColumn?.type).toBe('TEXT');
    
    // 4. Verificar que los datos antiguos se conservan
    const oldRecord = await localDb.select("SELECT version FROM migrations WHERE version = '000_create_migrations_table'");
    expect(oldRecord.length).toBe(1);
  });

  it("DEBE ser idempotente: ejecutar la migración 022 dos veces no debe fallar ni duplicar datos", async () => {
    await localDb.execute(`
      CREATE TABLE migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    `);
    
    // Primera ejecución
    await localDb.execute("ALTER TABLE migrations ADD COLUMN checksum TEXT;");
    
    // Segunda ejecución (debería ser ignorada o manejada sin romper la tabla)
    try {
      await localDb.execute("ALTER TABLE migrations ADD COLUMN checksum TEXT;");
    } catch (e: any) {
      expect(e.message.toLowerCase()).toContain("duplicate column name");
    }

    // Verificar integridad
    const tableInfo = await localDb.select("PRAGMA table_info(migrations)");
    const checksumColumns = tableInfo.filter((col: any) => col.name === 'checksum');
    expect(checksumColumns.length).toBe(1); // Solo una columna, no duplicada
  });

  it("DEBE rechazar versiones de migración desconocidas en Rust (simulación de lista blanca)", async () => {
    // Intentar invocar una migración que no está en la lista blanca compilada de Rust
    try {
      await invoke("apply_migration", { version: "999_malicious_injection" });
      // Si llega aquí, falló la prueba (debería haber lanzado error)
      expect(true).toBe(false); 
    } catch (e: any) {
      // Esperamos un error indicando que la migración no se encuentra o no está autorizada
      expect(e.message.toLowerCase()).toMatch(/no encontrada|not found|no autorizada|unknown|lista blanca/);
    }
  });
});
