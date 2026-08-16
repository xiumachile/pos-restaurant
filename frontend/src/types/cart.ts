import type { Product } from "./catalog";

export interface CartItem {
  id: string;
  product: Product;
  quantity: number;
  notes?: string;
}

/**
 * Carrito de una mesa específica.
 */
export interface TableCart {
  tableUuid: string;
  tableNumber: string;
  areaName?: string;
  items: CartItem[];
  createdAt: string;
}

export interface CartTotals {
  subtotal: number;
  tax: number;
  total: number;
  itemCount: number;
}
