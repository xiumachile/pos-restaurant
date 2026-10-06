import apiClient from '@/services/apiClient';

export interface AddItemPayload {
  product_uuid?: string;
  menu_item_uuid?: string;
  quantity: number;
  notes?: string | null;
}

/**
 * Agrega items a un pedido existente
 * Endpoint: POST /api/v1/orders/{orderUuid}/items
 */
export async function addItemsToOrder(
  orderUuid: string,
  items: AddItemPayload[]
): Promise<void> {
  for (const item of items) {
    await apiClient.post(`/orders/${orderUuid}/items`, item);
  }
}
