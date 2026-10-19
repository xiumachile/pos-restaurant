import { localDb } from "@/db/localDb";
import type { TableBill, TableBillOrder, TableBillOrderItem } from "@/types/tableBill";

interface LocalOrderRow {
  local_uuid: string;
  table_id: string | null;
  order_type: string;
  order_number: string;
  status: string;
  subtotal: number;
  tax_total: number;
  grand_total: number;
  created_at: string;
  customer_phone?: string;
}

interface LocalItemRow {
  local_uuid: string;
  order_local_uuid: string;
  product_name: string;
  quantity: number;
  unit_price: number;
  notes: string | null;
}

interface TableInfoRow {
  uuid: string;
  table_number: string;
  area_name: string | null;
  capacity: number;
}

export interface PendingAccount {
  id: string;
  type: 'table' | 'delivery' | 'takeout';
  identifier: string;
  area_code?: string;
  orders_count: number;
  total_items: number;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  first_order_at: string | null;
  last_order_at: string | null;
  has_unserved_orders: boolean;
  unserved_orders_count: number;
  unserved_items_count: number;
  orders: TableBillOrder[];
}

/**
 * Servicio para operaciones de pagos que requieren acceso a datos locales.
 */
export const localPaymentsService = {
  /**
   * Reconstruye la lista de TODAS las cuentas pendientes (mesas, delivery, takeout) desde SQLite.
   */
  async listPendingAccountsOffline(): Promise<PendingAccount[]> {
    console.log("[localPaymentsService] 📋 listPendingAccountsOffline() llamado");

    const db = await localDb.getConnection();

    // 1. Obtener TODOS los pedidos activos (no cerrados, cancelados o pagados)
    const ordersResult = await db.select<LocalOrderRow[]>(`
      SELECT local_uuid, table_id, order_type, order_number, status, 
             subtotal, tax_total, grand_total, created_at, customer_phone
      FROM local_orders
      WHERE status NOT IN ('closed', 'cancelled', 'paid')
      ORDER BY created_at ASC
    `);

    // Aplanar resultados de SQLite (a veces vienen como array de arrays o array de objetos)
    const orders = ordersResult.map((row: any) => (Array.isArray(row) ? row[0] : row));

    console.log(`[localPaymentsService] 📦 Pedidos activos encontrados: ${orders.length}`);

    if (orders.length === 0) {
      return [];
    }

    // 2. Agrupar pedidos por cuenta (mesa o pedido individual de delivery/takeout)
    const accountsMap = new Map<string, any>();

    for (const order of orders) {
      const isTable = !!order.table_id;
      const accountId = isTable ? order.table_id : order.local_uuid;
      
      if (!accountsMap.has(accountId)) {
        if (isTable) {
          accountsMap.set(accountId, {
            id: accountId,
            type: 'table' as const,
            table_id: order.table_id,
            orders: [],
          });
        } else {
          accountsMap.set(accountId, {
            id: accountId,
            type: (order.order_type === 'delivery' ? 'delivery' : 'takeout') as 'delivery' | 'takeout',
            identifier: order.customer_phone || 'Sin cliente',
            orders: [],
          });
        }
      }
      accountsMap.get(accountId).orders.push(order);
    }

    // 3. Obtener info de mesas para las cuentas de tipo 'table'
    const tableUuids = Array.from(accountsMap.values())
      .filter((a: any) => a.type === 'table')
      .map((a: any) => a.table_id);
    
    let tablesMap = new Map<string, TableInfoRow>();
    if (tableUuids.length > 0) {
      const tablesInfoResult = await db.select<TableInfoRow[]>(`
        SELECT uuid, table_number, area_name, capacity
        FROM local_tables
        WHERE uuid IN (${tableUuids.map(() => "?").join(",")})
      `, tableUuids);
      
      const tablesInfo = tablesInfoResult.map((row: any) => (Array.isArray(row) ? row[0] : row));
      tablesMap = new Map(tablesInfo.map((t: TableInfoRow) => [t.uuid, t]));
    }

    // 4. Obtener todos los items de estos pedidos
    const orderUuids = orders.map((o: LocalOrderRow) => o.local_uuid);
    const itemsResult = await db.select<LocalItemRow[]>(`
      SELECT local_uuid, order_local_uuid, product_name, quantity, unit_price, notes
      FROM local_order_items
      WHERE order_local_uuid IN (${orderUuids.map(() => "?").join(",")})
    `, orderUuids);

    const items = itemsResult.map((row: any) => (Array.isArray(row) ? row[0] : row));

    const itemsByOrder = new Map<string, LocalItemRow[]>();
    for (const item of items) {
      const existing = itemsByOrder.get(item.order_local_uuid) || [];
      existing.push(item);
      itemsByOrder.set(item.order_local_uuid, existing);
    }

    // 5. Construir el resultado final
    const result: PendingAccount[] = [];

    for (const account of accountsMap.values()) {
      if (account.type === 'table') {
        const tableInfo = tablesMap.get(account.table_id);
        if (!tableInfo) continue;

        const areaCode = (tableInfo.area_name || "sin_area").toLowerCase().replace(/\s+/g, "_");
        
        const tableOrders: TableBillOrder[] = account.orders.map((o: LocalOrderRow) => {
          const orderItems = itemsByOrder.get(o.local_uuid) || [];
          return {
            uuid: o.local_uuid,
            order_number: o.order_number,
            status: o.status,
            subtotal: o.subtotal || 0,
            tax_amount: o.tax_total || 0,
            total: o.grand_total || 0,
            waiter_name: null,
            served_at: null,
            items: orderItems.map((i: LocalItemRow) => ({
              uuid: i.local_uuid,
              name: i.product_name,
              quantity: i.quantity,
              unit_price: i.unit_price,
              subtotal: i.quantity * i.unit_price,
              notes: i.notes,
            })),
            bills: [],
          };
        });

        const subtotal = tableOrders.reduce((sum: number, o: TableBillOrder) => sum + o.subtotal, 0);
        const taxAmount = tableOrders.reduce((sum: number, o: TableBillOrder) => sum + o.tax_amount, 0);
        const totalAmount = tableOrders.reduce((sum: number, o: TableBillOrder) => sum + o.total, 0);
        const totalItems = tableOrders.reduce((sum: number, o: TableBillOrder) => sum + o.items.reduce((s: number, i: any) => s + i.quantity, 0), 0);

        const dates: string[] = account.orders.map((o: LocalOrderRow) => o.created_at).sort();
        const unservedOrders = account.orders.filter((o: LocalOrderRow) => o.status === "confirmed" || o.status === "preparing");
        const unservedItemsCount = unservedOrders.reduce((sum: number, o: LocalOrderRow) => sum + (itemsByOrder.get(o.local_uuid)?.length || 0), 0);

        result.push({
          id: account.table_id,
          type: 'table',
          identifier: tableInfo.table_number,
          area_code: areaCode,
          orders_count: account.orders.length,
          total_items: totalItems,
          subtotal,
          tax_amount: taxAmount,
          total_amount: totalAmount,
          first_order_at: dates[0] || null,
          last_order_at: dates[dates.length - 1] || null,
          has_unserved_orders: unservedOrders.length > 0,
          unserved_orders_count: unservedOrders.length,
          unserved_items_count: unservedItemsCount,
          orders: tableOrders,
        });
      } else {
        // Delivery o Takeout (cada pedido es una cuenta individual)
        for (const o of account.orders) {
          const orderItems = itemsByOrder.get(o.local_uuid) || [];
          const totalItems = orderItems.reduce((sum: number, i: LocalItemRow) => sum + i.quantity, 0);
          const dates = [o.created_at];
          const unservedOrdersCount = (o.status === "confirmed" || o.status === "preparing") ? 1 : 0;

          result.push({
            id: o.local_uuid,
            type: account.type,
            identifier: account.identifier,
            area_code: o.order_type === 'delivery' ? 'delivery' : 'takeout',
            orders_count: 1,
            total_items: totalItems,
            subtotal: o.subtotal || 0,
            tax_amount: o.tax_total || 0,
            total_amount: o.grand_total || 0,
            first_order_at: dates[0] || null,
            last_order_at: dates[0] || null,
            has_unserved_orders: unservedOrdersCount > 0,
            unserved_orders_count: unservedOrdersCount,
            unserved_items_count: unservedOrdersCount > 0 ? totalItems : 0,
            orders: [{
              uuid: o.local_uuid,
              order_number: o.order_number,
              status: o.status,
              subtotal: o.subtotal || 0,
              tax_amount: o.tax_total || 0,
              total: o.grand_total || 0,
              waiter_name: null,
              served_at: null,
              items: orderItems.map((i: LocalItemRow) => ({
                uuid: i.local_uuid,
                name: i.product_name,
                quantity: i.quantity,
                unit_price: i.unit_price,
                subtotal: i.quantity * i.unit_price,
                notes: i.notes,
              })),
              bills: [],
            }],
          });
        }
      }
    }

    console.log(`[localPaymentsService] ✅ Retornando ${result.length} cuentas pendientes`);
    return result;
  },
};
