import type { Product } from "./catalog";
import type { ChannelType } from "@/stores/useActiveChannelStore";

export interface CartItem {
  id: string;
  product: Product;
  quantity: number;
  notes?: string;
}

/**
 * Carrito de un pedido específico.
 * 
 * Clave (key) en el objeto carts:
 *   - Para mesa física: el `tableUuid` de la mesa
 *   - Para pedido fuera de mesa (delivery/takeout): string `takeaway-{uuid}`
 * 
 * El canal se fija al crear el pedido y NO cambia durante su vida.
 * Esto garantiza que el canal de un pedido no se filtra a otro.
 */
export interface TableCart {
  /** ID del pedido existente que se está editando (null si es nuevo) */
  editingOrderId?: string | null;
  /** UUID de la mesa, o null si es pedido fuera de mesa */
  tableUuid: string | null;
  /** Número de mesa (display). Vacío para pedidos fuera de mesa */
  tableNumber: string;
  /** Nombre del área. Opcional */
  areaName?: string;
  /** Canal de venta fijado al crear el pedido (H5 - fase 2) */
  channel: ChannelType;
  /** Items del pedido */
  items: CartItem[];
  /** Timestamp de creación */
  createdAt: string;
}

export interface CartTotals {
  subtotal: number;
  tax: number;
  total: number;
  itemCount: number;
}
