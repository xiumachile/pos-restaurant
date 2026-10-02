import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { ChannelSelectionModal } from "@/components/orders/ChannelSelectionModal";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, params?: any) => {
      const translations: Record<string, string> = {
        "orders.new_order_title": "Nuevo pedido",
        "orders.select_channel": "Selecciona el canal",
        "orders.channel_dine_in": "Comedor",
        "orders.channel_delivery": "Delivery",
        "orders.channel_takeout": "Para llevar",
        "orders.cancel": "Cancelar",
      };
      return translations[key] || key;
    },
  }),
}));

/**
 * Tests del nuevo modal de selección de canal.
 * 
 * Este modal es la entrada al flujo "Pedido nuevo fuera de mesa".
 * El usuario DEBE elegir un canal antes de entrar al catálogo.
 */
describe("ChannelSelectionModal", () => {
  it("muestra los 3 canales disponibles (dine_in, delivery, takeout)", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal open={true} onSelect={onSelect} onClose={onClose} />);

    expect(screen.getByText("Comedor")).toBeDefined();
    expect(screen.getByText("Delivery")).toBeDefined();
    expect(screen.getByText("Para llevar")).toBeDefined();
  });

  it("NO muestra uber_eats ni rappi (son canales externos, no de toma manual)", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal open={true} onSelect={onSelect} onClose={onClose} />);

    expect(screen.queryByText(/uber_eats/i)).toBeNull();
    expect(screen.queryByText(/rappi/i)).toBeNull();
  });

  it("al seleccionar un canal, llama onSelect con ese canal", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal open={true} onSelect={onSelect} onClose={onClose} />);

    fireEvent.click(screen.getByText("Delivery"));
    expect(onSelect).toHaveBeenCalledWith("delivery");
    expect(onSelect).toHaveBeenCalledTimes(1);
  });

  it("al cancelar, llama onClose sin seleccionar canal", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal open={true} onSelect={onSelect} onClose={onClose} />);

    fireEvent.click(screen.getByText("Cancelar"));
    expect(onClose).toHaveBeenCalled();
    expect(onSelect).not.toHaveBeenCalled();
  });

  it("no se renderiza cuando open=false", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    const { container } = render(
      <ChannelSelectionModal open={false} onSelect={onSelect} onClose={onClose} />
    );

    expect(container.innerHTML).toBe("");
  });
});
