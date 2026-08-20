import { create } from "zustand";
import { 
  OrderRepository, 
  type LocalOrder, 
  type LocalOrderItem,
  type CreateOrderPayload 
} from "../db/repositories/OrderRepository";
import { SyncQueueRepository } from "../db/repositories/SyncQueueRepository";

interface OfflineOrderState {
  // Estado
  orders: LocalOrder[];
  currentOrder: LocalOrder | null;
  currentOrderItems: LocalOrderItem[];
  isLoading: boolean;
  error: string | null;

  // Acciones de pedidos
  createOrder: (payload: CreateOrderPayload) => Promise<LocalOrder>;
  loadOrdersByBranch: (branchId: string) => Promise<void>;
  setCurrentOrder: (orderUuid: string) => Promise<void>;
  clearCurrentOrder: () => void;
  updateOrderStatus: (orderUuid: string, status: LocalOrder["status"]) => Promise<void>;
  deleteOrder: (orderUuid: string) => Promise<void>;

  // Acciones de items
  addItemToCurrentOrder: (item: {
    product_id: string;
    product_name: string;
    quantity: number;
    unit_price: number;
    notes?: string;
  }) => Promise<LocalOrderItem | null>;
}

export const useOfflineOrderStore = create<OfflineOrderState>((set, get) => ({
  orders: [],
  currentOrder: null,
  currentOrderItems: [],
  isLoading: false,
  error: null,

  createOrder: async (payload) => {
    set({ isLoading: true, error: null });
    try {
      const order = await OrderRepository.create(payload);
      
      // Encolar evento de sincronización
      await SyncQueueRepository.enqueue({
        company_id: payload.company_id,
        branch_id: payload.branch_id,
        entity_type: "order",
        entity_local_uuid: order.local_uuid,
        action: "create",
        payload: order,
      });

      set((state) => ({
        orders: [order, ...state.orders],
        currentOrder: order,
        currentOrderItems: [],
        isLoading: false,
      }));

      return order;
    } catch (error: any) {
      const message = error?.message || "Error creando pedido";
      set({ error: message, isLoading: false });
      throw error;
    }
  },

  loadOrdersByBranch: async (branchId) => {
    set({ isLoading: true, error: null });
    try {
      const orders = await OrderRepository.findAllByBranch(branchId);
      set({ orders, isLoading: false });
    } catch (error: any) {
      set({ error: error?.message || "Error cargando pedidos", isLoading: false });
    }
  },

  setCurrentOrder: async (orderUuid) => {
    set({ isLoading: true, error: null });
    try {
      const order = await OrderRepository.findByLocalUuid(orderUuid);
      const items = await OrderRepository.findItemsByOrderLocalUuid(orderUuid);
      set({ 
        currentOrder: order, 
        currentOrderItems: items, 
        isLoading: false 
      });
    } catch (error: any) {
      set({ error: error?.message || "Error cargando pedido", isLoading: false });
    }
  },

  clearCurrentOrder: () => {
    set({ currentOrder: null, currentOrderItems: [] });
  },

  updateOrderStatus: async (orderUuid, status) => {
    try {
      await OrderRepository.updateStatus(orderUuid, status);
      
      // Encolar evento de sincronización
      const order = await OrderRepository.findByLocalUuid(orderUuid);
      if (order) {
        await SyncQueueRepository.enqueue({
          company_id: order.company_id,
          branch_id: order.branch_id,
          entity_type: "order",
          entity_local_uuid: orderUuid,
          action: "update",
          payload: { status },
        });
      }

      // Actualizar estado local
      set((state) => ({
        orders: state.orders.map((o) => 
          o.local_uuid === orderUuid ? { ...o, status } : o
        ),
        currentOrder: state.currentOrder?.local_uuid === orderUuid
          ? { ...state.currentOrder, status }
          : state.currentOrder,
      }));
    } catch (error: any) {
      set({ error: error?.message || "Error actualizando estado" });
      throw error;
    }
  },

  deleteOrder: async (orderUuid) => {
    try {
      const order = await OrderRepository.findByLocalUuid(orderUuid);
      await OrderRepository.delete(orderUuid);

      // Encolar evento de sincronización (solo si tenía cloud_id)
      if (order?.cloud_id) {
        await SyncQueueRepository.enqueue({
          company_id: order.company_id,
          branch_id: order.branch_id,
          entity_type: "order",
          entity_local_uuid: orderUuid,
          action: "delete",
          payload: { cloud_id: order.cloud_id },
        });
      }

      set((state) => ({
        orders: state.orders.filter((o) => o.local_uuid !== orderUuid),
        currentOrder: state.currentOrder?.local_uuid === orderUuid
          ? null
          : state.currentOrder,
        currentOrderItems: state.currentOrder?.local_uuid === orderUuid
          ? []
          : state.currentOrderItems,
      }));
    } catch (error: any) {
      set({ error: error?.message || "Error eliminando pedido" });
      throw error;
    }
  },

  addItemToCurrentOrder: async (item) => {
    const { currentOrder } = get();
    if (!currentOrder) {
      set({ error: "No hay pedido activo" });
      return null;
    }

    try {
      const newItem = await OrderRepository.addItem(currentOrder.local_uuid, item);

      // Encolar evento de sincronización del item
      await SyncQueueRepository.enqueue({
        company_id: currentOrder.company_id,
        branch_id: currentOrder.branch_id,
        entity_type: "order",
        entity_local_uuid: currentOrder.local_uuid,
        action: "update",
        payload: { action: "add_item", item: newItem },
      });

      // Recargar pedido actualizado (los totales cambian)
      const updatedOrder = await OrderRepository.findByLocalUuid(currentOrder.local_uuid);
      const updatedItems = await OrderRepository.findItemsByOrderLocalUuid(currentOrder.local_uuid);

      set({
        currentOrder: updatedOrder,
        currentOrderItems: updatedItems,
        orders: get().orders.map((o) =>
          o.local_uuid === currentOrder.local_uuid ? updatedOrder! : o
        ),
      });

      return newItem;
    } catch (error: any) {
      set({ error: error?.message || "Error agregando item" });
      throw error;
    }
  },
}));
