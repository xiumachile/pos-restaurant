import { localDb } from "./localDb";
import initialMigration from "./migrations/001_initial.sql?raw";
import tableMutationsMigration from "./migrations/002_table_local_mutations.sql?raw";
import backendIdMigration from "./migrations/003_add_backend_id_to_categories.sql?raw";
import localBillsMigration from "./migrations/004_create_local_bills.sql?raw";
import localCashMovementsMigration from "./migrations/005_create_local_cash_movements.sql?raw";
import localCashSessionsCompanyMigration from "./migrations/006_add_company_to_local_cash_sessions.sql?raw";
import offlineEventsMigration from "./migrations/007_create_offline_events.sql?raw";

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

  // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 001: Tablas base (INDEPENDIENTE)
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "001")) {
    console.log("[Migrations] 🚀 Aplicando migración 001_initial...");

    if (!initialMigration || initialMigration.trim().length === 0) {
      throw new Error("Archivo de migración vacío o no cargado correctamente");
    }

    console.log(`[Migrations] 📄 SQL cargado: ${initialMigration.length} caracteres`);

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
          throw new Error(`Migración 001 falló en statement ${i + 1}: ${err.message}`);
        }
      }
    }

    console.log(`[Migrations] ✅ 001 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log(`[Migrations] 📊 001 Tablas creadas: ${tablesCreated.join(", ")}`);

    // Marcar migración como aplicada
    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["001", `initial-${executed}-statements-${Date.now()}`]
    );

    console.log("[Migrations] 🎉 Migración 001 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 001 ya está aplicada");
  }

  // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 002: table_local_mutations (INDEPENDIENTE)
  // ═══════════════════════════════════════════════════════════════
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
          console.log(`[Migrations] ✓ 002: Tabla creada: ${createMatch[1]}`);
        }
        const indexMatch = stmt.match(/CREATE\s+INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?["']?(\w+)/i);
        if (indexMatch) {
          console.log(`[Migrations] ✓ 002: Índice creado: ${indexMatch[1]}`);
        }
      } catch (err: any) {
        if (err.message?.includes("already exists") || err.code === 1) {
          console.warn(`[Migrations] ⚠️  002: Objeto ya existe, continuando`);
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

    // Verificar que la tabla se creó (usar localDb.select que retorna T[])
    const check = await localDb.select<{ name: string }>(
      "SELECT name FROM sqlite_master WHERE type='table' AND name = 'table_local_mutations'"
    );
    if (check.length === 0) {
      throw new Error("Tabla table_local_mutations no se creó");
    }
    console.log("[Migrations] ✅ 002: Verificada: table_local_mutations");
    console.log("[Migrations] 🎉 Migración 002 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 002 ya está aplicada");
  }

  // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 003: Agregar backend_id a local_categories
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "003")) {
    console.log("[Migrations] 🚀 Aplicando migración 003_add_backend_id...");

    if (!backendIdMigration || backendIdMigration.trim().length === 0) {
      throw new Error("Migración 003 vacía o no cargada");
    }

    console.log(`[Migrations] 📄 003 SQL: ${backendIdMigration.length} caracteres`);

    const statements = parseSqlStatements(backendIdMigration);
    console.log(`[Migrations] 🔍 003: Parsed ${statements.length} statements`);

    let executed = 0;
    let skipped = 0;

    for (let i = 0; i < statements.length; i++) {
      const stmt = statements[i];
      try {
        await db.execute(stmt);
        executed++;

        if (stmt.toUpperCase().includes("ALTER TABLE")) {
          console.log(`[Migrations] ✓ 003: Columna backend_id agregada`);
        } else if (stmt.toUpperCase().includes("CREATE INDEX")) {
          const indexMatch = stmt.match(/CREATE INDEX\s+\w+\s+(\w+)/i);
          console.log(`[Migrations] ✓ 003: Índice creado: ${indexMatch?.[1] || "unknown"}`);
        }
      } catch (err: any) {
        const errMsg = String(err?.message || err || "unknown");
        // Ignorar errores de columna duplicada (ya existe) o tabla no encontrada
        if (errMsg.includes("duplicate column") || 
            errMsg.includes("already exists") || 
            errMsg.includes("no such table") ||
            errMsg.includes("duplicate column name")) {
          skipped++;
          console.warn(`[Migrations] ⚠️  003: Statement ${i + 1} ignorado (ya existe): ${errMsg}`);
          continue;
        } else {
          console.error(`[Migrations] ❌ 003: Error en statement ${i + 1}:`, err);
          throw new Error(`Migración 003 falló en statement ${i + 1}: ${errMsg}`);
        }
      }
    }

    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["003", `backend-id-${executed}-statements-${Date.now()}`]
    );

    // Verificar que la columna se creó (o ya existía)
    try {
      const check = await localDb.select<{ name: string }>(
        "PRAGMA table_info(local_categories)"
      );
      const hasBackendId = check.some((col: any) => col.name === "backend_id");
      if (!hasBackendId) {
        console.warn("[Migrations] ⚠️  003: backend_id no encontrado en PRAGMA, pero la migración continuó");
      } else {
        console.log("[Migrations] ✅ 003: Verificada: backend_id en local_categories");
      }
    } catch (verifyErr: any) {
      console.warn("[Migrations] ⚠️  003: No se pudo verificar con PRAGMA:", verifyErr?.message);
    }
    console.log(`[Migrations] ✅ 003 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log("[Migrations] 🎉 Migración 003 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 003 ya está aplicada");
  }

  // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 004: local_bills (INDEPENDIENTE)
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "004")) {
    console.log("[Migrations] 🚀 Aplicando migración 004_create_local_bills...");

    if (!localBillsMigration || localBillsMigration.trim().length === 0) {
      throw new Error("Migración 004 vacía o no cargada");
    }

    const statements = parseSqlStatements(localBillsMigration);
    console.log(`[Migrations] 🔍 004: Parsed ${statements.length} statements SQL`);

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
          console.log(`[Migrations] ✓ 004: Tabla creada: ${createMatch[1]}`);
        }
        const indexMatch = stmt.match(/CREATE\s+INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?["']?(\w+)/i);
        if (indexMatch) {
          console.log(`[Migrations] ✓ 004: Índice creado: ${indexMatch[1]}`);
        }
      } catch (err: any) {
        const errMsg = String(err?.message || err || "unknown");
        if (errMsg.includes("already exists") || err.code === 1) {
          console.warn(`[Migrations] ⚠️  004: Objeto ya existe, continuando`);
          skipped++;
        } else {
          throw new Error(`Migración 004 falló en statement ${i + 1}: ${errMsg}`);
        }
      }
    }

    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["004", `local-bills-${executed}-statements-${Date.now()}`]
    );

    console.log(`[Migrations] ✅ 004 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log("[Migrations] 🎉 Migración 004 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 004 ya está aplicada");
  }

    // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 005: local_cash_movements (INDEPENDIENTE)
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "005")) {
    console.log("[Migrations] 🚀 Aplicando migración 005_create_local_cash_movements...");

    if (!localCashMovementsMigration || localCashMovementsMigration.trim().length === 0) {
      throw new Error("Migración 005 vacía o no cargada");
    }

    const statements = parseSqlStatements(localCashMovementsMigration);
    console.log(`[Migrations] 🔍 005: Parsed ${statements.length} statements SQL`);

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
          console.log(`[Migrations] ✓ 005: Tabla creada: ${createMatch[1]}`);
        }
        const indexMatch = stmt.match(/CREATE\s+INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?["']?(\w+)/i);
        if (indexMatch) {
          console.log(`[Migrations] ✓ 005: Índice creado: ${indexMatch[1]}`);
        }
      } catch (err: any) {
        const errMsg = String(err?.message || err || "unknown");
        if (errMsg.includes("already exists") || err.code === 1) {
          console.warn(`[Migrations] ⚠️  005: Objeto ya existe, continuando`);
          skipped++;
        } else {
          throw new Error(`Migración 005 falló en statement ${i + 1}: ${errMsg}`);
        }
      }
    }

    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["005", `cash-movements-${executed}-statements-${Date.now()}`]
    );

    console.log(`[Migrations] ✅ 005 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log("[Migrations] 🎉 Migración 005 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 005 ya está aplicada");
  }

    // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 007: offline_events (Event Sourcing)
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "007")) {
    console.log("[Migrations] 🚀 Aplicando migración 007_create_offline_events...");

    const statements = parseSqlStatements(offlineEventsMigration);
    console.log(`[Migrations] 🔍 007: Parsed ${statements.length} statements SQL`);

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
      } catch (err: any) {
        const errMsg = String(err?.message || err || "unknown");

        if (
          errMsg.includes("already exists") ||
          errMsg.includes("duplicate") ||
          errMsg.includes("table offline_events already exists")
        ) {
          console.warn(`[Migrations] ⚠️  007: Statement ya aplicado, continuando`);
          skipped++;
        } else {
          throw new Error(`Migración 007 falló en statement ${i + 1}: ${errMsg}`);
        }
      }
    }

    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["007", `offline-events-${executed}-statements-${Date.now()}`]
    );

    console.log(`[Migrations] ✅ 007 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log("[Migrations] 🎉 Migración 007 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 007 ya está aplicada");
  }

    // ═══════════════════════════════════════════════════════════════
  // MIGRACIÓN 006: company_id en local_cash_sessions
  // ═══════════════════════════════════════════════════════════════
  if (!applied.some(m => m.version === "006")) {
    console.log("[Migrations] 🚀 Aplicando migración 006_add_company_to_local_cash_sessions...");

    const statements = parseSqlStatements(localCashSessionsCompanyMigration);
    console.log(`[Migrations] 🔍 006: Parsed ${statements.length} statements SQL`);

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
      } catch (err: any) {
        const errMsg = String(err?.message || err || "unknown");

        // Idempotencia defensiva: si company_id ya existe, continuar.
        if (
          errMsg.includes("duplicate column name") ||
          errMsg.includes("already exists") ||
          errMsg.includes("no such column") === false && stmt.toUpperCase().includes("ADD COLUMN COMPANY_ID")
        ) {
          console.warn(`[Migrations] ⚠️  006: Statement ya aplicado o no crítico, continuando: ${errMsg}`);
          skipped++;
        } else {
          throw new Error(`Migración 006 falló en statement ${i + 1}: ${errMsg}`);
        }
      }
    }

    await db.execute(
      "INSERT OR REPLACE INTO migrations (version, checksum) VALUES (?, ?)",
      ["006", `cash-sessions-company-${executed}-statements-${Date.now()}`]
    );

    console.log(`[Migrations] ✅ 006 Resumen: ${executed} ejecutados, ${skipped} saltados`);
    console.log("[Migrations] 🎉 Migración 006 aplicada correctamente");
  } else {
    console.log("[Migrations] ✅ Migración 006 ya está aplicada");
  }

  // ═══════════════════════════════════════════════════════════════
  // VERIFICACIÓN DE INTEGRIDAD (independiente de migraciones)
  // ═══════════════════════════════════════════════════════════════
  const criticalTables = [
    "sync_queue",
    "local_orders",
    "local_order_items",
    "local_tables",
    "local_bills",
    "local_cash_sessions",
    "local_cash_movements",
    "offline_events",
    "table_local_mutations"
  ];

  console.log("[Migrations] 🔍 Verificando integridad de tablas críticas...");
  let allOk = true;

  for (const table of criticalTables) {
    try {
      const check = await localDb.select<{ name: string }>(
        "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
        [table]
      );
      if (check.length === 0) {
        console.error(`[Migrations] ❌ Tabla faltante: ${table}`);
        allOk = false;
      } else {
        console.log(`[Migrations] ✅ Verificada: ${table}`);
      }
    } catch (err) {
      console.error(`[Migrations] ❌ Error verificando ${table}:`, err);
      allOk = false;
    }
  }

  if (!allOk) {
    console.warn("[Migrations] ⚠️  Faltan tablas críticas");
    throw new Error("Faltan tablas críticas en la base de datos");
  }

  console.log("[Migrations] ✅ Integridad de base de datos verificada");
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
