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
  }
};
