import { localDb } from "@/db/localDb";
import type { TableBill, TableBillOrder, TableBillOrderItem } from "@/types/tableBill";

interface LocalOrderRow {
  local_uuid: string;
  table_id: string;
  order_number: string;
  status: string;
  subtotal: number;
  tax_total: number;
  grand_total: number;
  created_at: string;
}

interface LocalItemRow {
  local_uuid: string;
  order_local_uuid: string;
  product_id: string;
  product_name: string;
  quantity: number;
  unit_price: number;
  notes: string | null;
}

interface TableInfoRow {
  uuid: string;
  table_number: string;
  area_code: string;
  area_name: string;
  capacity: number;
}

/**
 * Servicio para operaciones de pagos que requieren acceso a datos locales.
 * 
 * Proporciona fallback offline para funcionalidades que normalmente
 * dependen del backend.
 */
export const localPaymentsService = {
  /**
   * Reconstruye la lista de mesas con cuentas pendientes desde SQLite.
   * 
   * Equivalente offline de paymentsService.listTablesWithBills().
   * Útil cuando el backend no está disponible.
   * 
   * Flujo:
   * 1. Lee todos los pedidos locales con status !== closed/cancelled
   * 2. Agrupa por table_id
   * 3. Lee items de cada pedido
   * 4. Lee info de mesa desde local_tables
   * 5. Construye estructura TableBill[] idéntica al backend
   */
  async listTablesWithBillsOffline(): Promise<TableBill[]> {
    const db = await localDb.getConnection();

    // 1. Obtener pedidos activos (no cerrados ni cancelados)
    const orders = await db.select<LocalOrderRow[]>(`
      SELECT local_uuid, table_id, order_number, status, 
             subtotal, tax_total, grand_total, created_at
      FROM local_orders
      WHERE table_id IS NOT NULL
        AND status NOT IN ('closed', 'cancelled')
      ORDER BY created_at ASC
    `);

    if (orders.length === 0) return [];

    // 2. Agrupar pedidos por table_id
    const ordersByTable = new Map<string, LocalOrderRow[]>();
    for (const order of orders) {
      const existing = ordersByTable.get(order.table_id) || [];
      existing.push(order);
      ordersByTable.set(order.table_id, existing);
    }

    // 3. Obtener info de mesas
    const tableUuids = Array.from(ordersByTable.keys());
    const tablesInfo = await db.select<TableInfoRow[]>(`
      SELECT uuid, table_number, area_code, area_name, capacity
      FROM local_tables
      WHERE uuid IN (${tableUuids.map(() => "?").join(",")})
    `, tableUuids);

    const tablesMap = new Map(tablesInfo.map(t => [t.uuid, t]));

    // 4. Obtener todos los items de estos pedidos
    const orderUuids = orders.map(o => o.local_uuid);
    const items = await db.select<LocalItemRow[]>(`
      SELECT local_uuid, order_local_uuid, product_id, product_name,
             quantity, unit_price, notes
      FROM local_order_items
      WHERE order_local_uuid IN (${orderUuids.map(() => "?").join(",")})
    `, orderUuids);

    // Agrupar items por pedido
    const itemsByOrder = new Map<string, LocalItemRow[]>();
    for (const item of items) {
      const existing = itemsByOrder.get(item.order_local_uuid) || [];
      existing.push(item);
      itemsByOrder.set(item.order_local_uuid, existing);
    }

    // 5. Construir TableBill[] para cada mesa
    const result: TableBill[] = [];

    for (const [tableUuid, tableOrders] of ordersByTable.entries()) {
      const tableInfo = tablesMap.get(tableUuid);
      if (!tableInfo) continue;

      // Construir objetos TableBillOrder
      const tableBillOrders: TableBillOrder[] = tableOrders.map(order => {
        const orderItems = itemsByOrder.get(order.local_uuid) || [];

        const items: TableBillOrderItem[] = orderItems.map(item => ({
          uuid: item.local_uuid,
          name: item.product_name,
          quantity: item.quantity,
          unit_price: item.unit_price,
          subtotal: item.quantity * item.unit_price,
          notes: item.notes,
        }));

        return {
          uuid: order.local_uuid,
          order_number: order.order_number,
          status: order.status,
          subtotal: order.subtotal || 0,
          tax_amount: order.tax_total || 0,
          total: order.grand_total || 0,
          waiter_name: null, // No disponible localmente
          served_at: null,   // No disponible localmente
          items,
          bills: [], // Las bills solo existen en el backend
        };
      });

      // Calcular totales de la mesa
      const subtotal = tableBillOrders.reduce((sum, o) => sum + o.subtotal, 0);
      const taxAmount = tableBillOrders.reduce((sum, o) => sum + o.tax_amount, 0);
      const totalAmount = tableBillOrders.reduce((sum, o) => sum + o.total, 0);
      const totalItems = tableBillOrders.reduce(
        (sum, o) => sum + o.items.reduce((s, i) => s + i.quantity, 0), 0
      );

      // Timestamps
      const dates = tableOrders.map(o => o.created_at).sort();
      const firstOrderAt = dates[0] || null;
      const lastOrderAt = dates[dates.length - 1] || null;

      // Detectar pedidos no servidos (status = confirmed/preparing)
      const unservedOrders = tableOrders.filter(o =>
        o.status === "confirmed" || o.status === "preparing"
      );
      const unservedItemsCount = unservedOrders.reduce(
        (sum, o) => sum + (itemsByOrder.get(o.local_uuid)?.length || 0),
        0
      );

      result.push({
        table_uuid: tableUuid,
        table_number: tableInfo.table_number,
        area_code: tableInfo.area_code || "unknown",
        capacity: tableInfo.capacity || 4,
        orders_count: tableOrders.length,
        total_items: totalItems,
        subtotal,
        tax_amount: taxAmount,
        total_amount: totalAmount,
        first_order_at: firstOrderAt,
        last_order_at: lastOrderAt,
        has_unserved_orders: unservedOrders.length > 0,
        unserved_orders_count: unservedOrders.length,
        unserved_items_count: unservedItemsCount,
        orders: tableBillOrders,
      });
    }

    return result;
  },
};
