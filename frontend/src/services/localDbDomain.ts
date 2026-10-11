/**
 * O-05 FIX: Wrapper de dominio para operaciones locales de Tauri.
 * Reemplaza el uso de execute_transaction con SQL arbitrario por comandos específicos.
 * Tipos alineados exactamente con el esquema real de SQLite (local_orders, local_order_items, sync_queue).
 */

import { invoke } from '@tauri-apps/api/core';

export interface CreateOrderPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string | null;
  table_id?: string | null;
  order_type: string;
  guest_count: number;
  waiter_id?: string | null;
  waiter_name?: string | null;
  notes?: string | null;
  items: Array<{
    product_id: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    notes?: string | null;
    is_menu_item: boolean;
    menu_item_id?: string | null;
  }>;
}

export interface RegisterPaymentPayload {
  order_local_uuid: string;
  payment_method: string;
  amount: number;
  tip_amount: number;
  total_amount: number;
  reference_code?: string | null;
  notes?: string | null;
}

export interface EnqueueSyncEventPayload {
  id: string; // UUID para la PK de sync_queue
  company_id: string;
  branch_id: string;
  entity_type: string;
  entity_local_uuid: string;
  action: string;
  payload_json: string;
}

export const localDomainApi = {
  /**
   * Crea una orden local con sus ítems y encola el evento de sync en UNA transacción atómica.
   */
  async createOrder(payload: CreateOrderPayload): Promise<{ local_uuid: string }> {
    return await invoke<{ local_uuid: string }>('create_local_order', { payload });
  },

  /**
   * Registra un pago local con validación estricta de campos monetarios.
   */
  async registerPayment(payload: RegisterPaymentPayload): Promise<{ local_uuid: string }> {
    return await invoke<{ local_uuid: string }>('register_local_payment', { payload });
  },

  /**
   * Encola un evento de sincronización con validación de esquema.
   */
  async enqueueSyncEvent(payload: EnqueueSyncEventPayload): Promise<void> {
    await invoke<void>('enqueue_sync_event', { payload });
  },

  /**
   * Obtiene órdenes pendientes de sincronización de forma segura.
   */
  async getPendingOrders(companyId: string, branchId: string): Promise<any[]> {
    return await invoke<any[]>('get_pending_orders', { companyId, branchId });
  }
};
