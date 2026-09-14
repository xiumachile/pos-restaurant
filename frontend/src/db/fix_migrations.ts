/**
 * Script de reparación de migraciones inconsistentes
 * 
 * Problema: La migración 011 se aplicó parcialmente en versiones anteriores
 * de la app, dejando tablas sin columnas requeridas.
 * 
 * Este script verifica el estado real y aplica correcciones si es necesario.
 */

import { localDb } from "./localDb";

export async function fixInconsistentMigrations(): Promise<void> {
  console.log("[FixMigrations] Verificando estado de tablas...");

  try {
    // Verificar si local_orders tiene tax_total
    const ordersColumns = await localDb.select<any>(
      "PRAGMA table_info(local_orders)"
    );
    const hasTaxTotal = ordersColumns.some((col: any) => col.name === "tax_total");

    if (!hasTaxTotal) {
      console.log("[FixMigrations] ⚠️ local_orders no tiene tax_total, aplicando corrección...");
      
      // Agregar columnas faltantes
      await localDb.execute("ALTER TABLE local_orders ADD COLUMN tax_total INTEGER DEFAULT 0");
      await localDb.execute("ALTER TABLE local_orders ADD COLUMN net_amount INTEGER DEFAULT 0");
      await localDb.execute("ALTER TABLE local_orders ADD COLUMN amount_due INTEGER DEFAULT 0");
      
      // Calcular valores para filas existentes
      await localDb.execute(`
        UPDATE local_orders SET
          tax_total = CAST(ROUND(grand_total - (grand_total / 1.19)) AS INTEGER),
          net_amount = CAST(ROUND(grand_total / 1.19) AS INTEGER),
          amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER)
        WHERE tax_total = 0 OR net_amount = 0 OR amount_due = 0
      `);
      
      console.log("[FixMigrations] ✅ local_orders corregido");
    } else {
      console.log("[FixMigrations] ✅ local_orders tiene todas las columnas requeridas");
    }

    // Verificar local_order_items
    const itemsColumns = await localDb.select<any>(
      "PRAGMA table_info(local_order_items)"
    );
    const hasUnitPriceInteger = itemsColumns.some(
      (col: any) => col.name === "unit_price" && col.type === "INTEGER"
    );

    if (!hasUnitPriceInteger) {
      console.log("[FixMigrations] ⚠️ local_order_items tiene tipos incorrectos, recreando tabla...");
      
      // Esta es una operación más compleja, solo si es necesario
      await localDb.execute(`
        CREATE TABLE IF NOT EXISTS local_order_items_new (
          local_uuid TEXT PRIMARY KEY,
          order_local_uuid TEXT NOT NULL,
          cloud_id TEXT,
          product_id TEXT NOT NULL,
          product_name TEXT NOT NULL,
          quantity INTEGER NOT NULL DEFAULT 1,
          unit_price INTEGER NOT NULL,
          subtotal INTEGER NOT NULL,
          notes TEXT,
          kitchen_status TEXT DEFAULT 'pending',
          is_menu_item INTEGER DEFAULT 0,
          menu_item_id TEXT,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
        )
      `);
      
      await localDb.execute(`
        INSERT OR IGNORE INTO local_order_items_new
        SELECT 
          local_uuid, order_local_uuid, cloud_id, product_id, product_name,
          quantity, CAST(ROUND(unit_price) AS INTEGER), CAST(ROUND(subtotal) AS INTEGER),
          notes, kitchen_status, is_menu_item, menu_item_id, created_at
        FROM local_order_items
      `);
      
      await localDb.execute("DROP TABLE local_order_items");
      await localDb.execute("ALTER TABLE local_order_items_new RENAME TO local_order_items");
      await localDb.execute("CREATE INDEX IF NOT EXISTS idx_local_order_items_order ON local_order_items(order_local_uuid)");
      
      console.log("[FixMigrations] ✅ local_order_items recreado");
    } else {
      console.log("[FixMigrations] ✅ local_order_items tiene tipos correctos");
    }

    // Verificar local_payments
    const paymentsColumns = await localDb.select<any>(
      "PRAGMA table_info(local_payments)"
    );
    const hasSaleAmount = paymentsColumns.some((col: any) => col.name === "sale_amount");

    if (!hasSaleAmount) {
      console.log("[FixMigrations] ⚠️ local_payments no tiene sale_amount, aplicando corrección...");
      
      await localDb.execute("ALTER TABLE local_payments ADD COLUMN sale_amount INTEGER DEFAULT 0");
      await localDb.execute(`
        UPDATE local_payments SET
          sale_amount = CAST(ROUND(amount - tip_amount) AS INTEGER)
        WHERE sale_amount = 0
      `);
      
      console.log("[FixMigrations] ✅ local_payments corregido");
    } else {
      console.log("[FixMigrations] ✅ local_payments tiene sale_amount");
    }

    // Verificar local_bills
    const billsColumns = await localDb.select<any>(
      "PRAGMA table_info(local_bills)"
    );
    const hasBillNetAmount = billsColumns.some((col: any) => col.name === "net_amount");

    if (!hasBillNetAmount) {
      console.log("[FixMigrations] ⚠️ local_bills no tiene net_amount, aplicando corrección...");
      
      await localDb.execute("ALTER TABLE local_bills ADD COLUMN net_amount INTEGER DEFAULT 0");
      await localDb.execute("ALTER TABLE local_bills ADD COLUMN amount_due INTEGER DEFAULT 0");
      
      await localDb.execute(`
        UPDATE local_bills SET
          net_amount = CAST(ROUND(grand_total / 1.19) AS INTEGER),
          tax_total = CAST(ROUND(grand_total - (grand_total / 1.19)) AS INTEGER),
          amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER)
        WHERE net_amount = 0 OR amount_due = 0
      `);
      
      console.log("[FixMigrations] ✅ local_bills corregido");
    } else {
      console.log("[FixMigrations] ✅ local_bills tiene net_amount");
    }

    console.log("[FixMigrations] ✅ Verificación completada");
  } catch (error) {
    console.error("[FixMigrations] ❌ Error:", error);
    throw error;
  }
}
