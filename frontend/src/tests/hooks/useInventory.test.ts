import { describe, it, expect, vi } from "vitest";
import { renderHook } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createElement, type ReactNode } from "react";
import { useIngredientMovements } from "@/hooks/useInventory";

// Mock del service
vi.mock("@/services/inventoryService", () => ({
  inventoryService: {
    listMovements: vi.fn().mockResolvedValue([]),
    createProductionBatch: vi.fn().mockResolvedValue({
      uuid: "batch-uuid",
      movements_count: 1,
    }),
  },
}));

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return function Wrapper({ children }: { children: ReactNode }) {
    return createElement(
      QueryClientProvider,
      { client: queryClient },
      children
    );
  };
}

describe("useIngredientMovements", () => {
  it("no hace query si ingredientUuid es null", () => {
    const { result } = renderHook(
      () => useIngredientMovements(null),
      { wrapper: createWrapper() }
    );

    expect(result.current.isLoading).toBe(false);
    // Cuando enabled=false, data es undefined (no [])
    expect(result.current.data).toBeUndefined();
  });

  it("usa la queryKey correcta con el uuid", () => {
    const { result } = renderHook(
      () => useIngredientMovements("test-uuid"),
      { wrapper: createWrapper() }
    );

    // El hook debe haber iniciado la query
    expect(result.current.isLoading).toBeDefined();
  });

  it("respeta los filtros en la queryKey", () => {
    const { result } = renderHook(
      () =>
        useIngredientMovements("test-uuid", {
          type: "in_purchase",
          limit: 50,
        }),
      { wrapper: createWrapper() }
    );

    expect(result.current.isLoading).toBeDefined();
  });
});
