import { localDomainApi } from '../../services/localDbDomain';
import { isTestEnvironment } from "../nativeDb";
import { localDb } from "../localDb";
import { localWriteCoordinator } from "../LocalWriteCoordinator";
import { validateLocalMoneyPayload } from '../guards/localMoneyGuard';
import { SyncQueueRepository } from "./SyncQueueRepository";
import { localTablesService } from "@/services/localTablesService";
import { v4 as uuidv4 } from "uuid";
import { calculateTax, roundToCents, sumMoney } from "@/utils/money";
import { IVA_RATE } from "@/config/tax";

export interface LocalOrder {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  table_id: string | null;
  order_number: string;
  order_type: "dine_in" | "take_out" | "delivery";
  status: 
    | "draft"              // Borrador (no confirmado aún)
    | "confirmed"          // Confirmado por garzón
    | "preparing"          // En preparación (cocina)
    | "ready"              // Listo para servir (onsite)
    | "ready_for_pickup"   // Listo para retirar (pickup)
    | "picked_up"          // Cliente retiró (pickup)
    | "dispatched"         // En camino (delivery)
    | "delivered"          // Entregado (delivery)
    | "served"             // Servido en mesa (legacy dine_in)
    | "paid"               // Pagado
    | "closed"             // Cerrado (final)
    | "cancelled";         // Cancelado (terminal)
  // ADR-011: Semántica chilena (precios IVA incluido)
  subtotal: number;        // subtotal_gross (IVA incluido)
  discount_total: number;  // discount_amount
  net_amount: number;      // subtotal / 1.19 (desglose tributario)
  tax_total: number;       // subtotal - net_amount (IVA desglosado)
  tip_amount: number;      // propina (separada, no tributaria)
  grand_total: number;     // subtotal - discount (total venta IVA incluido)
  amount_due: number;      // grand_total + tip_amount (total a cobrar)
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
  // kitchen_status: alineado con OrderStatus del backend
  // NOTA: pending = draft (item aún no confirmado por cocina)
  kitchen_status: 
    | "pending"            // Equivale a draft del order padre
    | "confirmed"          // Confirmado en cocina
    | "preparing"          // En preparación
    | "ready"              // Listo
    | "ready_for_pickup"   // Listo para pickup
    | "served"             // Servido en mesa
    | "delivered"          // Entregado (pickup/delivery)
    | "cancelled";         // Cancelado
  is_menu_item: boolean;
  menu_item_id: string | null;
  created_at: string;
}

export interface CreateOrderPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  table_id?: string | null;
  order_type?: "dine_in" | "take_out" | "delivery";
  waiter_id?: string;
  waiter_name?: string;
  guest_count?: number;
  notes?: string;
  // Campos de cliente para delivery/takeout
  customer_name?: string | null;
  customer_phone?: string | null;
  customer_address?: string | null;
  customer_reference?: string | null;
}

