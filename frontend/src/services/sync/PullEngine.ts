import { syncApi } from "../syncApi";
import { useAuthStore } from "../../store/useAuthStore";
import { localDb } from "../../db/localDb";
import { useSyncStore } from "../../store/useSyncStore";
import { flattenAreas, type TablesArea } from "../../types/tables";

export interface PullResult {
  categories: number;
  products: number;
  tables: number;
  paymentMethods: number;
  success: boolean;
  error?: string;
  incremental: boolean;
}

export class PullEngine {
  /**
   * Descarga snapshot desde el servidor.
   * Usa modo incremental si hay last_pull_at, sino snapshot completo.
   */
  async pullAll(): Promise<PullResult> {
    const stats: PullResult = {
      categories: 0,
      products: 0,
      tables: 0,
      paymentMethods: 0,
      success: false,
      incremental: false,
    };

    try {
      console.log("[PullEngine] 🚀 Iniciando pullAll()...");
      useSyncStore.getState().setStatus("syncing");

      const lastPullAt = await this.getLastPullTimestamp();
      const branchId = this.getCurrentBranchId();

      if (lastPullAt) {
        console.log(`[PullEngine] Sync incremental desde ${lastPullAt}`);
        return await this.pullIncremental(branchId, lastPullAt);
      } else {
        console.log("[PullEngine] Sync completa (primera vez)");
        return await this.pullFullSnapshot();
      }
    } catch (error: any) {
      console.error("[PullEngine] Error:", error);
      useSyncStore.getState().setStatus("error");
      useSyncStore.getState().setLastError(error?.message || "Error en pull");
      return { ...stats, success: false, error: error?.message };
    }
  }

  /**
   * Modo incremental: solo descarga cambios desde last_pull_at.
   */
  private async pullIncremental(branchId: string, lastPullAt: string): Promise<PullResult> {
    console.log(`[PullEngine] 📥 Modo INCREMENTAL (branch: ${branchId}, desde: ${lastPullAt})`);
    const stats: PullResult = {
      categories: 0,
      products: 0,
      tables: 0,
      paymentMethods: 0,
      success: false,
      incremental: true,
    };

    const store = useSyncStore.getState();
    store.updateProgress({
      phase: "pull-downloading",
      message: "Descargando cambios recientes...",
      percentage: 0,
    });

    const response = await syncApi.fetchChanges(branchId, lastPullAt);
    const { changes, timestamp } = response;

    store.updateProgress({
      phase: "pull-applying",
      message: `Aplicando ${response.total} cambios...`,
      percentage: 50,
    });

    // Upsert incremental (solo actualiza/elimina lo que cambió)
    await this.upsertCategoriesIncremental(changes.categories);
    stats.categories = changes.categories.length;

    await this.upsertProductsIncremental(changes.products);
    stats.products = changes.products.length;

    await this.upsertTablesIncremental(changes.tables);
    stats.tables = changes.tables.length;

    await this.upsertPaymentMethodsIncremental(changes.payment_methods);
    stats.paymentMethods = changes.payment_methods.length;

    // Actualizar last_pull_at con el timestamp de la respuesta
    await this.updatePullTimestamp(timestamp);

    store.updateProgress({
      phase: "completed",
      message: "Cambios aplicados",
      percentage: 100,
    });

    stats.success = true;
    useSyncStore.getState().setStatus("online");

    console.log(`[PullEngine] ✓ Sync incremental: ${response.total} cambios aplicados`);
    return stats;
  }

  /**
   * Modo snapshot completo: descarga todo el catálogo.
   * Usado en primera sincronización o cuando no hay last_pull_at.
   */
  private async pullFullSnapshot(): Promise<PullResult> {
    console.log("[PullEngine] 📥 Modo SNAPSHOT COMPLETO (primera sync)");
    const stats: PullResult = {
      categories: 0,
      products: 0,
      tables: 0,
      paymentMethods: 0,
      success: false,
      incremental: false,
    };

    const store = useSyncStore.getState();
    store.updateProgress({
      phase: "pull-downloading",
      message: "Descargando catálogo completo...",
      percentage: 0,
    });

    const catalog = await syncApi.fetchCatalog();
    store.updateProgress({ percentage: 25 });

    const [tableAreas, paymentMethods] = await Promise.all([
      syncApi.fetchTables() as Promise<TablesArea[]>,
      syncApi.fetchPaymentMethods(),
    ]);

    // GET /tables devuelve mesas agrupadas por área (TableCollection en el backend).
    // Hay que aplanarlas antes de insertarlas en local_tables.
    const tables = flattenAreas(tableAreas);

    store.updateProgress({
      phase: "pull-applying",
      message: "Aplicando snapshot completo...",
      percentage: 75,
    });

    await this.upsertCategories(catalog.categories);
    stats.categories = catalog.categories.length;

    await this.upsertProducts(catalog.products);
    stats.products = catalog.products.length;

    await this.upsertTables(tables);
    stats.tables = tables.length;

    await this.upsertPaymentMethods(paymentMethods);
    stats.paymentMethods = paymentMethods.length;

    await this.updatePullTimestamp(new Date().toISOString());

    stats.success = true;
    useSyncStore.getState().setStatus("online");

    console.log(`[PullEngine] ✓ Snapshot completo: ${stats.categories} categorías, ${stats.products} productos, ${stats.tables} mesas, ${stats.paymentMethods} métodos`);
    return stats;
  }

