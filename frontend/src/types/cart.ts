import type { Product } from "./catalog";

export interface CartItem {
  id: string;
  product: Product;
  quantity: number;
  notes?: string;
  modifiers?: string[];
}

export interface CartTotals {
  subtotal: number;
  tax: number;
  total: number;
  itemCount: number;
}
