import { localDb } from "@/db/localDb";
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
 */
export const localTablesService = {
  /**
   * Obtiene todas las mutaciones pendientes.
   */
  async getPendingMutations(): Promise<TableMutation[]> {
    const db = await localDb.getConnection();
    return await db.select<TableMutation[]>(
      "SELECT * FROM table_local_mutations ORDER BY created_at ASC"
    );
  },

  /**
   * Obtiene la mutación pendiente para una mesa específica (si existe).
   */
  async getMutation(tableUuid: string): Promise<TableMutation | null> {
    const db = await localDb.getConnection();
    const rows = await db.select<TableMutation[]>(
      "SELECT * FROM table_local_mutations WHERE table_uuid = ?",
      [tableUuid]
    );
    return rows[0] || null;
  },

  /**
   * Lee el status visible de todas las mesas.
   * Prioriza mutaciones locales sobre estado del cloud.
   *
   * Retorna un mapa uuid -> status para aplicar como overlay.
   */
  async getStatusOverrides(): Promise<Map<string, string>> {
    const db = await localDb.getConnection();

    // 1. Obtener mutaciones pendientes (prioridad alta)
    const mutations = await db.select<TableMutation[]>(
      "SELECT table_uuid, pending_status FROM table_local_mutations"
    );

    const map = new Map<string, string>();

    // Mutaciones tienen prioridad absoluta
    for (const m of mutations) {
      map.set(m.table_uuid, m.pending_status);
    }

    // 2. Para mesas SIN mutación, usar estado del cloud (local_tables)
    // Esto es importante para modo offline cuando no hay caché del backend
    if (mutations.length < 100) {
      // Solo si hay pocas mutaciones, traer el resto desde local_tables
      // para tener información completa en fallback offline
      const cloudTables = await db.select<{ uuid: string; status: string }[]>(
        "SELECT uuid, status FROM local_tables"
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
   * Lee TODAS las mesas desde SQLite con toda su información.
   * Usado como fallback cuando no hay caché del backend disponible.
   *
   * El status visible se calcula priorizando mutaciones locales.
   */
  async getAllTables(): Promise<RestaurantTable[]> {
    const db = await localDb.getConnection();

    // Traer todo en una sola query con LEFT JOIN
    const tables = await db.select<any[]>(
      `SELECT 
         t.uuid, t.table_number, t.area_name, t.capacity, 
         t.status AS cloud_status, t.current_order_uuid AS cloud_order_uuid,
         t.last_updated,
         m.pending_status, m.pending_order_uuid
       FROM local_tables t
       LEFT JOIN table_local_mutations m ON t.uuid = m.table_uuid
       ORDER BY t.area_name, t.table_number`
    );

    return tables.map(t => {
      // Prioridad: mutación local > cloud
      const visibleStatus = t.pending_status || t.cloud_status || "available";
      const visibleOrderUuid = t.pending_order_uuid || t.cloud_order_uuid;

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
  },

  /**
   * Marca una mesa como ocupada (update optimista al crear pedido offline).
   *
   * IMPLEMENTACIÓN FASE 4:
   * 1. INSERT/REPLACE en table_local_mutations (marca mutación pendiente)
   * 2. UPDATE de local_tables.status (para reflejo inmediato en UI)
   *
   * PullEngine detectará la mutación y NO sobrescribirá el estado
   * hasta que la orden se sincronice exitosamente.
   */
  async markOccupied(tableUuid: string, orderLocalUuid: string): Promise<void> {
    console.log("[localTablesService] 🪑 markOccupied:", { tableUuid, orderLocalUuid });

    const db = await localDb.getConnection();

    await db.execute("BEGIN TRANSACTION");
    try {
      // 1. Registrar mutación pendiente (autoridad principal)
      await db.execute(
        `INSERT OR REPLACE INTO table_local_mutations 
         (table_uuid, pending_status, pending_order_uuid, created_at)
         VALUES (?, 'occupied', ?, CURRENT_TIMESTAMP)`,
        [tableUuid, orderLocalUuid]
      );

      // 2. Actualizar local_tables para reflejo inmediato
      await db.execute(
        `UPDATE local_tables 
         SET status = 'occupied', 
             current_order_uuid = ?, 
             last_updated = CURRENT_TIMESTAMP 
         WHERE uuid = ?`,
        [orderLocalUuid, tableUuid]
      );

      await db.execute("COMMIT");
      console.log("[localTablesService] ✅ Mutación registrada + local_tables actualizado");
    } catch (error) {
      await db.execute("ROLLBACK");
      console.error("[localTablesService] ❌ Error en markOccupied:", error);
      throw error;
    }
  },

  /**
   * Marca una mesa como disponible (update optimista tras pago).
   *
   * Elimina la mutación pendiente y actualiza local_tables.
   */
  async markAvailable(tableUuid: string): Promise<void> {
    console.log("[localTablesService] 🟢 markAvailable:", tableUuid);

    const db = await localDb.getConnection();

    await db.execute("BEGIN TRANSACTION");
    try {
      // 1. Eliminar mutación pendiente
      await db.execute(
        "DELETE FROM table_local_mutations WHERE table_uuid = ?",
        [tableUuid]
      );

      // 2. Actualizar local_tables
      await db.execute(
        `UPDATE local_tables 
         SET status = 'available', 
             current_order_uuid = NULL, 
             last_updated = CURRENT_TIMESTAMP 
         WHERE uuid = ?`,
        [tableUuid]
      );

      await db.execute("COMMIT");
      console.log("[localTablesService] ✅ Mutación eliminada + local_tables actualizado");
    } catch (error) {
      await db.execute("ROLLBACK");
      console.error("[localTablesService] ❌ Error en markAvailable:", error);
      throw error;
    }
  },

  /**
   * Limpia la mutación pendiente para una mesa específica.
   * Usado por PullEngine DESPUÉS de confirmar que el cloud refleja el estado.
   *
   * NO toca local_tables (el PullEngine ya lo actualizó).
   */
  async clearMutation(tableUuid: string): Promise<void> {
    await localDb.execute(
      "DELETE FROM table_local_mutations WHERE table_uuid = ?",
      [tableUuid]
    );
  },

  /**
   * Limpia TODAS las mutaciones pendientes.
   * Usado tras full sync exitoso o logout.
   */
  async clearAllMutations(): Promise<number> {
    const db = await localDb.getConnection();
    const count = await db.select<{ count: number }[]>(
      "SELECT COUNT(*) as count FROM table_local_mutations"
    );
    const result = await db.execute("DELETE FROM table_local_mutations");
    console.log(`[localTablesService] 🧹 ${count[0]?.count || 0} mutaciones eliminadas`);
    return result;
  },
};
