import type { TableStatus } from "./tables";

export type OrderStatus =
  | "draft"
  | "confirmed"
  | "preparing"
  | "ready"
  | "served"
  | "paid"
  | "closed"
  | "cancelled";

export type OrderType = "dine_in" | "takeout" | "delivery";

export interface OrderItem {
  uuid: string;
  menu_item_uuid: string;
  name: string;
  unit_price: number;
  quantity: number;
  subtotal: number;
  notes?: string | null;
  created_at: string;
}

export interface Order {
  uuid: string;
  order_number: string;
  type: OrderType;
  type_label: string;
  status: OrderStatus;
  is_editable: boolean;
  is_active: boolean;
  table?: {
    uuid: string;
    table_number: string;
    area_code: string;
  } | null;
  waiter?: {
    uuid: string;
    name: string;
  } | null;
  items: OrderItem[];
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  notes: string | null;
  confirmed_at: string | null;
  served_at: string | null;
  paid_at: string | null;
  closed_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateOrderPayload {
  type: OrderType;
  table_uuid?: string;
  notes?: string;
}

export interface AddItemPayload {
  menu_item_uuid: string;
  quantity: number;
  notes?: string;
}
