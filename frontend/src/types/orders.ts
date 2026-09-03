export type OrderStatus =
  // Estados base
  | "draft"
  | "confirmed"
  | "preparing"
  | "ready"
  // Estados específicos por canal de fulfillment
  | "ready_for_pickup"  // pickup: listo para retirar
  | "picked_up"         // pickup: cliente retiró
  | "dispatched"        // delivery: salió a entregar
  | "delivered"         // delivery: entregado al cliente
  // Estados compartidos
  | "served"            // dine_in: servido en mesa
  | "paid"
  | "closed"
  | "cancelled";

export type OrderType = "dine_in" | "takeout" | "delivery";

export interface OrderItem {
  uuid: string;
  menu_item_uuid: string;
  name: string;
  unit_price: number;
  quantity: number;
  subtotal: number;
  notes?: string | null;
  created_at: string;
}

export interface Order {
  uuid: string;
  order_number: string;
  type: OrderType;
  type_label: string;
  status: OrderStatus;
  is_editable: boolean;
  is_active: boolean;
  table?: {
    uuid: string;
    table_number: string;
    area_code: string;
  } | null;
  waiter?: {
    uuid: string;
    name: string;
  } | null;
  items: OrderItem[];
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  notes: string | null;
  confirmed_at: string | null;
  served_at: string | null;
  paid_at: string | null;
  closed_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateOrderPayload {
  type: OrderType;
  table_uuid?: string;
  notes?: string;
}

export interface AddItemPayload {
  menu_item_uuid: string;
  quantity: number;
  notes?: string;
}

/**
 * Item agrupado (suma cantidades del mismo producto entre varias órdenes).
 */
export interface AggregatedItem {
  /** Clave única: menu_item_uuid (para agrupar) */
  key: string;
  name: string;
  totalQuantity: number;
  unitPrice: number;
  subtotal: number;
  /** Notas únicas encontradas */
  notes: string[];
  /** Detalles de cada orden origen */
  sources: {
    orderUuid: string;
    orderNumber: string;
    status: OrderStatus;
    quantity: number;
    confirmedAt: string | null;
  }[];
}

/**
 * Resultado de agrupar items de varias órdenes.
 */
export interface AggregatedOrders {
  items: AggregatedItem[];
  totalQuantity: number;
  distinctProducts: number;
  subtotal: number;
  tax: number;
  total: number;
  ordersCount: number;
}

/**
 * Agrupa items de varias órdenes por menu_item_uuid.
 * Suma cantidades del mismo producto, mantiene trazabilidad de órdenes.
 */
export function aggregateOrders(orders: Order[]): AggregatedOrders {
  const itemsMap = new Map<string, AggregatedItem>();

  for (const order of orders) {
    for (const item of order.items) {
      // Usar menu_item_uuid si existe, sino usar nombre del producto como fallback
      // Esto previene que items sin menu_item_uuid (null) se agrupen incorrectamente
      const key = item.menu_item_uuid || `name:${item.name}`;
      const existing = itemsMap.get(key);

      if (existing) {
        existing.totalQuantity += item.quantity;
        existing.subtotal += item.subtotal;
        if (item.notes && !existing.notes.includes(item.notes)) {
          existing.notes.push(item.notes);
        }
        existing.sources.push({
          orderUuid: order.uuid,
          orderNumber: order.order_number,
          status: order.status,
          quantity: item.quantity,
          confirmedAt: order.confirmed_at,
        });
      } else {
        itemsMap.set(key, {
          key,
          name: item.name,
          totalQuantity: item.quantity,
          unitPrice: item.unit_price,
          subtotal: item.subtotal,
          notes: item.notes ? [item.notes] : [],
          sources: [
            {
              orderUuid: order.uuid,
              orderNumber: order.order_number,
              status: order.status,
              quantity: item.quantity,
              confirmedAt: order.confirmed_at,
            },
          ],
        });
      }
    }
  }

  const items = Array.from(itemsMap.values());
  const totalQuantity = items.reduce((sum, i) => sum + i.totalQuantity, 0);
  const subtotal = orders.reduce((sum, o) => sum + (o.subtotal || 0), 0);
  const tax = orders.reduce((sum, o) => sum + (o.tax_amount || 0), 0);
  const total = orders.reduce((sum, o) => sum + (o.total || 0), 0);

  return {
    items,
    totalQuantity,
    distinctProducts: items.length,
    subtotal,
    tax,
    total,
    ordersCount: orders.length,
  };
}
