export type BillType = "equal_split" | "by_items" | "custom_amount";

export type BillStatus = "open" | "partial" | "paid" | "cancelled";

export interface Bill {
  uuid: string;
  bill_number: string;
  type: BillType;
  subtotal: number;
  tax_amount: number;
  total: number;
  paid_amount: number;
  remaining_amount: number;
  status: BillStatus;
  guest_count: number;
  item_ids?: number[];
}

export interface SplitEqualPayload {
  type: "equal_split";
  parts: number;
}

export interface SplitByItemsPayload {
  type: "by_items";
  groups: Array<{
    item_ids: number[];
    guest_count?: number;
  }>;
}

export interface SplitCustomAmountsPayload {
  type: "custom_amount";
  amounts: number[];
}

export type SplitPayload =
  | SplitEqualPayload
  | SplitByItemsPayload
  | SplitCustomAmountsPayload;

export interface PayBillPayload {
  payment_method_uuid: string;
  tip_amount?: number;
  reference_code?: string;
  notes?: string;
  idempotency_key: string;
}

export interface PayBillResponse {
  success: boolean;
  bill_uuid: string;
  bill_paid: boolean;
  paid_amount: number;
  remaining_amount: number;
  order_transitioned_to_paid: boolean;
  amount_paid: number;
  tip_amount: number;
}
