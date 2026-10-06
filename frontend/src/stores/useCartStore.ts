import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { CartItem, CartTotals, TableCart } from "@/types/cart";
import type { Product } from "@/types/catalog";
import type { ChannelType } from "@/stores/useActiveChannelStore";
import { parsePrice } from "@/types/catalog";
import { calculateTax } from "@/utils/money";
import { IVA_RATE } from "@/config/tax";

function generateUUID(): string {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Parámetros para iniciar un pedido nuevo.
 * - Si `tableUuid` está presente, es un pedido de mesa (canal típico: dine_in).
 * - Si `tableUuid` es null, es un pedido fuera de mesa (delivery/takeout),
 *   y se genera una clave única tipo `takeaway-{uuid}`.
 */
export interface InitOrderParams {
  tableUuid: string | null;
  tableNumber?: string;
  areaName?: string;
  channel: ChannelType;
  editingOrderId?: string | null;
}

interface CartState {
  /** Carritos activos, uno por pedido (key = tableUuid o takeaway-{uuid}) */
  carts: Record<string, TableCart>;

  /**
   * NUEVO (Fase 2): Inicia un pedido con canal fijado.
   * Reemplaza a initCart() para todos los casos nuevos.
   * @returns El cartKey generado (tableUuid para mesa, "takeaway-{uuid}" para fuera de mesa).
   *          Permite al caller navegar al pedido recién creado.
   */
  initOrder: (params: InitOrderParams) => string;

  /**
   * LEGACY: Inicializa el carrito de una mesa (canal dine_in implícito).
   * Mantiene compatibilidad con OrderTakingPage hasta el Bloque 5.
   * Equivalente a initOrder({ tableUuid, tableNumber, areaName, channel: 'dine_in' })
   */
  initCart: (tableUuid: string, tableNumber: string, areaName?: string) => void;

  /** Agrega un producto al carrito (o incrementa cantidad) */
  addItem: (cartKey: string, product: Product, quantity?: number) => void;

  /** Quita un item del carrito */
  removeItem: (cartKey: string, itemId: string) => void;

  /** Actualiza cantidad (si llega a 0, elimina el item) */
  updateQuantity: (cartKey: string, itemId: string, quantity: number) => void;

  /** Actualiza las notas de un item */
  updateItemNotes: (cartKey: string, itemId: string, notes: string) => void;

  /** Vacía el carrito */
  clearCart: (cartKey: string) => void;

  /** Obtiene el carrito (o null) */
  getCart: (cartKey: string) => TableCart | null;

  /** Calcula totales */
  getTotals: (cartKey: string) => CartTotals;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      carts: {},

      initOrder: (params) => {
        const { tableUuid, tableNumber = "", areaName, channel, editingOrderId } = params;
        const cartKey = tableUuid ?? `takeaway-${generateUUID()}`;

        set((state) => {
          // Idempotente: si el cart ya existe, no lo sobreescribe
          if (state.carts[cartKey]) return state;
          return {
            carts: {
              ...state.carts,
              [cartKey]: {
                tableUuid,
                tableNumber,
                areaName,
                channel,
                editingOrderId: editingOrderId || null,
                items: [],
                createdAt: new Date().toISOString(),
              },
            },
          };
        });
        return cartKey;
      },

      initCart: (tableUuid, tableNumber, areaName) => {
        // Wrapper legacy: canal dine_in implícito
        get().initOrder({
          tableUuid,
          tableNumber,
          areaName,
          channel: "dine_in",
        });
      },

      addItem: (cartKey, product, quantity = 1) => {
        console.log("[useCartStore] 🛒 addItem llamado");
        console.log("[useCartStore] 📦 cartKey:", cartKey);
        console.log("[useCartStore] 🏷️ product.uuid:", product?.uuid);
        console.log("[useCartStore] 🆔 product.id:", product?.id);
        console.log("[useCartStore] 📝 product.name:", product?.name_translations?.es || product?.name_translations?.en);
        console.log("[useCartStore] 🔢 quantity:", quantity);
        
        set((state) => {
          const cart = state.carts[cartKey];
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
            carts: { ...state.carts, [cartKey]: { ...cart, items } },
          };
        });
      },

      removeItem: (cartKey, itemId) => {
        set((state) => {
          const cart = state.carts[cartKey];
          if (!cart) return state;
          return {
            carts: {
              ...state.carts,
              [cartKey]: {
                ...cart,
                items: cart.items.filter((i) => i.id !== itemId),
              },
            },
          };
        });
      },

      updateQuantity: (cartKey, itemId, quantity) => {
        set((state) => {
          const cart = state.carts[cartKey];
          if (!cart) return state;

          if (quantity <= 0) {
            return {
              carts: {
                ...state.carts,
                [cartKey]: {
                  ...cart,
                  items: cart.items.filter((i) => i.id !== itemId),
                },
              },
            };
          }

          return {
            carts: {
              ...state.carts,
              [cartKey]: {
                ...cart,
                items: cart.items.map((i) =>
                  i.id === itemId ? { ...i, quantity } : i
                ),
              },
            },
          };
        });
      },

      updateItemNotes: (cartKey, itemId, notes) => {
        set((state) => {
          const cart = state.carts[cartKey];
          if (!cart) return state;
          return {
            carts: {
              ...state.carts,
              [cartKey]: {
                ...cart,
                items: cart.items.map((i) =>
                  i.id === itemId ? { ...i, notes } : i
                ),
              },
            },
          };
        });
      },

      clearCart: (cartKey) => {
        set((state) => {
          const cart = state.carts[cartKey];
          if (!cart) return state;
          return {
            carts: {
              ...state.carts,
              [cartKey]: { ...cart, items: [] },
            },
          };
        });
      },

      getCart: (cartKey) => {
        return get().carts[cartKey] || null;
      },

      getTotals: (cartKey) => {
        const cart = get().carts[cartKey];
        if (!cart) {
          return { subtotal: 0, tax: 0, total: 0, itemCount: 0 };
        }

        // En Chile, base_price YA incluye IVA (precio bruto)
        const total = cart.items.reduce((sum, item) => {
          const price = parsePrice(item.product.base_price);
          return sum + price * item.quantity;
        }, 0);

        // Extraer neto e IVA del precio bruto
        // Fórmula: net = total / 1.19, tax = total - net
        const subtotal = Math.round(total / (1 + IVA_RATE));
        const tax = total - subtotal;
        const itemCount = cart.items.reduce((sum, i) => sum + i.quantity, 0);

        return { subtotal, tax, total, itemCount };
      },
    }),
    {
      name: "pos-cart-storage",
      partialize: (state) => ({ carts: state.carts }),
    }
  )
);
