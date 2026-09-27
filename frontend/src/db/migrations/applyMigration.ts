function simpleHash(str: string): string {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash;
  }
  return hash.toString(16);
}

import { parseSqlStatements } from "../utils/sqlParser";

interface MigrationResult {
  executed: number;
  skipped: number;
  tablesCreated: string[];
}

/**
 * Ejecuta una migración SQL de forma genérica.
 * Encapsula toda la lógica de parseo, ejecución y logging.
 */
export async function applyMigration(
  db: any,
  version: string,
  migrationSql: string,
  applied: Array<{ version: string }>
): Promise<void> {
  // Verificar si ya fue aplicada
  if (applied.some(m => m.version === version)) {
    console.log(`[Migrations] ✅ Migración ${version} ya está aplicada`);
    return;
  }

  console.log(`[Migrations] 🚀 Aplicando migración ${version}...`);

  if (!migrationSql || migrationSql.trim().length === 0) {
    throw new Error(`Migración ${version} vacía o no cargada correctamente`);
  }

  console.log(`[Migrations] 📄 SQL cargado: ${migrationSql.length} caracteres`);

  const statements = parseSqlStatements(migrationSql);
  console.log(`[Migrations] 🔍 ${version}: Parsed ${statements.length} statements SQL`);

  let executed = 0;
  let skipped = 0;
  const tablesCreated: string[] = [];

  for (let i = 0; i < statements.length; i++) {
    const stmt = statements[i];
    const upper = stmt.toUpperCase().trim();

    // Saltar PRAGMAs (ya aplicados en localDb.ts)
    if (upper.startsWith("PRAGMA")) {
      console.log(`[Migrations] ⏭️  Saltando PRAGMA`);
      skipped++;
      continue;
    }

    // Saltar comentarios puros o statements vacíos
    if (upper.startsWith("--") || upper.startsWith("/*") || stmt.trim().length === 0) {
      skipped++;
      continue;
    }

    try {
      await db.execute(stmt);
      executed++;

      // Detectar y loguear CREATE TABLE
      const createMatch = stmt.match(/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["']?(\w+)/i);
      if (createMatch) {
        tablesCreated.push(createMatch[1]);
        console.log(`[Migrations] ✓ ${version}: Tabla creada: ${createMatch[1]}`);
      }

      // Detectar INSERT
      if (upper.startsWith("INSERT")) {
        console.log(`[Migrations] ✓ ${version}: Datos insertados`);
      }

    } catch (err: any) {
      console.error(`[Migrations] ❌ Error en ${version} statement ${i + 1}/${statements.length}:`);
      console.error(`[Migrations] SQL: ${stmt.substring(0, 150)}${stmt.length > 150 ? "..." : ""}`);
      console.error(`[Migrations] Error: ${err.message}`);
      
      // Ignorar errores de esquema para compatibilidad con migraciones legacy
      // Replicar comportamiento del código original:
      // - Migraciones 001-009, 016, 017: ignoran "already exists"
      // - Migraciones 011-015: ignoran "already exists", "duplicate", "duplicate column"
      // - Migración 003: ignora "duplicate column", "already exists", "no such table"
      const errMsg = String(err?.message || err || "unknown");
      const isNonCritical = 
        errMsg.includes("already exists") ||
        errMsg.includes("duplicate column") ||
        errMsg.includes("duplicate column name") ||
        errMsg.includes("duplicate") ||
        errMsg.includes("no such table");
      
      if (isNonCritical) {
        console.warn(`[Migrations] ⚠️  ${version}: Statement ${i + 1} ignorado (no crítico): ${errMsg}`);
        skipped++;
      } else {
        throw new Error(`Migración ${version} falló en statement ${i + 1}: ${errMsg}`);
      }
    }
  }

  console.log(`[Migrations] ✅ ${version} Resumen: ${executed} ejecutados, ${skipped} saltados`);
  if (tablesCreated.length > 0) {
    console.log(`[Migrations] 📊 ${version} Tablas creadas: ${tablesCreated.join(", ")}`);
  }

  // Marcar migración como aplicada
  await db.execute(
    "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
    [version, `${version}-${executed}-statements-${Date.now()}`]
  );

  console.log(`[Migrations] 🎉 Migración ${version} aplicada correctamente`);
}
