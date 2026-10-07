import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { ChannelSelectionModal } from "@/components/orders/ChannelSelectionModal";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, params?: any) => {
      const translations: Record<string, string> = {
        "orders.new_order_title": "Nuevo pedido",
        "orders.select_channel": "Seleccionar Canal",
        "orders.new_order_without_table": "Nuevo pedido sin mesa",
        "orders.delivery": "Delivery",
        "orders.takeout": "Para Llevar",
        "orders.uber_eats": "Uber Eats",
        "orders.rappi": "Rappi",
        "orders.dine_in_hint": "Para comer en el local, selecciona una mesa desde el plano",
        "common.close": "Cerrar",
      };
      return translations[key] || key;
    },
  }),
}));

/**
 * Tests del modal de selección de canal.
 *
 * Este modal es la entrada al flujo "Pedido nuevo fuera de mesa".
 * El usuario DEBE elegir un canal antes de entrar al catálogo.
 */
describe("ChannelSelectionModal", () => {
  it("muestra los 4 canales disponibles (delivery, takeout, uber_eats, rappi)", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal isOpen={true} onSelect={onSelect} onClose={onClose} />);

    expect(screen.getByText("Delivery")).toBeDefined();
    expect(screen.getByText("Para Llevar")).toBeDefined();
    expect(screen.getByText("Uber Eats")).toBeDefined();
    expect(screen.getByText("Rappi")).toBeDefined();
  });

  it("NO muestra dine_in (se inicia desde el plano de mesas)", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal isOpen={true} onSelect={onSelect} onClose={onClose} />);

    expect(screen.queryByText("Comedor")).toBeNull();
    expect(screen.queryByText("Dine In")).toBeNull();
  });

  it("al seleccionar un canal, llama onSelect con ese canal", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal isOpen={true} onSelect={onSelect} onClose={onClose} />);

    fireEvent.click(screen.getByText("Delivery"));
    expect(onSelect).toHaveBeenCalledWith("delivery");
    expect(onSelect).toHaveBeenCalledTimes(1);
  });

  it("al cerrar (botón X), llama onClose sin seleccionar canal", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(<ChannelSelectionModal isOpen={true} onSelect={onSelect} onClose={onClose} />);

    // El botón de cerrar tiene title="Cerrar"
    const closeButton = screen.getByTitle("Cerrar");
    fireEvent.click(closeButton);
    expect(onClose).toHaveBeenCalled();
    expect(onSelect).not.toHaveBeenCalled();
  });

  it("no se renderiza cuando isOpen=false", () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();

    const { container } = render(
      <ChannelSelectionModal isOpen={false} onSelect={onSelect} onClose={onClose} />
    );

    expect(container.innerHTML).toBe("");
  });
});
