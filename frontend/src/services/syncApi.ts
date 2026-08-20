import { apiClient } from "./apiClient";

/**
 * Cliente API para endpoints de sincronización y entidades.
 * Centraliza las llamadas HTTP del SyncEngine.
 */

export interface CreateOrderPayload {
  order_number?: string;
  order_type?: string;
  table_id?: string;
  waiter_id?: string;
  guest_count?: number;
  notes?: string;
  items?: Array<{
    product_id: string;
    quantity: number;
    unit_price: number;
    notes?: string;
  }>;
  idempotency_key: string;
}

export interface CreatePaymentPayload {
  order_id?: string;
  payment_method: string;
  amount: number;
  tip_amount?: number;
  reference_code?: string;
  idempotency_key: string;
}

export interface OrderResponse {
  id?: string;
  uuid?: string;
  order_number?: string;
  [key: string]: any;
}

export interface PaymentResponse {
  id?: string;
  uuid?: string;
  [key: string]: any;
}

class SyncApiClient {
  /**
   * Crea una orden en el servidor.
   * Endpoint: POST /api/v1/orders
   */
  async createOrder(payload: CreateOrderPayload): Promise<OrderResponse> {
    const response = await apiClient.post("/orders", payload, {
      headers: {
        "Idempotency-Key": payload.idempotency_key,
      },
    });
    return response.data?.data || response.data;
  }

  /**
   * Actualiza una orden existente.
   * Endpoint: PUT /api/v1/orders/{uuid}
   */
  async updateOrder(uuid: string, payload: Partial<CreateOrderPayload>): Promise<OrderResponse> {
    const response = await apiClient.put(`/orders/${uuid}`, payload);
    return response.data?.data || response.data;
  }

  /**
   * Elimina una orden (soft delete).
   * Endpoint: DELETE /api/v1/orders/{uuid}
   */
  async deleteOrder(uuid: string): Promise<void> {
    await apiClient.delete(`/orders/${uuid}`);
  }

  /**
   * Crea un pago en el servidor.
   * Endpoint: POST /api/v1/payments
   */
  async createPayment(payload: CreatePaymentPayload): Promise<PaymentResponse> {
    const response = await apiClient.post("/payments", payload, {
      headers: {
        "Idempotency-Key": payload.idempotency_key,
      },
    });
    return response.data?.data || response.data;
  }

  /**
   * Actualiza el estado de una mesa.
   * Endpoint: PATCH /api/v1/tables/{uuid}/status
   */
  async updateTableStatus(uuid: string, status: string): Promise<any> {
    const response = await apiClient.patch(`/tables/${uuid}/status`, { status });
    return response.data?.data || response.data;
  }

  /**
   * Obtiene estadísticas de sync del backend.
   * Endpoint: GET /api/v1/sync/status
   */
  async getSyncStatus(branchId: string): Promise<any> {
    const response = await apiClient.get(`/sync/status?branch_id=${branchId}`);
    return response.data?.data || response.data;
  }

  /**
   * Health check del sistema de sincronización.
   * Endpoint: GET /api/v1/sync/health
   */
  async healthCheck(): Promise<any> {
    const response = await apiClient.get("/sync/health");
    return response.data?.data || response.data;
  }
}

export const syncApi = new SyncApiClient();
