import { localDb } from "../localDb";
import { SyncQueueRepository } from "./SyncQueueRepository";
import { v4 as uuidv4 } from "uuid";

export interface LocalOrder {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  table_id: string | null;
  order_number: string;
  order_type: "dine_in" | "take_out" | "delivery";
  status: "open" | "confirmed" | "preparing" | "ready" | "served" | "paid" | "cancelled";
  subtotal: number;
  discount_total: number;
  tax_total: number;
  tip_amount: number;
  grand_total: number;
  guest_count: number;
  waiter_id: string | null;
  waiter_name: string | null;
  notes: string | null;
  idempotency_key: string;
  sync_status: "pending" | "syncing" | "synced" | "failed";
  created_at: string;
  updated_at: string;
}

export interface LocalOrderItem {
  local_uuid: string;
  order_local_uuid: string;
  cloud_id: string | null;
  product_id: string;
  product_name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  notes: string | null;
  kitchen_status: "pending" | "preparing" | "ready" | "delivered";
  is_menu_item: boolean;
  menu_item_id: string | null;
  created_at: string;
}

export interface CreateOrderPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  table_id?: string;
  order_type?: "dine_in" | "take_out" | "delivery";
  waiter_id?: string;
  waiter_name?: string;
  guest_count?: number;
  notes?: string;
}

