import apiClient from "./apiClient";
import type {
  Order,
  CreateOrderPayload,
  AddItemPayload,
} from "@/types/orders";

interface OrderResponse {
  data: Order;
}

export const ordersService = {
  /**
   * Crea un nuevo pedido en estado DRAFT.
   */
  async create(payload: CreateOrderPayload): Promise<Order> {
    const response = await apiClient.post<OrderResponse>("/orders", payload);
    return response.data.data;
  },

  /**
   * Agrega un item al pedido (solo permitido en estado DRAFT).
   */
  async addItem(orderUuid: string, payload: AddItemPayload): Promise<Order> {
    const response = await apiClient.post<OrderResponse>(
      `/orders/${orderUuid}/items`,
      payload
    );
    return response.data.data;
  },

  /**
   * Confirma el pedido. Dispara eventos:
   * - Reserva de stock
   * - Impresión de comanda en cocina
   * - Descuento de recetas
   *
   * Solo permitido en estado DRAFT.
   */
  async confirm(orderUuid: string): Promise<Order> {
    const response = await apiClient.post<OrderResponse>(
      `/orders/${orderUuid}/confirm`
    );
    return response.data.data;
  },

  /**
   * Cancela el pedido (solo en estados DRAFT, CONFIRMED, PREPARING, READY).
   */
  async cancel(orderUuid: string, reason?: string): Promise<Order> {
    const response = await apiClient.post<OrderResponse>(
      `/orders/${orderUuid}/cancel`,
      { reason }
    );
    return response.data.data;
  },

  /**
   * Elimina un pedido (solo en estado DRAFT).
   */
  async delete(orderUuid: string): Promise<void> {
    await apiClient.delete(`/orders/${orderUuid}`);
  },

  /**
   * Obtiene un pedido por UUID.
   */
  async show(orderUuid: string): Promise<Order> {
    const response = await apiClient.get<OrderResponse>(`/orders/${orderUuid}`);
    return response.data.data;
  },
};
