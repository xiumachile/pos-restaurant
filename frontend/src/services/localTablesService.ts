import { localDb } from "@/db/localDb";
import type { RestaurantTable, TableStatus } from "@/types/tables";

export const localTablesService = {
  /**
   * Lee el status actual de todas las mesas desde SQLite local.
   * Retorna un mapa uuid -> status para aplicar como overlay sobre datos del cloud.
   */
  async getStatusOverrides(): Promise<Map<string, string>> {
    const db = await localDb.getConnection();
    const tables = await db.select<{ uuid: string; status: string }[]>(
      "SELECT uuid, status FROM local_tables"
    );

    const map = new Map<string, string>();
    tables.forEach(t => map.set(t.uuid, t.status));
    return map;
  },

  /**
   * Lee TODAS las mesas desde SQLite con toda su información.
   * Usado como fallback cuando no hay caché del backend disponible.
   *
   * Nota: local_tables no tiene area_code ni current_order_id/has_active_order,
   * por lo que se reconstruyen a partir de area_name y current_order_uuid.
   */
  async getAllTables(): Promise<RestaurantTable[]> {
    const db = await localDb.getConnection();
    const tables = await db.select<any[]>(
      `SELECT uuid, table_number, area_name, capacity, status, 
              current_order_uuid, last_updated
       FROM local_tables
       ORDER BY area_name, table_number`
    );

    return tables.map(t => ({
      uuid: t.uuid,
      table_number: t.table_number,
      area_code: (t.area_name || "sin_area").toLowerCase().replace(/\s+/g, "_"),
      area_name: t.area_name || "Sin área",
      capacity: t.capacity || 4,
      status: (t.status || "available") as TableStatus,
      has_active_order: !!t.current_order_uuid,
      current_order_id: null, // No disponible en SQLite
      created_at: t.last_updated || new Date().toISOString(),
      updated_at: t.last_updated || new Date().toISOString(),
    }));
  },

  /**
   * Marca una mesa como ocupada (update optimista al crear pedido).
   * Centraliza el UPDATE de local_tables para mantener consistencia.
   */
  async markOccupied(tableUuid: string, orderLocalUuid: string): Promise<void> {
    console.log("[localTablesService] 🪑 markOccupied llamado:", { tableUuid, orderLocalUuid });
    const result = await localDb.execute(
      "UPDATE local_tables SET status = 'occupied', current_order_uuid = ?, last_updated = CURRENT_TIMESTAMP WHERE uuid = ?",
      [orderLocalUuid, tableUuid]
    );
    console.log("[localTablesService] ✅ UPDATE ejecutado, filas afectadas:", result);
  },

  /**
   * Marca una mesa como disponible (update optimista tras pago).
   * Centraliza el UPDATE de local_tables para mantener consistencia.
   */
  async markAvailable(tableUuid: string): Promise<void> {
    await localDb.execute(
      "UPDATE local_tables SET status = 'available', current_order_uuid = NULL, last_updated = CURRENT_TIMESTAMP WHERE uuid = ?",
      [tableUuid]
    );
  },

  /**
   * Limpia TODOS los overrides de mesas en SQLite.
   * Usar después de sync exitoso para confiar en el estado del backend.
   */
  async clearAllOverrides(): Promise<void> {
    await localDb.execute(
      "UPDATE local_tables SET status = 'available', current_order_uuid = NULL, last_updated = CURRENT_TIMESTAMP WHERE current_order_uuid IS NOT NULL"
    );
  },
};
