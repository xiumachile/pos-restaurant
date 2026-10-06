import apiClient from '@/services/apiClient';
import type { Order } from '@/types/orders';

export interface CreateOrderPayload {
  table_id?: string | null;
  order_type: string;
  items: Array<{
    product_id: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    notes?: string | null;
  }>;
}

export interface AddItemPayload {
  product_uuid?: string;
  menu_item_uuid?: string;
  quantity: number;
  notes?: string | null;
}

/**
 * Servicio para operaciones de pedidos vía API
 */
export const ordersService = {
  /**
   * Obtiene un pedido por su UUID
   */
  async getByUuid(uuid: string): Promise<Order> {
    const response = await apiClient.get<{ data: Order }>(`/orders/${uuid}`);
    return response.data.data;
  },

  /**
   * Crea un nuevo pedido con items
   */
  async create(payload: CreateOrderPayload): Promise<Order> {
    const response = await apiClient.post<{ data: Order }>('/orders', payload);
    return response.data.data;
  },

  /**
   * Agrega un item a un pedido existente
   */
  async addItem(orderUuid: string, payload: AddItemPayload): Promise<Order> {
    const response = await apiClient.post<{ data: Order }>(`/orders/${orderUuid}/items`, payload);
    return response.data.data;
  },

  /**
   * Lista pedidos activos
   */
  async listActive(): Promise<Order[]> {
    const response = await apiClient.get<{ data: Order[] }>('/orders/active');
    return response.data.data;
  },

  /**
   * Lista pedidos de una mesa específica
   */
  async listTableOrders(tableUuid: string): Promise<Order[]> {
    const response = await apiClient.get<{ data: Order[] }>(`/tables/${tableUuid}/orders`);
    return response.data.data;
  },

  /**
   * Confirma un pedido (transición de estado)
   */
  async confirm(orderUuid: string): Promise<Order> {
    const response = await apiClient.post<{ data: Order }>(`/orders/${orderUuid}/confirm`);
    return response.data.data;
  },
};
