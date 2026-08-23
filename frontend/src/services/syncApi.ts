import { apiClient } from "./apiClient";

export interface OrderPayload {
  order_number?: string;
  order_type?: string;
  table_id?: string;
  waiter_id?: string;
  guest_count?: number;
  notes?: string;
  status?: string;
  items?: Array<{
    product_id: string;
    quantity: number;
    unit_price: number;
    notes?: string;
  }>;
  idempotency_key: string;
}

export interface PaymentPayload {
  order_uuid: string;
  payment_method_uuid: string;
  bill_uuid?: string | null;
  amount: number;
  tip_amount?: number;
  reference_code?: string | null;
  notes?: string | null;
  idempotency_key: string;
}

export class SyncApiClient {
  async createOrder(payload: OrderPayload): Promise<any> {
    const response = await apiClient.post("/orders", payload, {
      headers: { "Idempotency-Key": payload.idempotency_key },
    });
    return response.data.data;
  }

  /**
   * Agrega un item a una orden existente en el backend.
   * Backend espera: menu_item_uuid, quantity, notes
   */
  async addOrderItem(orderUuid: string, item: {
    product_id: string;
    quantity: number;
    unit_price?: number;
    notes?: string | null;
  }): Promise<any> {
    const payload = {
      product_uuid: item.product_id,  // Enviar product_uuid (backend lo resuelve)
      quantity: item.quantity,
      notes: item.notes || null,
    };
    
    const response = await apiClient.post(`/orders/${orderUuid}/items`, payload);
    return response.data.data;
  }

  async updateOrder(uuid: string, payload: Partial<OrderPayload>): Promise<any> {
    const response = await apiClient.put(`/orders/${uuid}`, payload);
    return response.data.data;
  }

  async deleteOrder(uuid: string): Promise<void> {
    await apiClient.delete(`/orders/${uuid}`);
  }

  async createPayment(payload: PaymentPayload): Promise<any> {
    const response = await apiClient.post("/billing/payments", payload, {
      headers: { "Idempotency-Key": payload.idempotency_key },
    });
    return response.data.data;
  }

  async updateTableStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.put(`/tables/${uuid}/status`, { status });
    return response.data.data;
  }

  /**
   * Descarga snapshot completo (catálogo, mesas, métodos de pago).
   * Usado en primera sync o cuando no hay last_pull_at.
   */
  async fetchCatalog(): Promise<{ categories: any[]; products: any[] }> {
    const [categoriesRes, productsRes] = await Promise.all([
      apiClient.get("/catalog/categories"),
      apiClient.get("/catalog/products"),
    ]);
    return {
      categories: categoriesRes.data.data,
      products: productsRes.data.data,
    };
  }

  async fetchTables(): Promise<any[]> {
    const response = await apiClient.get("/tables");
    return response.data.data;
  }

  async fetchPaymentMethods(): Promise<any[]> {
    const response = await apiClient.get("/payment-methods");
    return response.data.data;
  }

  /**
   * Descarga cambios incrementales desde last_pull_at.
   * Más eficiente que fetchCatalog() para syncs frecuentes.
   * 
   * @param branchId - ID de la sucursal
   * @param lastPullAt - ISO 8601 timestamp de última sincronización
   * @returns Objeto con cambios, total, timestamp y flag incremental
   */
  async fetchChanges(branchId: string, lastPullAt?: string): Promise<{
    changes: {
      categories: any[];
      products: any[];
      tables: any[];
      payment_methods: any[];
    };
    total: number;
    timestamp: string;
    incremental: boolean;
  }> {
    const params: any = { branch_id: branchId };
    if (lastPullAt) {
      params.last_pull_at = lastPullAt;
    }

    const response = await apiClient.get("/sync/changes", { params });
    return response.data.data;
  }
}

export const syncApi = new SyncApiClient();
