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

import { parseSqlStatements } from "./utils/sqlParser";
import { applyMigration } from "./nativeDb";

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
    console.log("[Migrations] 🚀 Iniciando sistema de migraciones...");

    // 1. Asegurar que la tabla de control existe (applyMigration maneja el IF NOT EXISTS internamente)
    await applyMigration("000_create_migrations_table", `
      CREATE TABLE IF NOT EXISTS migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
        checksum TEXT
      )
    `);

    // 2. Aplicar migraciones secuencialmente. Rust verifica si ya fueron aplicadas.
    await applyMigration("001", initialMigration);
    await applyMigration("002", tableMutationsMigration);
    await applyMigration("003", backendIdMigration);
    await applyMigration("004", localBillsMigration);
    await applyMigration("005", localCashMovementsMigration);
    await applyMigration("006", localCashSessionsCompanyMigration);
    await applyMigration("007", offlineEventsMigration);
    await applyMigration("008", localPrintJobsMigration);
    await applyMigration("009", printerConfigsMigration);
    await applyMigration("011", chileanColumnsMigration);
    await applyMigration("012", multiTenancyMigration);
    await applyMigration("013", tableMutationsTenancyMigration);
    await applyMigration("014", tenantBackfillMigration);
    await applyMigration("015", billLinkMigration);
    await applyMigration("016", fixOrphanTablesMigration);
    await applyMigration("017", enforceIntegerMoneyMigration);
    await applyMigration("019", fixTableStatusMigration);
    await applyMigration("020", cleanTableMutationsMigration);
    await applyMigration("021", enforceTableMutationSchemaMigration);

    console.log("[Migrations] ✅ Todas las migraciones aplicadas exitosamente");
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
