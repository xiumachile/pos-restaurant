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
  amount?: number; // Opcional: si se omite, paga el remaining_amount completo
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

// ═══════════════════════════════════════════════════════════════
// TIPOS LOCALES (modelo offline - SQLite)
// ═══════════════════════════════════════════════════════════════
// Estos tipos representan la estructura de la tabla local_bills
// creada en migración 004. Convención: local_uuid/cloud_id (igual
// que local_orders y local_payments).

export type LocalBillStatus = "open" | "partial" | "paid" | "cancelled";
export type BillSyncStatus = "pending" | "syncing" | "synced" | "failed";

/**
 * Bill tal como se almacena en SQLite (offline-first).
 */
export interface LocalBill {
  local_uuid: string;
  cloud_id: string | null;
  company_id: string;
  branch_id: string;
  terminal_id: string | null;
  order_local_uuid: string | null;
  order_cloud_id: string | null;
  bill_number: string;
  subtotal: number;
  discount_total: number;
  tax_total: number;
  tip_amount: number;
  grand_total: number;
  paid_amount: number;
  remaining_amount: number;
  status: LocalBillStatus;
  idempotency_key: string;
  sync_status: BillSyncStatus;
  sync_error: string | null;
  notes: string | null;
  created_at: string;
}

/**
 * Payload para crear una bill local.
 * paid_amount inicia en 0, remaining_amount = grand_total.
 */
export interface CreateLocalBillPayload {
  company_id: string;
  branch_id: string;
  terminal_id?: string;
  order_local_uuid?: string;
  order_cloud_id?: string;
  bill_number: string;
  subtotal: number;
  discount_total?: number;
  tax_total?: number;
  tip_amount?: number;
  grand_total: number;
  notes?: string;
}
