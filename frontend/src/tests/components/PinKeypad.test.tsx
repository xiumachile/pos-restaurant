import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { PinKeypad } from "@/components/auth/PinKeypad";

describe("PinKeypad", () => {
  it("debería renderizar todos los dígitos", () => {
    render(<PinKeypad pin="" onPinChange={vi.fn()} maxLength={6} />);

    expect(screen.getByText("1")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();
    expect(screen.getByText("9")).toBeInTheDocument();
    expect(screen.getByText("0")).toBeInTheDocument();
    expect(screen.getByText("C")).toBeInTheDocument();
    expect(screen.getByText("⌫")).toBeInTheDocument();
  });

  it("debería agregar dígito al hacer click", () => {
    const onPinChange = vi.fn();
    render(<PinKeypad pin="" onPinChange={onPinChange} maxLength={6} />);

    fireEvent.click(screen.getByText("1"));
    expect(onPinChange).toHaveBeenCalledWith("1");

    fireEvent.click(screen.getByText("2"));
    expect(onPinChange).toHaveBeenCalledWith("2");
  });

  it("debería borrar último dígito con backspace", () => {
    const onPinChange = vi.fn();
    render(<PinKeypad pin="123" onPinChange={onPinChange} maxLength={6} />);

    fireEvent.click(screen.getByText("⌫"));
    expect(onPinChange).toHaveBeenCalledWith("12");
  });

  it("debería limpiar todo con C", () => {
    const onPinChange = vi.fn();
    render(<PinKeypad pin="123" onPinChange={onPinChange} maxLength={6} />);

    fireEvent.click(screen.getByText("C"));
    expect(onPinChange).toHaveBeenCalledWith("");
  });

  it("debería mostrar indicadores de PIN ingresado", () => {
    render(<PinKeypad pin="12" onPinChange={vi.fn()} maxLength={6} />);

    // Buscar todos los dots por data-testid
    const dots = screen.getAllByTestId("pin-dot");
    expect(dots).toHaveLength(6);

    // Los primeros 2 deberían tener aria-label "Dígito ingresado"
    expect(dots[0]).toHaveAttribute("aria-label", "Dígito ingresado");
    expect(dots[1]).toHaveAttribute("aria-label", "Dígito ingresado");
    
    // Los restantes deberían tener aria-label "Dígito vacío"
    expect(dots[2]).toHaveAttribute("aria-label", "Dígito vacío");
    expect(dots[3]).toHaveAttribute("aria-label", "Dígito vacío");
    expect(dots[4]).toHaveAttribute("aria-label", "Dígito vacío");
    expect(dots[5]).toHaveAttribute("aria-label", "Dígito vacío");

    // Verificar clases CSS
    expect(dots[0].className).toContain("bg-orange-500");
    expect(dots[1].className).toContain("bg-orange-500");
    expect(dots[2].className).toContain("border-slate-600");
  });

  it("debería deshabilitar botones cuando disabled=true", () => {
    render(<PinKeypad pin="" onPinChange={vi.fn()} maxLength={6} disabled />);

    expect(screen.getByText("1")).toBeDisabled();
    expect(screen.getByText("C")).toBeDisabled();
    expect(screen.getByText("⌫")).toBeDisabled();
  });

  it("debería tener atributos de accesibilidad correctos", () => {
    render(<PinKeypad pin="123" onPinChange={vi.fn()} maxLength={6} />);

    // El grupo principal tiene aria-label
    expect(screen.getByRole("group", { name: /Teclado PIN/i })).toBeInTheDocument();

    // El grupo de dots tiene aria-label descriptivo
    expect(
      screen.getByRole("group", { name: /PIN: 3 de 6 dígitos ingresados/i })
    ).toBeInTheDocument();

    // Cada botón tiene aria-label
    expect(screen.getByLabelText("Dígito 1")).toBeInTheDocument();
    expect(screen.getByLabelText("Dígito 0")).toBeInTheDocument();
    expect(screen.getByLabelText("Limpiar todo")).toBeInTheDocument();
    expect(screen.getByLabelText("Borrar último dígito")).toBeInTheDocument();
  });
});
