import { localDb } from "./localDb";
import initialMigration from "./migrations/001_initial.sql?raw";
import tableMutationsMigration from "./migrations/002_table_local_mutations.sql?raw";

/**
 * Parser robusto para dividir SQL en statements individuales.
 * Maneja comentarios, strings y casos edge correctamente.
 */
function parseSqlStatements(sql: string): string[] {
  const statements: string[] = [];
  let current = "";
  let inString = false;
  let stringChar = "";
  let inLineComment = false;
  let inBlockComment = false;

  for (let i = 0; i < sql.length; i++) {
    const char = sql[i];
    const nextChar = sql[i + 1];

    // Manejo de comentarios de línea (--)
    if (!inString && !inBlockComment && char === "-" && nextChar === "-") {
      inLineComment = true;
      i++; // saltar el segundo '-'
      continue;
    }

    // Fin de comentario de línea
    if (inLineComment) {
      if (char === "\n") {
        inLineComment = false;
        current += char;
      }
      continue;
    }

    // Inicio de comentario de bloque (/* */)
    if (!inString && !inLineComment && char === "/" && nextChar === "*") {
      inBlockComment = true;
      i++; // saltar '*'
      continue;
    }

    // Fin de comentario de bloque
    if (inBlockComment) {
      if (char === "*" && nextChar === "/") {
        inBlockComment = false;
        i++; // saltar '/'
      }
      continue;
    }

    // Manejo de strings (comillas simples o dobles)
    if (!inString && (char === "'" || char === '"')) {
      inString = true;
      stringChar = char;
      current += char;
      continue;
    }

    // Fin de string
    if (inString) {
      current += char;
      if (char === stringChar && sql[i - 1] !== "\\") {
        inString = false;
      }
      continue;
    }

    // Semicolon fuera de string/comentario = fin de statement
    if (char === ";") {
      const trimmed = current.trim();
      if (trimmed.length > 0) {
        statements.push(trimmed);
      }
      current = "";
      continue;
    }

    // Caracter normal
    current += char;
  }

  // Último statement sin semicolon
  const lastTrimmed = current.trim();
  if (lastTrimmed.length > 0) {
    statements.push(lastTrimmed);
  }

  return statements;
}

/**
 * Ejecuta todas las migraciones pendientes.
 */