export class OrderRepository {
  /**
   * Crea un nuevo pedido local con UUID único y idempotency key.
   */
  static async create(payload: CreateOrderPayload): Promise<LocalOrder> {
    // P2-003: Local Money Guard
    validateLocalMoneyPayload(payload as unknown as Record<string, unknown>, 'order');
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();
    const order_number = `TEMP-${Date.now()}`;

    // TRANSACCIÓN ATÓMICA: todas las operaciones deben completarse juntas
    await localWriteCoordinator.run(async (db) => {
      // 1. VALIDACIÓN FAIL-FAST ATÓMICA: Verificar que la mesa exista y esté DISPONIBLE
      if (payload.table_id) {
        // Usar el contexto de transacción (db) para leer el estado actual.
        // Esto garantiza que vemos los cambios de transacciones anteriores ya confirmadas.
        const tableCheck = await (db as any).select(
          "SELECT uuid, status FROM local_tables WHERE uuid = ? AND company_id = ? AND branch_id = ?",
          [payload.table_id, payload.company_id, payload.branch_id]
        );
        
        if (!tableCheck || tableCheck.length === 0) {
          throw new Error(`Mesa ${payload.table_id} no existe o no pertenece al tenant`);
        }
        
        if (tableCheck[0].status !== 'available') {
          throw new Error(`Mesa ${payload.table_id} ya está ocupada (estado: ${tableCheck[0].status})`);
        }
      }

            // 2. Crear order con modelo chileno (ADR-011) y campos de cliente
      await db.execute(
        `INSERT INTO local_orders (
          local_uuid, company_id, branch_id, terminal_id, table_id,
          order_number, order_type, status, subtotal, discount_total,
          net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
          waiter_id, waiter_name, notes, idempotency_key, sync_status,
          customer_name, customer_phone, delivery_address, delivery_notes,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
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
          payload.customer_name || null,
          payload.customer_phone || null,
          payload.customer_address || (payload as any).delivery_address || null,
          payload.customer_reference || (payload as any).delivery_notes || null,
        ]
      );

      // 3. Encolar evento de sincronización DENTRO de la misma transacción
      const syncPayload = {
        local_uuid, company_id: payload.company_id, branch_id: payload.branch_id,
        terminal_id: payload.terminal_id || null, table_id: payload.table_id || null,
        order_number, order_type: payload.order_type || "dine_in", status: 'confirmed',
        subtotal: 0, discount_total: 0, tax_total: 0, tip_amount: 0, grand_total: 0,
        guest_count: payload.guest_count || 1, waiter_id: payload.waiter_id || null,
        waiter_name: payload.waiter_name || null, notes: payload.notes || null,
        idempotency_key, items: []
      };
      
      await db.execute(
        `INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)`,
        [local_uuid, payload.company_id, payload.branch_id, 'order', local_uuid, 'create', JSON.stringify(syncPayload)]
      );

      // 4. Actualizar estado de la mesa (si aplica)
      if (payload.table_id) {
        await db.execute(
          "UPDATE local_tables SET status = 'occupied', current_order_uuid = ? WHERE uuid = ?",
          [local_uuid, payload.table_id]
        );
        await db.execute(
          "INSERT OR REPLACE INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, created_at) VALUES (?, 'occupied', ?, ?, ?, CURRENT_TIMESTAMP)",
          [payload.table_id, local_uuid, payload.company_id, payload.branch_id]
        );
      }
    });
 // Fin de la transacción de creación de orden

    console.log("[OrderRepository] 📤 Pedido creado localmente:", local_uuid);
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

    // TRANSACCIÓN ATÓMICA: item + recálculo de totales
    // Si recalculateOrderTotals falla, el item NO queda insertado
    await localWriteCoordinator.run(async (db) => {
      // 1. Insertar item
      await db.execute(
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

      // 2. Recalcular totales del pedido
      await this.recalculateOrderTotals(orderLocalUuid, db);
    });

    return await this.findItemByLocalUuid(itemUuid) as LocalOrderItem;
  }

  /**
   * Elimina un item del pedido y recalcula totales.
   */
  static async removeItem(orderLocalUuid: string, itemLocalUuid: string): Promise<void> {
    await localWriteCoordinator.run(async (db) => {
      // 1. Eliminar el item
      await (db as any).execute(
        "DELETE FROM local_order_items WHERE local_uuid = ? AND order_local_uuid = ?",
        [itemLocalUuid, orderLocalUuid]
      );

      // 2. Recalcular totales del pedido
      await this.recalculateOrderTotals(orderLocalUuid, db);
    });
    
    // 3. Sincronizar cambios con el backend/KDS
    await this.syncOrderChanges(orderLocalUuid);
  }

  /**
   * Actualiza la cantidad de un item existente. Si llega a 0, lo elimina.
   */
  static async updateItemQuantity(
    orderLocalUuid: string, 
    itemLocalUuid: string, 
    newQuantity: number
  ): Promise<void> {
    if (newQuantity <= 0) {
      await this.removeItem(orderLocalUuid, itemLocalUuid);
      return;
    }

    await localWriteCoordinator.run(async (db) => {
      // 1. Actualizar cantidad y subtotal
      const item = await this.findItemByLocalUuid(itemLocalUuid);
      if (!item) throw new Error("Item no encontrado");

      const newSubtotal = newQuantity * item.unit_price;
      await (db as any).execute(
        "UPDATE local_order_items SET quantity = ?, subtotal = ? WHERE local_uuid = ?",
        [newQuantity, newSubtotal, itemLocalUuid]
      );

      // 2. Recalcular totales del pedido
      await this.recalculateOrderTotals(orderLocalUuid, db);
    });
    
    // 3. Sincronizar cambios con el backend/KDS
    await this.syncOrderChanges(orderLocalUuid);
  }

  /**
   * Busca un pedido específico por su local_uuid (incluyendo sus items).
   */
  static async findOrderWithItems(localUuid: string): Promise<(LocalOrder & { items: LocalOrderItem[] }) | null> {
    const order = await this.findByLocalUuid(localUuid);
    if (!order) return null;

    const items = await this.findItemsByOrderLocalUuid(localUuid);
    return { ...order, items };
  }

    /**
   * Marca el pedido como pendiente de sincronización después de una modificación local.
   * El SyncWorker se encargará de generar el evento correcto con todos los datos validados.
   */
  static async syncOrderChanges(orderLocalUuid: string): Promise<void> {
    await localWriteCoordinator.run(async (db) => {
      // 1. Marcar el pedido como pendiente de sincronización
      await (db as any).execute(
        "UPDATE local_orders SET sync_status = 'pending', sync_error = NULL WHERE local_uuid = ?",
        [orderLocalUuid]
      );
      
      // 2. Si ya existe un evento 'create' pendiente en la cola, actualizar su payload
      // para que el SyncWorker envíe la versión más reciente del pedido.
      await (db as any).execute(
        `UPDATE sync_queue 
         SET payload = (
           SELECT json_object(
             'company_id', company_id,
             'branch_id', branch_id,
             'terminal_id', terminal_id,
             'table_id', table_id,
             'order_type', order_type,
             'guest_count', guest_count,
             'waiter_id', waiter_id,
             'waiter_name', waiter_name,
             'notes', notes,
             'customer_name', customer_name,
             'customer_phone', customer_phone,
             'delivery_address', delivery_address,
             'delivery_notes', delivery_notes,
             'items', (
               SELECT json_group_array(
                 json_object(
                   'product_id', product_id,
                   'product_name', product_name,
                   'quantity', quantity,
                   'unit_price', unit_price,
                   'notes', notes,
                   'is_menu_item', is_menu_item,
                   'menu_item_id', menu_item_id
                 )
               )
               FROM local_order_items 
               WHERE order_local_uuid = local_orders.local_uuid
             )
           )
           FROM local_orders 
           WHERE local_uuid = ?
         ),
         sync_status = 'pending',
         attempts = 0,
         last_error = NULL,
         created_at = CURRENT_TIMESTAMP
         WHERE entity_type = 'order' 
           AND entity_local_uuid = ? 
           AND action = 'create' 
           AND sync_status = 'pending'`,
        [orderLocalUuid, orderLocalUuid]
      );
    });
  }

  /**
   * Recalcula subtotal, tax y total del pedido.
   * 
   * ADR-010: Usa helpers de Money para garantizar que todos los montos
   * sean enteros (CLP no tiene centavos fraccionarios).
   * 
   * ADR-011: Semántica chilena (precios IVA incluido)
   * - subtotal = suma de item.subtotal (IVA incluido)
   * - net_amount = roundToCents(subtotal / 1.19)
   * - tax_amount = subtotal - net_amount
   * - grand_total = subtotal - discount
   * - amount_due = grand_total + tip_amount
   */
  /**
   * P1 FIX: Recalcula totales de forma atómica en una sola sentencia SQL (CTE).
   * Usa solo 2 parámetros posicionales simples para evitar conflictos de binding.
   */
  static async recalculateOrderTotals(orderLocalUuid: string, txDb?: any): Promise<void> {
    const dbToUse = txDb || localDb;
    
    const atomicUpdateSql = `
      WITH order_data AS (
        SELECT 
          o.discount_total,
          o.tip_amount,
          COALESCE(SUM(i.subtotal), 0) as new_subtotal
        FROM local_orders o
        LEFT JOIN local_order_items i ON i.order_local_uuid = o.local_uuid
        WHERE o.local_uuid = ?
        GROUP BY o.local_uuid
      )
      UPDATE local_orders
      SET 
        subtotal = (SELECT new_subtotal FROM order_data),
        net_amount = CAST(ROUND((SELECT new_subtotal FROM order_data) / 1.19) AS INTEGER),
        tax_total = (SELECT new_subtotal FROM order_data) - CAST(ROUND((SELECT new_subtotal FROM order_data) / 1.19) AS INTEGER),
        grand_total = (SELECT new_subtotal FROM order_data) - (SELECT discount_total FROM order_data),
        amount_due = ((SELECT new_subtotal FROM order_data) - (SELECT discount_total FROM order_data)) + (SELECT tip_amount FROM order_data),
        updated_at = CURRENT_TIMESTAMP
      WHERE local_uuid = ?
    `;

    await (dbToUse as any).execute(atomicUpdateSql, [orderLocalUuid, orderLocalUuid]);
  }

  /**
   * Actualiza el monto de propina del pedido.
   * 
   * ADR-011: La propina es separada del consumo y no afecta IVA.
   * Al actualizar tip_amount, recalcula amount_due automáticamente.
   */
  static async updateTipAmount(orderLocalUuid: string, tipAmount: number): Promise<void> {
    await localWriteCoordinator.executeSingle(
      `UPDATE local_orders SET tip_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?`,
      [tipAmount, orderLocalUuid]
    );
    
    // Recalcular amount_due con la nueva propina
    await this.recalculateOrderTotals(orderLocalUuid);
  }

  /**
   * P1-02 FIX: Actualiza el estado del pedido con transición condicional atómica.
   * Incluye el estado esperado en el WHERE para prevenir condiciones de carrera (TOCTOU).
   */
     static async updateStatus(
    orderLocalUuid: string,
    status: LocalOrder["status"],
    txDb?: any,
    expectedCurrentStatus?: string
  ): Promise<void> {
    const isTest = process.env.NODE_ENV === 'test' || typeof window === 'undefined';
    
    // AUD-01/02 FIX: En producción, delegar a Rust para atomicidad real.
    if (!isTest && !txDb) {
      let currentStatus: string = expectedCurrentStatus || '';
      if (!currentStatus) {
        const currentOrder = await localDb.select("SELECT status FROM local_orders WHERE local_uuid = ?", [orderLocalUuid]);
        if (!currentOrder || currentOrder.length === 0) throw new Error(`Order ${orderLocalUuid} no encontrado`);
        currentStatus = currentOrder[0].status;
      }

      try {
        await localDomainApi.transitionOrderStatus({
          order_uuid: orderLocalUuid,
          new_status: status,
          expected_status: currentStatus,
        });
        return;
      } catch (error: any) {
        console.error("[OrderRepository] ❌ Error en transición de estado en Rust:", error);
        throw new Error(error.message || "Conflicto de estado del pedido");
      }
    }

    // Fallback para tests o transacciones TypeScript (txDb)
    const dbToUse = txDb || localDb;
    let currentStatus: string = expectedCurrentStatus || '';

    if (!currentStatus) {
      const currentOrder = await (dbToUse as any).select("SELECT status FROM local_orders WHERE local_uuid = ?", [orderLocalUuid]);
      if (!currentOrder || currentOrder.length === 0) throw new Error(`Order ${orderLocalUuid} no encontrado`);
      currentStatus = currentOrder[0].status;
    }

    if (currentStatus === 'paid' && status !== 'paid') {
      throw new Error(`No se puede modificar un pedido ya pagado. Estado actual: ${currentStatus}`);
    }
    if (currentStatus === 'cancelled' && status !== 'cancelled') {
      throw new Error(`No se puede modificar un pedido ya cancelado. Estado actual: ${currentStatus}`);
    }

    // Ejecutar UPDATE condicional
    const result = await (dbToUse as any).execute(
      `UPDATE local_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ? AND status = ?`,
      [status, orderLocalUuid, currentStatus]
    );

    // AUD-02 FIX: Verificar explícitamente que el UPDATE afectó una fila, SOLO si el entorno lo soporta.
    // En modo txDb (buffering), result es undefined. En ese caso, confiamos en la validación inicial
    // de estado y dejamos la verificación estricta de filas para la ruta de producción en Rust.
    if (result && typeof result === 'object' && 'rowsAffected' in result) {
      if ((result as any).rowsAffected === 0) {
        throw new Error(`Conflicto de concurrencia: No se pudo actualizar el estado de la orden ${orderLocalUuid} (esperado: ${currentStatus}).`);
      }
    }

    // Si la verificación pasa, proceder con efectos secundarios
    if (status === 'cancelled') {
      await (dbToUse as any).execute(
        `UPDATE local_tables SET status = 'available', current_order_uuid = NULL WHERE uuid = (SELECT table_id FROM local_orders WHERE local_uuid = ? AND table_id IS NOT NULL)`,
        [orderLocalUuid]
      );
    }
  }
  static async findByLocalUuid(localUuid: string): Promise<LocalOrder | null> {
    const results = await localDb.select<LocalOrder>(
      "SELECT * FROM local_orders WHERE local_uuid = ?",
      [localUuid]
    );
    return results[0] || null;
  }

  static async markAsSynced(localUuid: string, cloudId: string): Promise<void> {
    await localWriteCoordinator.executeSingle(
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
    await localWriteCoordinator.executeSingle(
      "UPDATE local_orders SET sync_status = 'failed', sync_error = ?, updated_at = CURRENT_TIMESTAMP WHERE local_uuid = ?",
      [error, localUuid]
    );
  }

// BUILD_HASH: 1788495805

  /**
   * Crea un pedido y todos sus items en UNA SOLA transacción atómica.
   * Esto previene bloqueos de base de datos (database is locked) con el SyncEngine
   * al evitar múltiples aperturas/cierres de transacciones consecutivas.
   */
  static async createWithItems(
    payload: CreateOrderPayload,
    items: Array<{
      product_id: string;
      product_name: string;
      quantity: number;
      unit_price: number;
      notes?: string;
      is_menu_item?: boolean;
      menu_item_id?: string;
    }>
  ): Promise<LocalOrder> {
    validateLocalMoneyPayload(payload as unknown as Record<string, unknown>, 'order');

    // O-05 FIX: Creación atómica vía comando de dominio seguro (sin SQL arbitrario en frontend)
    if (!isTestEnvironment()) {
      const res = await localDomainApi.createOrder({
        company_id: String(payload.company_id),
        branch_id: String(payload.branch_id),
        terminal_id: payload.terminal_id ?? "unknown",
        table_id: payload.table_id ?? null,
        order_type: payload.order_type ?? "dine_in",
        guest_count: payload.guest_count ?? 1,
        waiter_id: payload.waiter_id ?? null,
        waiter_name: payload.waiter_name ?? null,
        notes: payload.notes ?? null,
        customer_name: (payload as any).customer_name ?? null,
        customer_phone: (payload as any).customer_phone ?? null,
        delivery_address: (payload as any).customer_address ?? (payload as any).delivery_address ?? null,
        delivery_notes: (payload as any).customer_reference ?? (payload as any).delivery_notes ?? null,
        items: items.map((i) => ({
          product_id: String(i.product_id),
          product_name: i.product_name,
          quantity: i.quantity,
          unit_price: i.unit_price,
          notes: i.notes ?? null,
          is_menu_item: i.is_menu_item ?? false,
          menu_item_id: i.menu_item_id ?? null,
        })),
      });
      console.log("[OrderRepository] 📤 Pedido creado atómicamente (Rust):", res.local_uuid);
      return (await this.findByLocalUuid(res.local_uuid)) as LocalOrder;
    }
    const local_uuid = uuidv4();
    const idempotency_key = uuidv4();
    const order_number = `TEMP-${Date.now()}`;

    await localWriteCoordinator.run(async (db) => {
      // 1. Crear order (valores iniciales en 0)
      await (db as any).execute(
        `INSERT INTO local_orders (
          local_uuid, company_id, branch_id, terminal_id, table_id,
          order_number, order_type, status, subtotal, discount_total,
          net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
          waiter_id, waiter_name, notes, idempotency_key, sync_status,
          created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
        [
          local_uuid, payload.company_id, payload.branch_id, payload.terminal_id || null,
          payload.table_id || null, order_number, payload.order_type || "dine_in", "confirmed",
          payload.customer_name || null, payload.customer_phone || null, 
          payload.customer_address || null, payload.customer_reference || null,
          payload.customer_name || null, payload.customer_phone || null, 
          payload.customer_address || null, payload.customer_reference || null,
          payload.guest_count || 1, payload.waiter_id || null, payload.waiter_name || null,
          payload.notes || null, idempotency_key,
        ]
      );

      // 2. Insertar todos los items
      let totalSubtotal = 0;
      for (const item of items) {
        const itemUuid = uuidv4();
        const subtotal = item.quantity * item.unit_price;
        totalSubtotal += subtotal;

        await (db as any).execute(
          `INSERT INTO local_order_items (
            local_uuid, order_local_uuid, product_id, product_name,
            quantity, unit_price, subtotal, notes, kitchen_status,
            is_menu_item, menu_item_id, created_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, CURRENT_TIMESTAMP)`,
          [
            itemUuid, local_uuid, item.product_id, item.product_name,
            item.quantity, item.unit_price, subtotal, item.notes || null,
            item.is_menu_item ? 1 : 0, item.menu_item_id || null,
          ]
        );
      }

      // 3. Recalcular totales del pedido (usando la misma conexión 'db')
      await this.recalculateOrderTotals(local_uuid, db);

      // 4. Encolar evento de sincronización usando la misma conexión de transacción
            // 4. Encolar evento de sincronización usando la misma conexión de transacción
      const syncPayload = {
        local_uuid, company_id: payload.company_id, branch_id: payload.branch_id,
        terminal_id: payload.terminal_id || null, table_id: payload.table_id || null,
        order_number, order_type: payload.order_type || "dine_in", status: 'confirmed',
        guest_count: payload.guest_count || 1, waiter_id: payload.waiter_id || null,
        waiter_name: payload.waiter_name || null, notes: payload.notes || null,
        idempotency_key,
        // Datos del cliente para sincronización con backend
        customer_name: (payload as any).customer_name || null,
        customer_phone: (payload as any).customer_phone || null,
        customer_address: (payload as any).customer_address || null,
        customer_reference: (payload as any).customer_reference || null,
      };

      // Usamos directamente execute de db para encolar en sync_queue
      await (db as any).execute(
        `INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)`,
        [
          local_uuid, // id
          payload.company_id, payload.branch_id, 'order', local_uuid, 'create',
          JSON.stringify(syncPayload)
        ]
      );

      // 5. Marcar mesa como occupied (DIRECTAMENTE en la transacción para garantizar atomicidad)
      if (payload.table_id) {
        // a) Validar que la mesa existe
        const tableExists = await (db as any).select(
          "SELECT uuid FROM local_tables WHERE uuid = ? AND company_id = ? AND branch_id = ?",
          [payload.table_id, payload.company_id, payload.branch_id]
        );
        if (!tableExists || tableExists.length === 0) {
          throw new Error(`Mesa ${payload.table_id} no existe o no pertenece al tenant`);
        }

        // b) Actualizar estado de la mesa (se acumula en la transacción)
        await (db as any).execute(
          `UPDATE local_tables SET status = 'occupied', current_order_uuid = ? WHERE uuid = ?`,
          [local_uuid, payload.table_id]
        );

        // c) Registrar mutación para el SyncEngine (se acumula en la transacción)
        await (db as any).execute(
          `INSERT OR REPLACE INTO table_local_mutations 
             (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at)
           VALUES (?, 'occupied', ?, ?, ?, 'update', ?, CURRENT_TIMESTAMP)`,
          [
            payload.table_id,
            local_uuid,
            payload.company_id,
            payload.branch_id,
            JSON.stringify({
              status: 'occupied',
              current_order_uuid: local_uuid,
            }),
          ]
        );
      }
    });

    console.log("[OrderRepository] 📤 Pedido + Items creados atómicamente:", local_uuid);
    return await this.findByLocalUuid(local_uuid) as LocalOrder;
  }
  static async findItemByLocalUuid(itemUuid: string): Promise<LocalOrderItem | null> {
    const results = await localDb.select<LocalOrderItem>(
      "SELECT * FROM local_order_items WHERE local_uuid = ?",
      [itemUuid]
    );
    return results[0] || null;
  }
  static async findPendingByTable(tableUuid: string): Promise<LocalOrder[]> {
    return await localDb.select<LocalOrder>(
      "SELECT * FROM local_orders WHERE table_id = ? AND status NOT IN ('paid', 'closed', 'cancelled')",
      [tableUuid]
    );
  }
  /**
   * Obtiene un mapa de estados locales de todas las órdenes de una mesa.
   * Clave: cloud_id (o local_uuid si no hay cloud_id)
   * Valor: status local
   * Esto es crucial para el principio Offline-First: ignorar datos de la nube 
   * si localmente la orden ya está en estado terminal (paid, closed, cancelled).
   */
  static async getLocalOrderStatusesByTable(tableUuid: string): Promise<Map<string, string>> {
    const orders = await localDb.select<{ cloud_id: string | null, local_uuid: string, status: string }>(
      "SELECT cloud_id, local_uuid, status FROM local_orders WHERE table_id = ?",
      [tableUuid]
    );
    
    const statusMap = new Map<string, string>();
    orders.forEach(o => {
      // Usamos cloud_id como clave principal para emparejar con la nube, fallback a local_uuid
      const key = o.cloud_id || o.local_uuid;
      statusMap.set(key, o.status);
    });
    
    return statusMap;
  }
  static async findItemsByOrderLocalUuid(orderLocalUuid: string): Promise<LocalOrderItem[]> {
    return await localDb.select<LocalOrderItem>(
      "SELECT * FROM local_order_items WHERE order_local_uuid = ?",
      [orderLocalUuid]
    );
  }
  static async findAllByBranch(branchId: string): Promise<LocalOrder[]> {
    return await localDb.select<LocalOrder>(
      "SELECT * FROM local_orders WHERE branch_id = ?",
      [branchId]
    );
  }
  static async delete(orderLocalUuid: string): Promise<void> {
    await localDb.execute("DELETE FROM local_orders WHERE local_uuid = ?", [orderLocalUuid]);
  }
    /**
   * [MANTENIMIENTO] Repara estados inconsistentes.
   * Alineado con el esquema real:
   *  - local_orders.updated_at  (sí existe)
   *  - local_tables.last_updated (NO updated_at)
   */
       static async repairInconsistentStates(): Promise<{ repairedOrders: number; freedTables: number }> {
    // 1. Marcar como 'paid' las órdenes con pagos completados localmente
    await localWriteCoordinator.executeSingle(`
      UPDATE local_orders
      SET status = 'paid', updated_at = CURRENT_TIMESTAMP
      WHERE status NOT IN ('paid', 'closed', 'cancelled')
        AND local_uuid IN (
          SELECT order_local_uuid FROM local_payments WHERE status = 'completed'
        )
    `);

    // 2. Marcar como 'cancelled' las órdenes con pagos fallidos
    await localWriteCoordinator.executeSingle(`
      UPDATE local_orders
      SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
      WHERE status NOT IN ('paid', 'closed', 'cancelled')
        AND local_uuid IN (
          SELECT order_local_uuid FROM local_payments WHERE status = 'failed'
        )
    `);

    // 3. Marcar como 'cancelled' las órdenes con eventos de pago fallidos en sync_queue
    await localWriteCoordinator.executeSingle(`
      UPDATE local_orders
      SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
      WHERE status NOT IN ('paid', 'closed', 'cancelled')
        AND local_uuid IN (
          SELECT entity_local_uuid FROM sync_queue 
          WHERE sync_status = 'failed' AND entity_type = 'payment'
        )
    `);

    // 4. Liberar mesas cuya orden ya está pagada/cancelada/cerrada o no existe
    await localWriteCoordinator.executeSingle(`
      UPDATE local_tables
      SET status = 'available',
          current_order_uuid = NULL,
          last_updated = CURRENT_TIMESTAMP
      WHERE (
        current_order_uuid IN (
          SELECT local_uuid FROM local_orders
          WHERE status IN ('paid', 'closed', 'cancelled')
        )
      ) OR (
        current_order_uuid IS NOT NULL
        AND current_order_uuid NOT IN (SELECT local_uuid FROM local_orders)
      )
    `);

    // 5. Limpiar eventos fallidos de la cola de sincronización
    await localWriteCoordinator.executeSingle(`
      DELETE FROM sync_queue 
      WHERE sync_status = 'failed' AND entity_type IN ('payment', 'bill')
    `);

           // 6. Limpiar carritos huérfanos del localStorage
    try {
      const cartStoreKey = 'pos-cart-storage';
      const cartData = localStorage.getItem(cartStoreKey);
      console.log(`[Repair] 🔍 Iniciando limpieza de carritos. localStorage key: ${cartStoreKey}`);
      
      if (cartData) {
        const parsed = JSON.parse(cartData);
        console.log(`[Repair] 📦 Carritos encontrados en localStorage:`, Object.keys(parsed.state?.carts || {}));
        
        if (parsed.state?.carts) {
          const cleanedCarts: Record<string, any> = {};
          let removedCount = 0;

          for (const [cartKey, cart] of Object.entries(parsed.state.carts)) {
            const cartAny = cart as any;
            const items = cartAny.items || [];

            console.log(`[Repair] 🔎 Analizando carrito: ${cartKey}, items: ${items.length}`);

            // Carritos vacíos → eliminar
            if (items.length === 0) {
              console.log(`[Repair] 🗑️ Eliminando carrito vacío: ${cartKey}`);
              removedCount++;
              continue;
            }

            // Carritos de delivery/takeout: key empieza con "takeaway-"
            if (cartKey.startsWith('takeaway-')) {
              const orderLocalUuid = cartKey.replace('takeaway-', '');
              console.log(`[Repair] 🔎 Buscando orden activa para takeaway: ${orderLocalUuid}`);
              
              const activeOrder = await localDb.select<{ local_uuid: string }>(
                `SELECT local_uuid FROM local_orders 
                 WHERE local_uuid = ? AND status NOT IN ('paid', 'closed', 'cancelled')`,
                [orderLocalUuid]
              );
              
              console.log(`[Repair] 📊 Órdenes activas encontradas:`, activeOrder.length);
              
              if (activeOrder.length > 0) {
                console.log(`[Repair] ✅ Conservando carrito takeaway: ${cartKey}`);
                cleanedCarts[cartKey] = cart;
              } else {
                console.log(`[Repair] 🗑️ Eliminando carrito takeaway huérfano: ${cartKey}`);
                removedCount++;
              }
              continue;
            }

            // Carritos de mesa: key es el tableUuid
            console.log(`[Repair] 🔎 Buscando orden activa para mesa: ${cartKey}`);
            const activeOrder = await localDb.select<{ local_uuid: string }>(
              `SELECT local_uuid FROM local_orders 
               WHERE table_id = ? AND status NOT IN ('paid', 'closed', 'cancelled')`,
              [cartKey]
            );

            console.log(`[Repair] 📊 Órdenes activas encontradas para mesa ${cartKey}:`, activeOrder.length);

            if (activeOrder.length > 0) {
              console.log(`[Repair] ✅ Conservando carrito de mesa: ${cartKey}`);
              cleanedCarts[cartKey] = cart;
            } else {
              console.log(`[Repair] 🗑️ Eliminando carrito de mesa huérfano: ${cartKey}`);
              removedCount++;
            }
          }

          parsed.state.carts = cleanedCarts;
          localStorage.setItem(cartStoreKey, JSON.stringify(parsed));
          console.log(`[Repair] 🧹 Limpieza completada. Eliminados: ${removedCount}. Restantes: ${Object.keys(cleanedCarts).length}`);
        }
      } else {
        console.log(`[Repair] ⚠️ No hay datos en localStorage para la key: ${cartStoreKey}`);
      }
    } catch (error) {
      console.error("[Repair] ❌ Error limpiando carritos de localStorage:", error);
    }

    return { repairedOrders: 1, freedTables: 1 };
  }
}
