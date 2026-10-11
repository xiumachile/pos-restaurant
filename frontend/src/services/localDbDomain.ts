/**
 * O-05 FIX: Wrapper de dominio para operaciones locales de Tauri.
 * Reemplaza el uso de execute_transaction con SQL arbitrario por comandos específicos.
 * Esto reduce drásticamente la superficie de ataque local.
 */

import { invoke } from '@tauri-apps/api/core';

export interface CreateOrderPayload {
  uuid: string;
  company_id: number;
  branch_id: number;
  order_number: string;
  type: string;
  status: string;
  subtotal: number;
  tax_amount: number;
  total: number;
  idempotency_key: string;
}

export interface RegisterPaymentPayload {
  uuid: string;
  company_id: number;
  branch_id: number;
  order_id: number;
  amount: number;
  tip_amount: number;
  total_amount: number;
  method_code: string;
  idempotency_key: string;
}

export interface EnqueueSyncEventPayload {
  company_id: number;
  branch_id: number;
  entity_type: string;
  entity_id: number;
  entity_uuid: string;
  action: string;
  payload_json: string;
  version: number;
}

export const localDomainApi = {
  /**
   * Crea una orden local con validación de esquema estricta en Rust.
   */
  async createOrder(payload: CreateOrderPayload): Promise<string> {
    return await invoke<string>('create_local_order', { payload });
  },

  /**
   * Registra un pago local con validación estricta de campos monetarios.
   */
  async registerPayment(payload: RegisterPaymentPayload): Promise<string> {
    return await invoke<string>('register_local_payment', { payload });
  },

  /**
   * Encola un evento de sincronización con validación de esquema.
   */
  async enqueueSyncEvent(payload: EnqueueSyncEventPayload): Promise<string> {
    return await invoke<string>('enqueue_sync_event', { payload });
  },

  /**
   * Obtiene órdenes pendientes de forma segura (sin SQL arbitrario).
   */
  async getPendingOrders(companyId: number, branchId: number): Promise<any[]> {
    return await invoke<any[]>('get_pending_orders', { companyId, branchId });
  }
};