export async function runMigrations(): Promise<void> {
  const db = await localDb.getConnection();

  console.log("[Migrations] 🚀 Iniciando sistema de migraciones...");

  // Crear tabla de control de migraciones
  try {
    await db.execute(`
      CREATE TABLE IF NOT EXISTS migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
        checksum TEXT
      )
    `);
    console.log("[Migrations] ✅ Tabla migrations verificada");
  } catch (error) {
    console.error("[Migrations] ❌ Error creando tabla migrations:", error);
    throw error;
  }

  // Verificar migraciones ya aplicadas
  const applied = await db.select<Array<{ version: string }>>(
    "SELECT version FROM migrations ORDER BY version"
  );

  console.log(`[Migrations] 📋 Migraciones aplicadas: ${applied.length > 0 ? applied.map(m => m.version).join(", ") : "ninguna"}`);

  // Aplicar migración 001 si no está aplicada
  if (!applied.some(m => m.version === "001")) {
    console.log("[Migrations] 🚀 Aplicando migración 001_initial...");

    if (!initialMigration || initialMigration.trim().length === 0) {
      throw new Error("Archivo de migración vacío o no cargado correctamente");
    }

    console.log(`[Migrations] 📄 SQL cargado: ${initialMigration.length} caracteres`);

    // Parsear statements
    const statements = parseSqlStatements(initialMigration);
    console.log(`[Migrations] 🔍 Parsed ${statements.length} statements SQL`);

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

      // Saltar comentarios puros
      if (upper.startsWith("--") || upper.startsWith("/*")) {
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
          console.log(`[Migrations] ✓ Tabla creada: ${createMatch[1]}`);
        }

        // Detectar INSERT
        if (upper.startsWith("INSERT")) {
          console.log(`[Migrations] ✓ Datos insertados`);
        }

      } catch (err: any) {
        console.error(`[Migrations] ❌ Error en statement ${i + 1}/${statements.length}:`);
        console.error(`[Migrations] SQL: ${stmt.substring(0, 150)}${stmt.length > 150 ? "..." : ""}`);
        console.error(`[Migrations] Error: ${err.message}`);
        
        // Si es error de tabla existente, continuar
        if (err.message?.includes("already exists") || err.code === 1) {
          console.warn("[Migrations] ⚠️  Tabla ya existe, continuando...");
        } else {
          throw new Error(`Migración falló en statement ${i + 1}: ${err.message}`);
        }
      }
    }

    console.log(`[Migrations] ✅ Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log(`[Migrations] 📊 Tablas creadas: ${tablesCreated.join(", ")}`);

    // Marcar migración como aplicada
    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["001", `initial-${executed}-statements-${Date.now()}`]
    );

    // Verificación final de tablas críticas
    const criticalTables = [
      "sync_queue",
      "local_orders",
      "local_order_items",
      "local_payments",
      "local_cash_sessions",
      "local_tables",
      "local_categories",
      "local_products",
      "local_payment_methods",
      "sync_state"
    ];

    console.log("[Migrations] 🔍 Verificando tablas críticas...");
    for (const table of criticalTables) {
      try {
        const check = await db.select<{ name: string }[]>(
          "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
          [table]
        );
        if (check.length === 0) {
          throw new Error(`Tabla crítica NO se creó: ${table}`);
        }
        console.log(`[Migrations] ✅ Verificada: ${table}`);
      } catch (err: any) {
        console.error(`[Migrations] ❌ Fallo en verificación de ${table}:`, err.message);
        throw err;
      }
    }

    console.log("[Migrations] 🎉 Migración 001 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 001 ya está aplicada");

    // Aplicar migración 002 si no está aplicada (FASE 4: table_local_mutations)
    if (!applied.some(m => m.version === "002")) {
      console.log("[Migrations] 🚀 Aplicando migración 002_table_local_mutations...");

      if (!tableMutationsMigration || tableMutationsMigration.trim().length === 0) {
        throw new Error("Migración 002 vacía o no cargada");
      }

      const statements = parseSqlStatements(tableMutationsMigration);
      console.log(`[Migrations] 🔍 002: Parsed ${statements.length} statements SQL`);

      let executed = 0;
      let skipped = 0;

      for (let i = 0; i < statements.length; i++) {
        const stmt = statements[i];
        const upper = stmt.toUpperCase().trim();

        if (upper.startsWith("--") || upper.startsWith("/*") || stmt.trim().length === 0) {
          skipped++;
          continue;
        }

        try {
          await db.execute(stmt);
          executed++;
          const createMatch = stmt.match(/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["']?(\w+)/i);
          if (createMatch) {
            console.log(`[Migrations] ✓ Tabla creada: ${createMatch[1]}`);
          }
        } catch (err: any) {
          if (err.message?.includes("already exists") || err.code === 1) {
            console.warn(`[Migrations] ⚠️  Objeto ya existe, continuando`);
            skipped++;
          } else {
            throw new Error(`Migración 002 falló en statement ${i + 1}: ${err.message}`);
          }
        }
      }

      console.log(`[Migrations] ✅ 002 Resumen: ${executed} ejecutados, ${skipped} saltados`);

      await db.execute(
        "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
        ["002", `table-mutations-${executed}-statements-${Date.now()}`]
      );

      // Verificar que la tabla se creó
      const check = await db.select<{ name: string }[]>(
        "SELECT name FROM sqlite_master WHERE type='table' AND name = 'table_local_mutations'"
      );
      if (check.length === 0) {
        throw new Error("Tabla table_local_mutations no se creó");
      }
      console.log("[Migrations] ✅ Verificada: table_local_mutations");
      console.log("[Migrations] 🎉 Migración 002 aplicada correctamente");
    } else {
      console.log("[Migrations] ✅ Migración 002 ya está aplicada");
    }

    // Verificar integridad de tablas críticas
    const criticalTables = ["sync_queue", "local_orders", "local_products", "table_local_mutations"];
    let allOk = true;

    for (const table of criticalTables) {
      try {
        const check = await db.select<{ name: string }[]>(
          "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
          [table]
        );
        if (check.length === 0) {
          console.error(`[Migrations] ❌ Tabla faltante: ${table}`);
          allOk = false;
        }
      } catch (err) {
        console.error(`[Migrations] ❌ Error verificando ${table}:`, err);
        allOk = false;
      }
    }

    if (!allOk) {
      console.warn("[Migrations] ⚠️  Faltan tablas críticas, re-aplicando migración...");
      await db.execute("DELETE FROM migrations WHERE version = '001'");
      return runMigrations();
    }

    console.log("[Migrations] ✅ Integridad de base de datos verificada");
  }
}

/**
 * Verifica que la base de datos esté lista.
 */
export async function verifyDatabase(): Promise<boolean> {
  try {
    const db = await localDb.getConnection();
    await db.select("SELECT 1 as ok");
    return true;
  } catch (error) {
    console.error("[DB] Error verificando base de datos:", error);
    return false;
  }
}
