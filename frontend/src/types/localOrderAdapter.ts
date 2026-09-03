import type { Order, OrderItem, OrderStatus, OrderType } from "./orders";
import { isValidLocalStatus } from "@/services/sync/statusMapper";
import type { LocalOrder, LocalOrderItem } from "../db/repositories/OrderRepository";

/**
 * Extensión del tipo Order con metadatos locales.
 * Permite a la UI distinguir pedidos cloud de locales sin sincronizar.
 */
export interface OrderWithSource extends Order {
  _isLocal?: boolean;
  _syncStatus?: string;
  _localUuid?: string;
}

/**
 * Convierte un LocalOrderItem al formato OrderItem que espera aggregateOrders.
 * 
 * Nota: aggregateOrders usa `menu_item_uuid` como key de agrupación.
 * Para pedidos locales usamos `product_id` (UUID del producto) como key,
 * lo que garantiza que productos iguales se agrupen correctamente.
 */
function adaptLocalItem(item: LocalOrderItem): OrderItem {
  return {
    uuid: item.local_uuid,
    menu_item_uuid: item.product_id, // product_uuid como agrupador
    name: item.product_name,
    unit_price: item.unit_price,
    quantity: item.quantity,
    subtotal: item.subtotal,
    notes: item.notes ?? null,
    created_at: item.created_at,
  };
}

/**
 * Convierte un LocalOrder al formato Order compatible con ActiveOrderItems.
 * Agrega metadatos (_isLocal, _syncStatus) para badges en UI.
 */
export function adaptLocalOrder(
  order: LocalOrder,
  items: LocalOrderItem[]
): OrderWithSource {
  const adaptedItems = items.map(adaptLocalItem);

  // Calcular subtotal desde items si no está en el order
  const subtotal = order.subtotal ?? adaptedItems.reduce((sum, i) => sum + i.subtotal, 0);
  const taxAmount = (order as any).tax_amount ?? Math.round(subtotal * 0.19);
  const total = order.grand_total ?? (subtotal + taxAmount);

  return {
    uuid: order.cloud_id || order.local_uuid,
    order_number: order.order_number || `TEMP-${order.local_uuid.slice(0, 8)}`,
    type: (order.order_type as OrderType) || "dine_in",
    type_label: order.order_type === "dine_in" ? "En mesa" : order.order_type || "dine_in",
    status: (isValidLocalStatus(order.status) 
      ? (order.status as OrderStatus) 
      : "confirmed"),
    is_editable: true,
    is_active: true,
    items: adaptedItems,
    subtotal,
    tax_amount: taxAmount,
    discount_amount: 0,
    total,
    notes: order.notes ?? null,
    confirmed_at: order.created_at,
    served_at: null,
    paid_at: null,
    closed_at: null,
    cancelled_at: null,
    cancellation_reason: null,
    created_at: order.created_at,
    updated_at: order.updated_at,
    // Metadatos locales
    _isLocal: true,
    _syncStatus: order.sync_status,
    _localUuid: order.local_uuid,
  };
}
