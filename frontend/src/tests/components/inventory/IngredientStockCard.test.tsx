import { render, screen, fireEvent } from "@testing-library/react";
import { describe, it, expect, vi } from "vitest";
import { IngredientStockCard } from "@/components/inventory/IngredientStockCard";
import type { RawIngredient } from "@/services/recipeService";

const baseIngredient: RawIngredient = {
  uuid: "test-uuid",
  sku: "HARINA-001",
  name_translations: { es: "Harina", "zh-CN": "面粉" },
  dimension_type: "mass",
  base_unit: "g",
  current_stock_base: 5000,
  minimum_stock_base: 1000,
  cost_per_base_unit: 2.5,
  total_stock_value: 12500,
  is_active: true,
  is_low_stock: false,
  created_at: "2026-10-02T10:00:00Z",
};

describe("IngredientStockCard", () => {
  it("muestra nombre, SKU y stock correctamente", () => {
    const onClick = vi.fn();
    render(
      <IngredientStockCard
        ingredient={baseIngredient}
        isSelected={false}
        onClick={onClick}
      />
    );

    expect(screen.getByText("Harina")).toBeInTheDocument();
    expect(screen.getByText("HARINA-001")).toBeInTheDocument();
    expect(screen.getByText("5.000")).toBeInTheDocument();
    expect(screen.getByText("g")).toBeInTheDocument();
  });

  it("muestra estado 'bajo' cuando is_low_stock=true", () => {
    const lowIngredient: RawIngredient = {
      ...baseIngredient,
      is_low_stock: true,
    };

    render(
      <IngredientStockCard
        ingredient={lowIngredient}
        isSelected={false}
        onClick={vi.fn()}
      />
    );

    // El contenedor debe tener clases de warning
    const card = screen.getByText("Harina").closest("button");
    expect(card?.className).toContain("yellow");
  });

  it("muestra estado 'sin stock' cuando current_stock_base=0", () => {
    const outIngredient: RawIngredient = {
      ...baseIngredient,
      current_stock_base: 0,
    };

    render(
      <IngredientStockCard
        ingredient={outIngredient}
        isSelected={false}
        onClick={vi.fn()}
      />
    );

    const card = screen.getByText("Harina").closest("button");
    expect(card?.className).toContain("red");
  });

  it("llama onClick al hacer click", () => {
    const onClick = vi.fn();
    render(
      <IngredientStockCard
        ingredient={baseIngredient}
        isSelected={false}
        onClick={onClick}
      />
    );

    fireEvent.click(screen.getByText("Harina"));
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it("aplica estilo seleccionado cuando isSelected=true", () => {
    render(
      <IngredientStockCard
        ingredient={baseIngredient}
        isSelected={true}
        onClick={vi.fn()}
      />
    );

    const card = screen.getByText("Harina").closest("button");
    expect(card?.className).toContain("border-orange-500");
  });
});
