import { localDb } from "@/db/localDb";
import { getCashierContextSafe } from "./authContext";
import type { RestaurantTable, TableStatus } from "@/types/tables";

/**
 * Representa una mutación local pendiente de sincronización.
 * Mientras exista una mutación para una mesa, PullEngine NO
 * debe sobrescribir el estado con datos del cloud.
 */
export interface TableMutation {
  table_uuid: string;
  pending_status: string;
  pending_order_uuid: string | null;
  created_at: string;
  company_id: string;
  branch_id: string;
}

/**
 * Servicio para gestión de estado local de mesas con modelo dual:
 * - local_tables: estado reflejo del cloud (sincronizado por PullEngine)
 * - table_local_mutations: mutaciones locales pendientes (creadas offline)
 *
 * REGLA ARQUITECTÓNICA:
 * "Una mutación local pendiente nunca puede ser destruida por un Pull cloud."
 *
 * El estado visible al usuario se calcula como:
 *   visible_status = table_local_mutations.pending_status ?? local_tables.status
 *
 * ADR-012: TODAS las operaciones filtran por company_id + branch_id
 * para garantizar aislamiento multi-tenant local.
 */
export const localTablesService = {
  /**
   * Obtiene el contexto actual o null si no hay usuario autenticado.
   */
  _getContext() {
    return getCashierContextSafe();
  },

  /**
   * Obtiene todas las mutaciones pendientes del tenant actual.
   */
  async getPendingMutations(): Promise<TableMutation[]> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localTablesService] ⚠️ Sin contexto, retornando mutaciones vacías");
      return [];
    }

    return await localDb.select<TableMutation>(
      "SELECT * FROM table_local_mutations WHERE company_id = ? AND branch_id = ? ORDER BY created_at ASC",
      [ctx.company_id, ctx.branch_id]
    );
  },

  /**
   * Obtiene la mutación pendiente para una mesa específica (si existe).
   * Filtra por tenant actual para prevenir acceso cruzado.
   */
  async getMutation(tableUuid: string): Promise<TableMutation | null> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localTablesService] ⚠️ Sin contexto, retornando null");
      return null;
    }

    return await localDb.selectOne<TableMutation>(
      "SELECT * FROM table_local_mutations WHERE table_uuid = ? AND company_id = ? AND branch_id = ?",
      [tableUuid, ctx.company_id, ctx.branch_id]
    );
  },

  /**
   * Lee el status visible de todas las mesas del tenant actual.
   * Prioriza mutaciones locales sobre estado del cloud.
   *
   * Retorna un mapa uuid -> status para aplicar como overlay.
   */
  async getStatusOverrides(): Promise<Map<string, string>> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localTablesService] ⚠️ Sin contexto, retornando mapa vacío");
      return new Map();
    }

    // 1. Obtener mutaciones pendientes del tenant actual (prioridad alta)
    const mutations = await localDb.select<TableMutation>(
      "SELECT table_uuid, pending_status FROM table_local_mutations WHERE company_id = ? AND branch_id = ?",
      [ctx.company_id, ctx.branch_id]
    );

    const map = new Map<string, string>();

    // Mutaciones tienen prioridad absoluta
    for (const m of mutations) {
      map.set(m.table_uuid, m.pending_status);
    }

    // 2. Para mesas SIN mutación, usar estado del cloud (local_tables)
    if (mutations.length < 100) {
      const cloudTables = await localDb.select<{ uuid: string; status: string }>(
        "SELECT uuid, status FROM local_tables WHERE company_id = ? AND branch_id = ?",
        [ctx.company_id, ctx.branch_id]
      );
      for (const t of cloudTables) {
        if (!map.has(t.uuid)) {
          map.set(t.uuid, t.status);
        }
      }
    }

    return map;
  },

  /**
   * Lee TODAS las mesas del tenant actual desde SQLite con toda su información.
   * Usado como fallback cuando no hay caché del backend disponible.
   *
   * NOTA: Se usan 2 queries separadas en lugar de LEFT JOIN para evitar
   * problemas con el mock de SQLite en tests (no soporta JOINs complejos
   * con múltiples condiciones en el ON clause).
   *
   * El status visible se calcula priorizando mutaciones locales sobre cloud.
   */
  async getAllTables(): Promise<RestaurantTable[]> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localTablesService] ⚠️ Sin contexto, retornando mesas vacías");
      return [];
    }

    interface TableRow {
      uuid: string;
      table_number: string;
      area_name: string | null;
      capacity: number | null;
      status: string;
      current_order_uuid: string | null;
      last_updated: string | null;
    }

    // Query 1: Traer todas las mesas del tenant
    const tables = await localDb.select<TableRow>(
      `SELECT uuid, table_number, area_name, capacity, status, current_order_uuid, last_updated
       FROM local_tables
       WHERE company_id = ? AND branch_id = ?
       ORDER BY area_name, table_number`,
      [ctx.company_id, ctx.branch_id]
    );

    // Query 2: Traer mutaciones pendientes del tenant
    const mutations = await localDb.select<{
      table_uuid: string;
      pending_status: string;
      pending_order_uuid: string | null;
    }>(
      `SELECT table_uuid, pending_status, pending_order_uuid
       FROM table_local_mutations
       WHERE company_id = ? AND branch_id = ?`,
      [ctx.company_id, ctx.branch_id]
    );

    // Crear mapa de mutaciones para lookup rápido
    const mutationMap = new Map(
      mutations.map(m => [m.table_uuid, m])
    );

    // Combinar resultados: mutación local tiene prioridad sobre cloud
    const result = tables.map(t => {
      const mutation = mutationMap.get(t.uuid);
      const visibleStatus = mutation?.pending_status || t.status || "available";
      const visibleOrderUuid = mutation?.pending_order_uuid || t.current_order_uuid;

      return {
        uuid: t.uuid,
        table_number: t.table_number,
        area_code: (t.area_name || "sin_area").toLowerCase().replace(/\s+/g, "_"),
        area_name: t.area_name || "Sin área",
        capacity: t.capacity || 4,
        status: visibleStatus as TableStatus,
        has_active_order: !!visibleOrderUuid,
        current_order_id: null,
        created_at: t.last_updated || new Date().toISOString(),
        updated_at: t.last_updated || new Date().toISOString(),
      };
    });
    return result;
  },

  /**
   * Marca una mesa como ocupada (update optimista al crear pedido offline).
   *
   * ADR-012: Requiere company_id y branch_id explícitos (tenant isolation).
   */
  async markOccupied(
    tableUuid: string,
    orderLocalUuid: string,
    companyId: string,
    branchId: string
  ): Promise<void> {
    console.log("[localTablesService] 🪑 markOccupied:", {
      tableUuid, orderLocalUuid, companyId, branchId
    });

    // 0. Verificar que la mesa existe en local_tables Y pertenece al tenant
    const tableCheck = await localDb.select<{ uuid: string }>(
      "SELECT uuid FROM local_tables WHERE uuid = ? AND company_id = ? AND branch_id = ?",
      [tableUuid, companyId, branchId]
    );
    if (tableCheck.length === 0) {
      throw new Error(`[localTablesService] Mesa ${tableUuid} no existe en SQLite o no pertenece al tenant actual (${companyId}/${branchId}). PullEngine debe sincronizar las mesas primero.`);
    }

    // 1. Registrar mutación pendiente (autoridad principal) con tenant
    await localDb.execute(
      `INSERT OR REPLACE INTO table_local_mutations
       (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, created_at)
       VALUES (?, 'occupied', ?, ?, ?, CURRENT_TIMESTAMP)`,
      [tableUuid, orderLocalUuid, companyId, branchId]
    );

    // 2. Actualizar local_tables para reflejo inmediato (filtrado por tenant)
    await localDb.execute(
      `UPDATE local_tables
       SET status = 'occupied',
           current_order_uuid = ?,
           last_updated = CURRENT_TIMESTAMP
       WHERE uuid = ? AND company_id = ? AND branch_id = ?`,
      [orderLocalUuid, tableUuid, companyId, branchId]
    );

    console.log("[localTablesService] ✅ Mutación registrada + local_tables actualizado");
  },

  /**
   * Marca una mesa como disponible (update optimista tras pago).
   * ADR-012: Filtrado por tenant obligatorio.
   */
  async markAvailable(tableUuid: string): Promise<void> {
    const ctx = this._getContext();
    if (!ctx) {
      throw new Error("[localTablesService] ❌ Sin contexto de usuario, no se puede liberar mesa");
    }

    console.log("[localTablesService] 🟢 markAvailable:", {
      tableUuid, company_id: ctx.company_id, branch_id: ctx.branch_id
    });

    try {
      // 1. Eliminar mutación pendiente del tenant actual
      await localDb.execute(
        "DELETE FROM table_local_mutations WHERE table_uuid = ? AND company_id = ? AND branch_id = ?",
        [tableUuid, ctx.company_id, ctx.branch_id]
      );

      // 2. Actualizar local_tables del tenant actual
      await localDb.execute(
        `UPDATE local_tables
         SET status = 'available',
             current_order_uuid = NULL,
             last_updated = CURRENT_TIMESTAMP
         WHERE uuid = ? AND company_id = ? AND branch_id = ?`,
        [tableUuid, ctx.company_id, ctx.branch_id]
      );

      console.log("[localTablesService] ✅ Mutación eliminada + local_tables actualizado");
    } catch (error) {
      console.error("[localTablesService] ❌ Error en markAvailable:", error);
      throw error;
    }
  },

  /**
   * Limpia la mutación pendiente para una mesa específica.
   * ADR-012: Requiere tenant explícito (llamado desde PullEngine).
   */
  async clearMutation(
    tableUuid: string,
    companyId: string,
    branchId: string
  ): Promise<void> {
    await localDb.execute(
      "DELETE FROM table_local_mutations WHERE table_uuid = ? AND company_id = ? AND branch_id = ?",
      [tableUuid, companyId, branchId]
    );
  },

  /**
   * Limpia TODAS las mutaciones pendientes del tenant actual.
   * Usado tras full sync exitoso o logout.
   */
  async clearAllMutations(): Promise<number> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localTablesService] ⚠️ Sin contexto, no se pueden limpiar mutaciones");
      return 0;
    }

    const countRows = await localDb.select<{ count: number }>(
      "SELECT COUNT(*) as count FROM table_local_mutations WHERE company_id = ? AND branch_id = ?",
      [ctx.company_id, ctx.branch_id]
    );
    const count = countRows[0]?.count || 0;
    await localDb.execute(
      "DELETE FROM table_local_mutations WHERE company_id = ? AND branch_id = ?",
      [ctx.company_id, ctx.branch_id]
    );
    console.log(`[localTablesService] 🧹 ${count} mutaciones eliminadas para tenant ${ctx.company_id}/${ctx.branch_id}`);
    return count;
  },
};
