import { describe, it, expect, beforeEach, vi, afterEach } from "vitest";

// Mock de syncApi
const mockAddOrderItem = vi.fn().mockResolvedValue({ uuid: "cloud-item-1" });
const mockUpdateOrder = vi.fn().mockResolvedValue({ uuid: "cloud-order-1" });

vi.mock("../../services/api/syncApi", () => ({
  syncApi: {
    addOrderItem: (...args: any[]) => mockAddOrderItem(...args),
    updateOrder: (...args: any[]) => mockUpdateOrder(...args),
    removeOrderItem: vi.fn().mockResolvedValue({}),
    createOrder: vi.fn().mockResolvedValue({ uuid: "cloud-order-1" }),
    createPayment: vi.fn().mockResolvedValue({ uuid: "cloud-payment-1" }),
    createBill: vi.fn().mockResolvedValue({ uuid: "cloud-bill-1" }),
  },
}));

// Mock de LocalDB
vi.mock("../../db/localDb", () => ({
  LocalDB: {
    getInstance: vi.fn().mockReturnValue({
      execute: vi.fn().mockResolvedValue([]),
      select: vi.fn().mockResolvedValue([]),
    }),
  },
}));

// Mock de repositories
vi.mock("../../db/repositories/OrderRepository", () => ({
  OrderRepository: {
    findByLocalUuid: vi.fn().mockResolvedValue({
      local_uuid: "order-local-1",
      cloud_id: "cloud-order-uuid-123",
      company_id: 1,
      branch_id: 1,
    }),
  },
}));

vi.mock("../../db/repositories/SyncQueueRepository", () => ({
  SyncQueueRepository: {
    findPending: vi.fn().mockResolvedValue([]),
    updateStatus: vi.fn().mockResolvedValue(undefined),
    getRetryCount: vi.fn().mockReturnValue(0),
  },
}));

vi.mock("../../store/useSyncStore", () => ({
  useSyncStore: {
    getState: vi.fn().mockReturnValue({
      status: "idle",
      isOnline: true,
      setStatus: vi.fn(),
      setLastError: vi.fn(),
    }),
  },
}));

vi.mock("../../store/useAuthStore", () => ({
  useAuthStore: {
    getState: vi.fn().mockReturnValue({
      user: { company_id: 1, branch_id: 1, id: 1 },
    }),
  },
}));

describe("SyncEngine - add_item crítico", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("add_item llama a POST /orders/{uuid}/items (NO a PUT /orders/{uuid})", async () => {
    const { SyncEngine } = await import("../../services/sync/SyncEngine");

    const syncItem = {
      id: "sync-1",
      entity_type: "order",
      entity_local_uuid: "order-local-1",
      action: "update",
      payload: JSON.stringify({
        action: "add_item",
        item: {
          local_uuid: "item-local-1",
          product_id: "product-uuid-1",
          quantity: 2,
          unit_price: 5000,
          notes: "Sin cebolla",
        },
      }),
      idempotency_key: "idem-key-1",
      company_id: 1,
      branch_id: 1,
    };

    await SyncEngine.processItem(syncItem as any);

    // VERIFICAR: se llamó addOrderItem, NO updateOrder
    expect(mockAddOrderItem).toHaveBeenCalledTimes(1);
    expect(mockUpdateOrder).not.toHaveBeenCalled();

    // VERIFICAR: payload correcto enviado a addOrderItem
    expect(mockAddOrderItem).toHaveBeenCalledWith(
      "cloud-order-uuid-123",
      expect.objectContaining({
        product_uuid: "product-uuid-1",
        quantity: 2,
        unit_price: 5000,
        notes: "Sin cebolla",
      })
    );
  });

  it("update normal (status) llama a PUT /orders/{uuid}", async () => {
    const { SyncEngine } = await import("../../services/sync/SyncEngine");

    const syncItem = {
      id: "sync-2",
      entity_type: "order",
      entity_local_uuid: "order-local-1",
      action: "update",
      payload: JSON.stringify({
        status: "confirmed",
        notes: "Mesa 5",
      }),
      idempotency_key: "idem-key-2",
      company_id: 1,
      branch_id: 1,
    };

    await SyncEngine.processItem(syncItem as any);

    expect(mockUpdateOrder).toHaveBeenCalledTimes(1);
    expect(mockAddOrderItem).not.toHaveBeenCalled();
  });

  it("remove_item llama a DELETE /orders/{uuid}/items/{itemUuid}", async () => {
    const { syncApi } = await import("../../services/api/syncApi");
    const mockRemove = vi.fn().mockResolvedValue({});
    (syncApi as any).removeOrderItem = mockRemove;

    const { SyncEngine } = await import("../../services/sync/SyncEngine");

    const syncItem = {
      id: "sync-3",
      entity_type: "order",
      entity_local_uuid: "order-local-1",
      action: "update",
      payload: JSON.stringify({
        action: "remove_item",
        item_uuid: "cloud-item-to-remove",
      }),
      idempotency_key: "idem-key-3",
      company_id: 1,
      branch_id: 1,
    };

    await SyncEngine.processItem(syncItem as any);

    expect(mockRemove).toHaveBeenCalledWith(
      "cloud-order-uuid-123",
      "cloud-item-to-remove"
    );
    expect(mockUpdateOrder).not.toHaveBeenCalled();
    expect(mockAddOrderItem).not.toHaveBeenCalled();
  });
});
