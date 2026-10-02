import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { OrderTakingPage } from "@/pages/OrderTakingPage";
import { useCartStore } from "@/stores/useCartStore";

/**
 * Mocks de dependencias.
 * Mantenemos el store real para probar la integración.
 */
vi.mock("@/hooks/useTables", () => ({
  useTables: () => ({
    data: [{
      area_code: "salon",
      area_name: "Salón principal",
      tables: [{
        uuid: "test-table-uuid",
        id: 1,
        table_number: "5",
        capacity: 4,
        status: "occupied",
        area_code: "salon",
        area_name: "Salón principal",
      }],
    }],
    isLoading: false,
  }),
}));

vi.mock("@/hooks/useTableOrders", () => ({
  useTableOrders: () => ({
    data: [],
    refetch: vi.fn(),
  }),
}));

vi.mock("@/store/useToastStore", () => ({
  useToastStore: () => ({ addToast: vi.fn() }),
}));

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) => key,
  }),
}));

vi.mock("@/components/CapabilityGate", () => ({
  CapabilityGate: ({ children }: any) => <>{children}</>,
}));

// Mockear los paneles que usan react-query (no son el foco de este test)
vi.mock("@/components/orders/OrderCatalogPanel", () => ({
  OrderCatalogPanel: ({ channel }: { channel: string }) => (
    <div data-testid="catalog-panel" data-channel={channel} />
  ),
}));

vi.mock("@/components/orders/OrderCartPanel", () => ({
  OrderCartPanel: () => <div data-testid="cart-panel" />,
}));

/**
 * Tests de OrderTakingPage con el nuevo modelo de canal por pedido.
 */
describe("OrderTakingPage - canal dine_in implícito", () => {
  beforeEach(() => {
    useCartStore.setState({ carts: {} });
  });

  const renderPage = (tableUuid = "test-table-uuid") => {
    return render(
      <MemoryRouter initialEntries={[`/tables/${tableUuid}`]}>
        <Routes>
          <Route path="/tables/:tableUuid" element={<OrderTakingPage />} />
        </Routes>
      </MemoryRouter>
    );
  };

  it("al entrar a una mesa, el cart se crea con canal dine_in", async () => {
    renderPage();

    await waitFor(() => {
      const cart = useCartStore.getState().getCart("test-table-uuid");
      expect(cart).not.toBeNull();
      expect(cart?.channel).toBe("dine_in");
    });
  });

  it("si la mesa ya tenía un cart previo, mantiene su canal dine_in", async () => {
    // Pre-crear el cart (simulando que el usuario ya había entrado antes)
    const { initOrder } = useCartStore.getState();
    initOrder({
      tableUuid: "test-table-uuid",
      tableNumber: "5",
      areaName: "Salón",
      channel: "dine_in",
    });

    renderPage();

    await waitFor(() => {
      const cart = useCartStore.getState().getCart("test-table-uuid");
      expect(cart?.channel).toBe("dine_in");
    });
  });

  it("si existía un pedido de delivery previo (de otra sesión), la mesa usa dine_in", async () => {
    // Simular: el garzón tenía un pedido de delivery abierto
    const { initOrder } = useCartStore.getState();
    initOrder({ tableUuid: null, channel: "delivery" });

    renderPage();

    await waitFor(() => {
      const mesaCart = useCartStore.getState().getCart("test-table-uuid");
      expect(mesaCart?.channel).toBe("dine_in");
    });

    // El pedido de delivery sigue existiendo con SU canal
    const carts = useCartStore.getState().carts;
    const deliveryCart = Object.values(carts).find(c => c.tableUuid === null);
    expect(deliveryCart?.channel).toBe("delivery");
  });
});
