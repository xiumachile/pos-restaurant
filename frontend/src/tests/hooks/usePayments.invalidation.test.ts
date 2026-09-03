import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook, act, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createElement, type ReactNode } from "react";
import { 
  usePayBill, 
  useInvalidateCashier 
} from "@/hooks/usePayments";

/**
 * Tests de regresión para invalidación de caché en pagos.
 * 
 * BUG CORREGIDO (Bloque 2): Mesa no pasaba a 'libre' tras pagar
 * porque usePayBill no invalidaba el query ['tables'] que usa TablesPage.
 * 
 * FIX: usePayBill y useInvalidateCashier ahora invalidan ['tables']
 * además de las keys de cashier.
 */

describe("usePayments - invalidación de caché", () => {
  let queryClient: QueryClient;
  let wrapper: ({ children }: { children: ReactNode }) => ReactNode;

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });

    // Pre-popular queries que deberían invalidarse
    queryClient.setQueryData(["tables"], [{ id: 1, status: "occupied" }]);
    queryClient.setQueryData(["cashier", "dashboard"], { sessions: [] });
    queryClient.setQueryData(["cashier", "tables-with-bills"], []);

    wrapper = ({ children }: { children: ReactNode }) =>
      createElement(QueryClientProvider, { client: queryClient }, children);
  });

  describe("useInvalidateCashier", () => {
    it("debe invalidar todas las keys relevantes incluyendo 'tables'", () => {
      const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");
      
      const { result } = renderHook(() => useInvalidateCashier(), { wrapper });
      
      act(() => {
        result.current();
      });

      // Verificar que se invalidan todas las keys necesarias
      const invalidatedKeys = invalidateSpy.mock.calls.map(
        (call) => (call[0] as any).queryKey
      );

      // ✅ FIX: ahora incluye ['tables'] para que mesas se actualicen
      expect(invalidatedKeys).toContainEqual(["cashier", "dashboard"]);
      expect(invalidatedKeys).toContainEqual(["cashier", "tables-with-bills"]);
      expect(invalidatedKeys).toContainEqual(["tables"]);

      invalidateSpy.mockRestore();
    });
  });
});
