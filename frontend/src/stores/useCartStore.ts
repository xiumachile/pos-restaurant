import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { CartItem, CartTotals } from "@/types/cart";
import type { Product } from "@/types/catalog";
import { parsePrice } from "@/types/catalog";

interface CartState {
  tableId: string | null;
  tableNumber: string | null;
  items: CartItem[];
  createdAt: string | null;

  setTable: (tableId: string, tableNumber: string) => void;
  clearTable: () => void;
  addItem: (product: Product, quantity?: number, notes?: string) => void;
  removeItem: (itemId: string) => void;
  updateQuantity: (itemId: string, quantity: number) => void;
  updateNotes: (itemId: string, notes: string) => void;
  clearCart: () => void;

  getTotals: () => CartTotals;
  getItemByProductId: (productId: number) => CartItem | undefined;
}

function generateUUID(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      tableId: null,
      tableNumber: null,
      items: [],
      createdAt: null,

      setTable: (tableId, tableNumber) => {
        set({
          tableId,
          tableNumber,
          createdAt: new Date().toISOString(),
        });
      },

      clearTable: () => {
        set({
          tableId: null,
          tableNumber: null,
          items: [],
          createdAt: null,
        });
      },

      addItem: (product, quantity = 1, notes) => {
        set((state) => {
          const existingItem = state.items.find(
            (item) => item.product.id === product.id
          );

          if (existingItem) {
            return {
              items: state.items.map((item) =>
                item.product.id === product.id
                  ? { ...item, quantity: item.quantity + quantity }
                  : item
              ),
            };
          } else {
            const newItem: CartItem = {
              id: generateUUID(),
              product,
              quantity,
              notes,
            };
            return {
              items: [...state.items, newItem],
            };
          }
        });
      },

      removeItem: (itemId) => {
        set((state) => ({
          items: state.items.filter((item) => item.id !== itemId),
        }));
      },

      updateQuantity: (itemId, quantity) => {
        if (quantity <= 0) {
          get().removeItem(itemId);
          return;
        }
        set((state) => ({
          items: state.items.map((item) =>
            item.id === itemId ? { ...item, quantity } : item
          ),
        }));
      },

      updateNotes: (itemId, notes) => {
        set((state) => ({
          items: state.items.map((item) =>
            item.id === itemId ? { ...item, notes } : item
          ),
        }));
      },

      clearCart: () => {
        set({
          items: [],
          tableId: null,
          tableNumber: null,
          createdAt: null,
        });
      },

      getTotals: () => {
        const { items } = get();
        const subtotal = items.reduce((sum, item) => {
          const price = parsePrice(item.product.base_price);
          return sum + price * item.quantity;
        }, 0);

        const taxRate = 0.19;
        const tax = subtotal * taxRate;
        const total = subtotal + tax;
        const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

        return { subtotal, tax, total, itemCount };
      },

      getItemByProductId: (productId) => {
        return get().items.find((item) => item.product.id === productId);
      },
    }),
    {
      name: "pos-cart-storage",
      partialize: (state) => ({
        tableId: state.tableId,
        tableNumber: state.tableNumber,
        items: state.items,
        createdAt: state.createdAt,
      }),
    }
  )
);
