import { localDb } from "@/db/localDb";

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
   * Marca una mesa como ocupada (update optimista al crear pedido).
   * Centraliza el UPDATE de local_tables para mantener consistencia.
   */
  async markOccupied(tableUuid: string, orderLocalUuid: string): Promise<void> {
    await localDb.execute(
      "UPDATE local_tables SET status = 'occupied', current_order_uuid = ?, last_updated = CURRENT_TIMESTAMP WHERE uuid = ?",
      [orderLocalUuid, tableUuid]
    );
    console.log(`[localTablesService] 🪑 Mesa ${tableUuid} marcada como occupied (optimista)`);
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
    console.log(`[localTablesService] ✅ Mesa ${tableUuid} marcada como available`);
  },

  /**
   * Limpia TODOS los overrides de mesas en SQLite.
   * Usar después de pago exitoso para confiar en el estado del backend.
   * Esto resuelve el problema donde el overlay optimista persiste
   * después de que el backend ya liberó la mesa.
   */
  async clearAllOverrides(): Promise<void> {
    await localDb.execute(
      "UPDATE local_tables SET status = 'available', current_order_uuid = NULL, last_updated = CURRENT_TIMESTAMP WHERE current_order_uuid IS NOT NULL"
    );
    console.log(`[localTablesService] 🧹 Todos los overrides de mesas limpiados`);
  },
};
