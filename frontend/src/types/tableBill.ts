export interface TableBillOrderItem {
  uuid: string;
  name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  notes: string | null;
}

export interface TableBillOrder {
  uuid: string;
  order_number: string;
  status: string;
  subtotal: number;
  tax_amount: number;
  total: number;
  waiter_name: string | null;
  served_at: string | null;
  items: TableBillOrderItem[];
}

export interface TableBill {
  table_uuid: string;
  table_number: string;
  area_code: string;
  capacity: number;
  orders_count: number;
  total_items: number;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  first_order_at: string | null;
  last_order_at: string | null;
  orders: TableBillOrder[];
}

export interface ChargeTablePayload {
  payment_method_uuid: string;
  tip_amount?: number;
  reference_code?: string;
  notes?: string;
  idempotency_key: string;
}

export interface ChargeTableResponse {
  success: boolean;
  orders_charged: number;
  total_charged: number;
  total_tip: number;
  grand_total: number;
  table_freed: boolean;
  table_number: string;
}
