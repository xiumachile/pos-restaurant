import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { CartItem, CartTotals, TableCart } from "@/types/cart";
import type { Product } from "@/types/catalog";
import { parsePrice } from "@/types/catalog";

function generateUUID(): string {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

interface CartState {
  /** Carritos activos, uno por mesa (key = tableUuid) */
  carts: Record<string, TableCart>;

  /** Inicializa el carrito de una mesa si no existe */
  initCart: (tableUuid: string, tableNumber: string, areaName?: string) => void;

  /** Agrega un producto al carrito de una mesa (o incrementa cantidad) */
  addItem: (tableUuid: string, product: Product, quantity?: number) => void;

  /** Quita un item del carrito */
  removeItem: (tableUuid: string, itemId: string) => void;

  /** Actualiza cantidad (si llega a 0, elimina el item) */
  updateQuantity: (tableUuid: string, itemId: string, quantity: number) => void;

  /** Vacía el carrito de una mesa */
  clearCart: (tableUuid: string) => void;

  /** Obtiene el carrito de una mesa (o null) */
  getCart: (tableUuid: string) => TableCart | null;

  /** Calcula totales de una mesa */
  getTotals: (tableUuid: string) => CartTotals;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      carts: {},

      initCart: (tableUuid, tableNumber, areaName) => {
        set((state) => {
          if (state.carts[tableUuid]) return state;
          return {
            carts: {
              ...state.carts,
              [tableUuid]: {
                tableUuid,
                tableNumber,
                areaName,
                items: [],
                createdAt: new Date().toISOString(),
              },
            },
          };
        });
      },

      addItem: (tableUuid, product, quantity = 1) => {
        set((state) => {
          const cart = state.carts[tableUuid];
          if (!cart) return state;

          const existing = cart.items.find((i) => i.product.id === product.id);

          const items = existing
            ? cart.items.map((i) =>
                i.product.id === product.id
                  ? { ...i, quantity: i.quantity + quantity }
                  : i
              )
            : [
                ...cart.items,
                { id: generateUUID(), product, quantity },
              ];

          return {
            carts: { ...state.carts, [tableUuid]: { ...cart, items } },
          };
        });
      },

      removeItem: (tableUuid, itemId) => {
        set((state) => {
          const cart = state.carts[tableUuid];
          if (!cart) return state;
          return {
            carts: {
              ...state.carts,
              [tableUuid]: {
                ...cart,
                items: cart.items.filter((i) => i.id !== itemId),
              },
            },
          };
        });
      },

      updateQuantity: (tableUuid, itemId, quantity) => {
        if (quantity <= 0) {
          get().removeItem(tableUuid, itemId);
          return;
        }
        set((state) => {
          const cart = state.carts[tableUuid];
          if (!cart) return state;
          return {
            carts: {
              ...state.carts,
              [tableUuid]: {
                ...cart,
                items: cart.items.map((i) =>
                  i.id === itemId ? { ...i, quantity } : i
                ),
              },
            },
          };
        });
      },

      clearCart: (tableUuid) => {
        set((state) => {
          const { [tableUuid]: _removed, ...rest } = state.carts;
          return { carts: rest };
        });
      },

      getCart: (tableUuid) => {
        return get().carts[tableUuid] ?? null;
      },

      getTotals: (tableUuid) => {
        const cart = get().carts[tableUuid];
        if (!cart) return { subtotal: 0, tax: 0, total: 0, itemCount: 0 };

        const subtotal = cart.items.reduce(
          (sum, item) => sum + parsePrice(item.product.base_price) * item.quantity,
          0
        );
        const tax = subtotal * 0.19;
        const itemCount = cart.items.reduce((sum, i) => sum + i.quantity, 0);

        return { subtotal, tax, total: subtotal + tax, itemCount };
      },
    }),
    {
      name: "pos-cart-storage",
    }
  )
);