export class OrderRepository {
  /**
   * Crea un nuevo pedido local con UUID único y idempotency key.
   */
  static async create(payload: CreateOrderPayload): Promise<LocalOrder> {
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();
    const order_number = `TEMP-${Date.now()}`;

    await localDb.execute(
      `INSERT INTO local_orders (
        local_uuid, company_id, branch_id, terminal_id, table_id,
        order_number, order_type, status, subtotal, discount_total,
        tax_total, tip_amount, grand_total, guest_count,
        waiter_id, waiter_name, notes, idempotency_key, sync_status,
        created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
      [
        local_uuid,
        payload.company_id,
        payload.branch_id,
        payload.terminal_id || null,
        payload.table_id || null,
        order_number,
        payload.order_type || "dine_in",
        "confirmed",
        payload.guest_count || 1,
        payload.waiter_id || null,
        payload.waiter_name || null,
        payload.notes || null,
        idempotency_key,
      ]
    );

    // Encolar evento de sincronización
    const syncPayload = {
      local_uuid,
      company_id: payload.company_id,
      branch_id: payload.branch_id,
      terminal_id: payload.terminal_id || null,
      table_id: payload.table_id || null,
      order_number,
      order_type: payload.order_type || "dine_in",
      status: "confirmed",
      subtotal: 0,
      discount_total: 0,
      tax_total: 0,
      tip_amount: 0,
      grand_total: 0,
      guest_count: payload.guest_count || 1,
      waiter_id: payload.waiter_id || null,
      waiter_name: payload.waiter_name || null,
      notes: payload.notes || null,
      idempotency_key,
      items: [], // Se actualizarán cuando se agreguen items
    };

    await SyncQueueRepository.enqueue({
      company_id: payload.company_id,
      branch_id: payload.branch_id,
      entity_type: 'order',
      entity_local_uuid: local_uuid,
      action: 'create',
      payload: syncPayload,
    });

    console.log(`[OrderRepository] 📤 Pedido encolado para sync: ${local_uuid}`);

    return await this.findByLocalUuid(local_uuid) as LocalOrder;
  }

  /**
   * Agrega un item al pedido.
   */
  static async addItem(orderLocalUuid: string, item: {
    product_id: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    notes?: string;
    is_menu_item?: boolean;
    menu_item_id?: string;
  }): Promise<LocalOrderItem> {
    const itemUuid = uuidv4();
    const subtotal = item.quantity * item.unit_price;

    await localDb.execute(
      `INSERT INTO local_order_items (
        local_uuid, order_local_uuid, product_id, product_name,
        quantity, unit_price, subtotal, notes, kitchen_status,
        is_menu_item, menu_item_id, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, CURRENT_TIMESTAMP)`,
      [
        itemUuid,
        orderLocalUuid,
        item.product_id,
        item.product_name,
        item.quantity,
        item.unit_price,
        subtotal,
        item.notes || null,
        item.is_menu_item ? 1 : 0,
        item.menu_item_id || null,
      ]
    );

    // Actualizar totales del pedido
    await this.recalculateOrderTotals(orderLocalUuid);

    return await this.findItemByLocalUuid(itemUuid) as LocalOrderItem;
  }

  /**
   * Recalcula subtotal, tax y total del pedido.
   */
  static async recalculateOrderTotals(orderLocalUuid: string): Promise<void> {
    const items = await localDb.select<any>(
      "SELECT subtotal FROM local_order_items WHERE order_local_uuid = ?",
      [orderLocalUuid]
    );

    const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0);
    const taxRate = 0.19; // 19% IVA Chile
    const taxTotal = subtotal * taxRate;
    const grandTotal = subtotal + taxTotal;

    await localDb.execute(
      `UPDATE local_orders SET subtotal = ?, tax_total = ?, grand_total = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?`,
      [subtotal, taxTotal, grandTotal, orderLocalUuid]
    );
  }

  /**
   * Actualiza el estado del pedido.
   */
  static async updateStatus(orderLocalUuid: string, status: LocalOrder["status"]): Promise<void> {
    await localDb.execute(
      `UPDATE local_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?`,
      [status, orderLocalUuid]
    );
  }

  /**
   * Busca un pedido por local_uuid.
   */
  static async findByLocalUuid(localUuid: string): Promise<LocalOrder | null> {
    const results = await localDb.select<LocalOrder>(
      "SELECT * FROM local_orders WHERE local_uuid = ?",
      [localUuid]
    );
    return results[0] || null;
  }

  /**
   * Busca todos los items de un pedido.
   */
  static async findItemsByOrderLocalUuid(orderLocalUuid: string): Promise<LocalOrderItem[]> {
    return await localDb.select<LocalOrderItem>(
      "SELECT * FROM local_order_items WHERE order_local_uuid = ? ORDER BY created_at ASC",
      [orderLocalUuid]
    );
  }

  /**
   * Busca un item por local_uuid.
   */
  private static async findItemByLocalUuid(itemUuid: string): Promise<LocalOrderItem | null> {
    const results = await localDb.select<LocalOrderItem>(
      "SELECT * FROM local_order_items WHERE local_uuid = ?",
      [itemUuid]
    );
    return results[0] || null;
  }

  /**
   * Lista todos los pedidos locales de una sucursal.
   */
  static async findAllByBranch(branchId: string): Promise<LocalOrder[]> {
    return await localDb.select<LocalOrder>(
      "SELECT * FROM local_orders WHERE branch_id = ? ORDER BY created_at DESC",
      [branchId]
    );
  }

  /**
   * Elimina un pedido y todos sus items (cascade).
   */
  static async delete(orderLocalUuid: string): Promise<void> {
    await localDb.execute("DELETE FROM local_orders WHERE local_uuid = ?", [orderLocalUuid]);
  }

  /**
   * Marca el pedido como sincronizado con el cloud_id.
   */
  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localDb.execute(
      `UPDATE local_orders SET cloud_id = ?, sync_status = 'synced', updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?`,
      [cloudId, localUuid]
    );
  }

  /**
   * Obtiene todos los items de un pedido.
   */
  static async findItemsByOrderUuid(orderLocalUuid: string): Promise<LocalOrderItem[]> {
    return await localDb.select<LocalOrderItem>(
      "SELECT * FROM local_order_items WHERE order_local_uuid = ? ORDER BY created_at ASC",
      [orderLocalUuid]
    );
  }


  /**
   * Marca un pedido como fallido con su error de sincronización.
   */
  static async markSyncError(localUuid: string, error: string): Promise<void> {
    await localDb.execute(
      "UPDATE local_orders SET sync_status = 'failed', sync_error = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?",
      [error, localUuid]
    );
  }

}
