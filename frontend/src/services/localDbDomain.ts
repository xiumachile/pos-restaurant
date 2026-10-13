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
  local_uuid: string;
  idempotency_key: string;
  order_local_uuid: string;
  payment_method_uuid: string; // P0 Fix: Alineado con Rust
  amount: number;
  tip_amount: number;
  total_amount: number;
  reference_code?: string | null;
  notes?: string | null;
}

export interface EnqueueSyncEventPayload {
  id: string;
  entity_type: string;
  entity_local_uuid: string;
  action: string;
  payload_json: string;
}

export const localDomainApi = {
  async createOrder(payload: CreateOrderPayload): Promise<{ local_uuid: string }> {
    return await invoke<{ local_uuid: string }>('create_local_order', { payload });
  },

  async registerPayment(payload: RegisterPaymentPayload): Promise<{ local_uuid: string }> {
    return await invoke<{ local_uuid: string }>('register_local_payment', { payload });
  },

  async enqueueSyncEvent(payload: EnqueueSyncEventPayload): Promise<void> {
    await invoke<void>('enqueue_sync_event', { payload });
  },

  async getPendingOrders(companyId: string, branchId: string): Promise<any[]> {
    return await invoke<any[]>('get_pending_orders', { companyId, branchId });
  },

  // P1 FIX: Verificación real de conectividad en lugar de descubrimiento de red vacío
  async checkPrinterStatus(ip: string, port: number): Promise<string> {
    return await invoke<string>('check_printer_status', { ip, port });
  },

  async printRaw(ip: string, port: number, dataBase64: string): Promise<number> {
    return await invoke<number>('print_raw', { ip, port, data: dataBase64 });
  }
};