  // Métodos de upsert completo (eliminan y recrean)
  private async upsertCategories(categories: any[]): Promise<void> {
    if (categories.length === 0) return;
    await localDb.execute("DELETE FROM local_categories");
    for (const cat of categories) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_categories (uuid, name_translations, sort_order, is_active, last_updated) 
         VALUES (?, ?, ?, ?, ?)`,
        [cat.uuid, JSON.stringify(cat.name_translations), cat.sort_order || 0, cat.is_active ? 1 : 0, cat.updated_at]
      );
    }
  }

  private async upsertProducts(products: any[]): Promise<void> {
    if (products.length === 0) return;
    await localDb.execute("DELETE FROM local_products");
    for (const prod of products) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_products 
         (uuid, category_id, sku, name_translations, description_translations, base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [prod.uuid, prod.category_id, prod.sku, JSON.stringify(prod.name_translations), JSON.stringify(prod.description_translations || {}), 
         prod.base_price, prod.tax_rate, prod.is_combo ? 1 : 0, prod.kitchen_zone_id, prod.is_active ? 1 : 0, prod.updated_at]
      );
    }
  }

  /**
   * FASE 4: Upsert de mesas preservando mutaciones locales pendientes.
   *
   * REGLA: Si existe una mutación pendiente para una mesa (en table_local_mutations),
   * NO sobrescribimos status ni current_order_uuid con datos del cloud.
   * El estado local pendiente tiene prioridad sobre el estado del cloud.
   *
   * Esto resuelve el bug donde:
   *   OFFLINE: Mesa local = occupied, Pedido local = pending
   *   PULL: Cloud = available
   *   Antes: local = available (INCORRECTO - destruía mutación)
   *   Ahora: local = occupied (CORRECTO - preserva mutación)
   */
  private async upsertTables(tables: any[]): Promise<void> {
    if (tables.length === 0) return;

    // Obtener mutaciones pendientes UNA VEZ
    const mutations = await localDb.select<{ table_uuid: string; pending_status: string; pending_order_uuid: string | null }>(
      "SELECT table_uuid, pending_status, pending_order_uuid FROM table_local_mutations"
    );
    const mutationMap = new Map(mutations.map(m => [m.table_uuid, m]));

    let preserved = 0;
    let synced = 0;

    for (const table of tables) {
      const hasMutation = mutationMap.has(table.uuid);

      if (hasMutation) {
        // 🔒 Hay mutación pendiente: preservar status y order_uuid locales
        // Solo actualizar campos que NO son estado (table_number, area_name, capacity)
        const mutation = mutationMap.get(table.uuid)!;
        await localDb.execute(
          `INSERT OR REPLACE INTO local_tables 
           (uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated) 
           VALUES (?, ?, ?, ?, ?, ?, ?)`,
          [
            table.uuid,
            table.table_number,
            table.area_name,
            table.capacity,
            mutation.pending_status,           // ← Preservado
            mutation.pending_order_uuid,       // ← Preservado
            table.updated_at
          ]
        );
        preserved++;
        console.log(`[PullEngine] 🔒 Mesa ${table.table_number} (${table.uuid}): mutación preservada → ${mutation.pending_status}`);
      } else {
        // ✅ Sin mutación: aplicar estado del cloud normalmente
        await localDb.execute(
          `INSERT OR REPLACE INTO local_tables 
           (uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated) 
           VALUES (?, ?, ?, ?, ?, ?, ?)`,
          [table.uuid, table.table_number, table.area_name, table.capacity, table.status, table.current_order_uuid, table.updated_at]
        );
        synced++;
      }
    }

    if (preserved > 0) {
      console.log(`[PullEngine] 📊 Mesas: ${synced} actualizadas desde cloud, ${preserved} mutaciones preservadas`);
    }
  }

  private async upsertPaymentMethods(methods: any[]): Promise<void> {
    if (methods.length === 0) return;
    await localDb.execute("DELETE FROM local_payment_methods");
    for (const method of methods) {
      await localDb.execute(
        `INSERT OR REPLACE INTO local_payment_methods (uuid, code, type, is_active, last_updated) 
         VALUES (?, ?, ?, ?, ?)`,
        [method.uuid, method.code, method.type, method.is_active ? 1 : 0, method.updated_at]
      );
    }
  }

  // Métodos de upsert incremental (solo actualizan o eliminan según flag deleted)
  private async upsertCategoriesIncremental(categories: any[]): Promise<void> {
    for (const cat of categories) {
      if (cat.deleted) {
        // Eliminar localmente
        await localDb.execute("DELETE FROM local_categories WHERE uuid = ?", [cat.uuid]);
      } else {
        // Insertar o actualizar
        await localDb.execute(
          `INSERT OR REPLACE INTO local_categories (uuid, name_translations, sort_order, is_active, last_updated) 
           VALUES (?, ?, ?, ?, ?)`,
          [cat.uuid, JSON.stringify(cat.name_translations), cat.sort_order || 0, cat.is_active ? 1 : 0, cat.updated_at]
        );
      }
    }
  }

  private async upsertProductsIncremental(products: any[]): Promise<void> {
    for (const prod of products) {
      if (prod.deleted) {
        await localDb.execute("DELETE FROM local_products WHERE uuid = ?", [prod.uuid]);
      } else {
        await localDb.execute(
          `INSERT OR REPLACE INTO local_products 
           (uuid, category_id, sku, name_translations, description_translations, base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated) 
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [prod.uuid, prod.category_id, prod.sku, JSON.stringify(prod.name_translations), JSON.stringify(prod.description_translations || {}), 
           prod.base_price, prod.tax_rate, prod.is_combo ? 1 : 0, prod.kitchen_zone_id, prod.is_active ? 1 : 0, prod.updated_at]
        );
      }
    }
  }

  /**
   * FASE 4: Upsert incremental preservando mutaciones locales.
   * Mismo principio que upsertTables pero para cambios incrementales.
   */
  private async upsertTablesIncremental(tables: any[]): Promise<void> {
    // Obtener mutaciones pendientes UNA VEZ
    const mutations = await localDb.select<{ table_uuid: string; pending_status: string; pending_order_uuid: string | null }>(
      "SELECT table_uuid, pending_status, pending_order_uuid FROM table_local_mutations"
    );
    const mutationMap = new Map(mutations.map(m => [m.table_uuid, m]));

    for (const table of tables) {
      if (table.deleted) {
        // Si hay mutación pendiente para una mesa eliminada, eliminarla también
        // (la mesa ya no existe en el cloud)
        await localDb.execute("DELETE FROM table_local_mutations WHERE table_uuid = ?", [table.uuid]);
        await localDb.execute("DELETE FROM local_tables WHERE uuid = ?", [table.uuid]);
      } else {
        const hasMutation = mutationMap.has(table.uuid);

        if (hasMutation) {
          // 🔒 Preservar estado local pendiente
          const mutation = mutationMap.get(table.uuid)!;
          await localDb.execute(
            `INSERT OR REPLACE INTO local_tables 
             (uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated) 
             VALUES (?, ?, ?, ?, ?, ?, ?)`,
            [
              table.uuid,
              table.table_number,
              table.area_name,
              table.capacity,
              mutation.pending_status,
              mutation.pending_order_uuid,
              table.updated_at
            ]
          );
        } else {
          // ✅ Aplicar estado del cloud
          await localDb.execute(
            `INSERT OR REPLACE INTO local_tables 
             (uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated) 
             VALUES (?, ?, ?, ?, ?, ?, ?)`,
            [table.uuid, table.table_number, table.area_name, table.capacity, table.status, table.current_order_uuid, table.updated_at]
          );
        }
      }
    }
  }

  private async upsertPaymentMethodsIncremental(methods: any[]): Promise<void> {
    for (const method of methods) {
      if (method.deleted) {
        await localDb.execute("DELETE FROM local_payment_methods WHERE uuid = ?", [method.uuid]);
      } else {
        await localDb.execute(
          `INSERT OR REPLACE INTO local_payment_methods (uuid, code, type, is_active, last_updated) 
           VALUES (?, ?, ?, ?, ?)`,
          [method.uuid, method.code, method.type, method.is_active ? 1 : 0, method.updated_at]
        );
      }
    }
  }

  private async updatePullTimestamp(timestamp: string): Promise<void> {
    await localDb.execute(
      `INSERT OR REPLACE INTO sync_state (key, value, updated_at) VALUES ('last_pull_at', ?, CURRENT_TIMESTAMP)`,
      [timestamp]
    );
  }

  async getLastPullTimestamp(): Promise<string | null> {
    const result = await localDb.selectOne<{ value: string }>(
      "SELECT value FROM sync_state WHERE key = 'last_pull_at'"
    );
    return result?.value || null;
  }

  private getCurrentBranchId(): string {
    try {
      const user = useAuthStore.getState().user;
      if (user?.branch_id) {
        const id = String(user.branch_id);
        console.log(`[PullEngine] ✅ branch_id obtenido del user: ${id}`);
        return id;
      }
      console.warn("[PullEngine] ⚠️ user sin branch_id, usando fallback '1'");
    } catch (error) {
      console.warn("[PullEngine] ⚠️ Error leyendo auth store:", error);
    }
    return "1";
  }
}

export const pullEngine = new PullEngine();
