// @ts-nocheck
import { localDb } from "./localDb";
import initialMigration from "./migrations/001_initial.sql?raw";
import tableMutationsMigration from "./migrations/002_table_local_mutations.sql?raw";
import backendIdMigration from "./migrations/003_add_backend_id_to_categories.sql?raw";
import localBillsMigration from "./migrations/004_create_local_bills.sql?raw";
import localCashMovementsMigration from "./migrations/005_create_local_cash_movements.sql?raw";
import localCashSessionsCompanyMigration from "./migrations/006_add_company_to_local_cash_sessions.sql?raw";
import offlineEventsMigration from "./migrations/007_create_offline_events.sql?raw";
import localPrintJobsMigration from "./migrations/008_create_local_print_jobs.sql?raw";
import printerConfigsMigration from "./migrations/009_create_printer_configs.sql?raw";
import chileanColumnsMigration from "./migrations/011_add_chilean_columns.sql?raw";
import multiTenancyMigration from "./migrations/012_add_tenant_to_local_tables.sql?raw";
import tableMutationsTenancyMigration from "./migrations/013_add_tenant_to_table_mutations.sql?raw";
import tenantBackfillMigration from "./migrations/014_backfill_and_validate_tenant.sql?raw";
import billLinkMigration from "./migrations/015_add_bill_link_to_payments.sql?raw";
import fixOrphanTablesMigration from "./migrations/016_fix_orphan_local_tables.sql?raw";
import enforceIntegerMoneyMigration from "./migrations/017_enforce_integer_money.sql?raw";
import fixTableStatusMigration from "./migrations/019_fix_table_status_pending.sql?raw";
import cleanTableMutationsMigration from "./migrations/020_clean_table_mutations.sql?raw";
import enforceTableMutationSchemaMigration from "./migrations/021_enforce_table_mutation_schema.sql?raw";
import customerFieldsMigration from "./migrations/023_add_customer_fields_to_local_orders.sql?raw";

import { parseSqlStatements } from "./utils/sqlParser";
import { applyMigration } from "./migrations/applyMigration";
import { ensureChileanColumns } from "./migrations/018_ensure_chilean_columns";

// El parser de SQL ahora vive en src/db/utils/sqlParser.ts

let migrationInitPromise: Promise<void> | null = null;

/**
 * Ejecuta todas las migraciones pendientes.
 */
export async function runMigrations(): Promise<void> {
  if (migrationInitPromise) {
    return migrationInitPromise;
  }

  migrationInitPromise = (async () => {
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

  await applyMigration(db, "001", initialMigration, applied);
  await applyMigration(db, "002", tableMutationsMigration, applied);
  await applyMigration(db, "003", backendIdMigration, applied);
  await applyMigration(db, "004", localBillsMigration, applied);
  await applyMigration(db, "005", localCashMovementsMigration, applied);
  await applyMigration(db, "006", localCashSessionsCompanyMigration, applied);
  await applyMigration(db, "007", offlineEventsMigration, applied);
  await applyMigration(db, "008", localPrintJobsMigration, applied);
  await applyMigration(db, "009", printerConfigsMigration, applied);
  await applyMigration(db, "011", chileanColumnsMigration, applied);
  await applyMigration(db, "012", multiTenancyMigration, applied);
  await applyMigration(db, "013", tableMutationsTenancyMigration, applied);
  await applyMigration(db, "014", tenantBackfillMigration, applied);
  await applyMigration(db, "015", billLinkMigration, applied);
  await applyMigration(db, "016", fixOrphanTablesMigration, applied);
  await applyMigration(db, "017", enforceIntegerMoneyMigration, applied);
  await applyMigration(db, "019", fixTableStatusMigration, applied);
  await applyMigration(db, "020", cleanTableMutationsMigration, applied);
  await applyMigration(db, "021", enforceTableMutationSchemaMigration, applied);
  await applyMigration(db, "023", customerFieldsMigration, applied);

  // ═══════════════════════════════════════════════════════════════
  // VERIFICACIÓN DE INTEGRIDAD (independiente de migraciones)
  // ═══════════════════════════════════════════════════════════════
  const criticalSchemas = [
    { table: "local_orders", required: ["terminal_id", "guest_count", "idempotency_key", "customer_id", "customer_name", "customer_phone", "delivery_address", "delivery_notes"] },
    { table: "local_bills", required: ["terminal_id"] },
    { table: "sync_queue", required: ["entity_type", "sync_status"] }
  ];

  await ensureChileanColumns();

  console.log("[Migrations] 🔍 Verificando integridad profunda de esquemas...");
  let allOk = true;

  for (const schema of criticalSchemas) {
    try {
      const columns: any[] = await db.select(`PRAGMA table_info(${schema.table})`);
      const existingNames = columns.map((c: any) => c.name);
      
      const missing = schema.required.filter((col: string) => !existingNames.includes(col));
      
      if (missing.length > 0) {
        console.error(`[Migrations] ❌ Tabla ${schema.table} falta columnas críticas: ${missing.join(", ")}`);
        allOk = false;
      } else {
        console.log(`[Migrations] ✅ Esquema verificado: ${schema.table}`);
      }
    } catch (err) {
      console.error(`[Migrations] ❌ Error verificando esquema de ${schema.table}:`, err);
      allOk = false;
    }
  }

  if (!allOk) {
    console.warn("[Migrations] ⚠️  Faltan tablas críticas");
    throw new Error("Faltan tablas críticas en la base de datos");
  }

    console.log("[Migrations] ✅ Integridad de base de datos verificada");
  })();

  return migrationInitPromise;
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
