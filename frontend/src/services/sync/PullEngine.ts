import { syncApi } from "../syncApi";
import { localDb } from "../../db/localDb";
import { useSyncStore } from "../../store/useSyncStore";

/**
 * PullEngine: Descarga snapshot del cloud y lo aplica al SQLite local.
 *
 * Política de resolución de conflictos (Cloud wins):
 * - Catálogo (categorías, productos, métodos de pago): Cloud siempre gana
 * - Mesas: Cloud siempre gana (estados desde servidor son verdad)
 */
export interface PullResult {
  categories: number;
  products: number;
  tables: number;
  paymentMethods: number;
  success: boolean;
  error?: string;
}

export class PullEngine {
  /**
   * Descarga snapshot completo desde el servidor.
   */
  async pullAll(): Promise<PullResult> {
    const stats: PullResult = {
      categories: 0,
      products: 0,
      tables: 0,
      paymentMethods: 0,
      success: false,
    };

    try {
      useSyncStore.getState().setStatus("syncing");

      console.log("[PullEngine] Iniciando descarga de snapshot...");

      // 1. Descargar catálogo (una sola llamada que retorna categories + products)
      const catalog = await syncApi.fetchCatalog();
      const categories = catalog.categories || [];
      const products = catalog.products || [];

      // 2. Descargar mesas y métodos de pago en paralelo
      const [tables, paymentMethods] = await Promise.all([
        syncApi.fetchTables(),
        syncApi.fetchPaymentMethods(),
      ]);

      console.log(`[PullEngine] Descargado: ${categories.length} categorías, ${products.length} productos, ${tables.length} mesas, ${paymentMethods.length} métodos`);

      // Emitir progreso
      const store = useSyncStore.getState();
      store.updateProgress({
        phase: "pull-applying",
        message: "Aplicando cambios locales...",
        percentage: 90,
      });

      // 3. Upsert en SQLite (Cloud wins)
      await this.upsertCategories(categories);
      stats.categories = categories.length;

      await this.upsertProducts(products);
      stats.products = products.length;

      await this.upsertTables(tables);
      stats.tables = tables.length;

      await this.upsertPaymentMethods(paymentMethods);
      stats.paymentMethods = paymentMethods.length;

      // 4. Actualizar timestamp de último pull
      const now = new Date().toISOString();
      await this.updatePullTimestamp(now);

      stats.success = true;
      useSyncStore.getState().setStatus("online");

      console.log(`[PullEngine] ✓ Snapshot aplicado: ${stats.categories} categorías, ${stats.products} productos, ${stats.tables} mesas, ${stats.paymentMethods} métodos`);

      return stats;
    } catch (error: any) {
      console.error("[PullEngine] Error descargando snapshot:", error);
      useSyncStore.getState().setStatus("error");
      useSyncStore.getState().setLastError(error?.message || "Error en pull");

      return { ...stats, success: false, error: error?.message };
    }
  }

  private async upsertCategories(categories: any[]): Promise<void> {
    if (categories.length === 0) return;

    await localDb.execute("DELETE FROM local_categories");

    for (const cat of categories) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_categories (
          uuid, name_translations, sort_order, is_active, last_updated
        ) VALUES (?, ?, ?, ?, ?)`,
        [
          cat.uuid || cat.id,
          JSON.stringify(cat.name_translations || { es: cat.name }),
          cat.sort_order || 0,
          cat.is_active !== false ? 1 : 0,
          cat.updated_at || new Date().toISOString(),
        ]
      );
    }
  }

  private async upsertProducts(products: any[]): Promise<void> {
    if (products.length === 0) return;

    await localDb.execute("DELETE FROM local_products");

    for (const prod of products) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_products (
          uuid, category_id, sku, name_translations, description_translations,
          base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          prod.uuid || prod.id,
          prod.category_id || prod.category_uuid || null,
          prod.sku || null,
          JSON.stringify(prod.name_translations || { es: prod.name }),
          JSON.stringify(prod.description_translations || {}),
          prod.base_price || 0,
          prod.tax_rate || 19.0,
          prod.is_combo ? 1 : 0,
          prod.kitchen_zone_id || null,
          prod.is_active !== false ? 1 : 0,
          prod.updated_at || new Date().toISOString(),
        ]
      );
    }
  }

  private async upsertTables(tables: any[]): Promise<void> {
    if (tables.length === 0) return;

    await localDb.execute("DELETE FROM local_tables");

    for (const table of tables) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_tables (
          uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated
        ) VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [
          table.uuid || table.id,
          table.table_number,
          table.area_name || table.area_code || "",
          table.capacity || 4,
          table.status || "available",
          table.current_order_uuid || table.current_order_id || null,
          table.updated_at || new Date().toISOString(),
        ]
      );
    }
  }

  private async upsertPaymentMethods(methods: any[]): Promise<void> {
    if (methods.length === 0) return;

    await localDb.execute("DELETE FROM local_payment_methods");

    for (const method of methods) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_payment_methods (
          uuid, code, type, is_active, last_updated
        ) VALUES (?, ?, ?, ?, ?)`,
        [
          method.uuid || method.id,
          method.code,
          method.type || method.code.toLowerCase(),
          method.is_active !== false ? 1 : 0,
          method.updated_at || new Date().toISOString(),
        ]
      );
    }
  }

  private async updatePullTimestamp(timestamp: string): Promise<void> {
    await localDb.execute(
      `INSERT OR REPLACE INTO sync_state (key, value, updated_at) 
       VALUES ('last_pull_at', ?, CURRENT_TIMESTAMP)`,
      [timestamp]
    );
  }

  async getLastPullTimestamp(): Promise<string | null> {
    const result = await localDb.selectOne<{ value: string }>(
      "SELECT value FROM sync_state WHERE key = 'last_pull_at'"
    );
    return result?.value || null;
  }
}

export const pullEngine = new PullEngine();
