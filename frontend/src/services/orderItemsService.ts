import apiClient from './apiClient';

export interface AddItemPayload {
  product_uuid: string;
  product_name: string;
  quantity: number;
  unit_price: number;
  notes?: string | null;
}

export interface AddItemResponse {
  uuid: string;
  order_uuid: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

/**
 * Agrega items a un pedido existente vía API
 * Endpoint: POST /api/v1/orders/{orderUuid}/items
 */
export const orderItemsService = {
  async addItems(orderUuid: string, items: AddItemPayload[]): Promise<AddItemResponse[]> {
    // Enviar items uno por uno (el backend acepta uno a la vez)
    const responses: AddItemResponse[] = [];
    
    for (const item of items) {
      const response = await apiClient.post<AddItemResponse>(
        `/orders/${orderUuid}/items`,
        item
      );
      responses.push(response.data);
    }
    
    return responses;
  },
};
