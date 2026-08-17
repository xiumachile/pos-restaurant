export type PaymentMethodType = "cash" | "card" | "transfer" | "gift_card" | "other";

export type PaymentStatus = "pending" | "completed" | "refunded" | "failed";

export type CashSessionStatus = "open" | "closed" | "suspended";

export interface PaymentMethod {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number | null;
  code: string;
  name_translations: Record<string, string>;
  type: PaymentMethodType;
  icon: string | null;
  max_amount: string | null;
  requires_reference: boolean;
  is_active: boolean;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface PaymentBreakdownItem {
  amount: number;
  tips: number;
  count: number;
}

export interface PaymentBreakdown {
  cash: PaymentBreakdownItem;
  card: PaymentBreakdownItem;
  transfer: PaymentBreakdownItem;
  gift_card: PaymentBreakdownItem;
}

export interface CashSession {
  uuid: string;
  session_number: string;
  status: CashSessionStatus;
  opening_amount: number;
  closing_amount: number | null;
  expected_amount: number;
  difference: number | null;
  opening_notes: string | null;
  closing_notes: string | null;
  opened_at: string;
  closed_at: string | null;
  user?: {
    uuid: string;
    name: string;
  };
  register?: {
    uuid: string;
    name: string;
    code: string;
  } | null;
  breakdown?: PaymentBreakdown;
  total_sales_amount?: number;
  total_tips?: number;
  total_transactions?: number;
  total_cash_expected?: number;
  total_grand_expected?: number;
  hours_open?: number;
  withdrawals_total?: number;
  deposits_total?: number;
  movements_count?: number;
}

export interface CashierDashboard {
  current_session: CashSession | null;
  registers: Array<{
    uuid: string;
    name: string;
    code: string;
    is_available: boolean;
    is_busy: boolean;
    current_session_uuid: string | null;
  }>;
  statistics_today: {
    sessions_today: number;
    sessions_open: number;
    sessions_closed: number;
    counts_today: number;
    discrepant_counts_today: number;
    movements_today: number;
    total_withdrawals_today: number;
    total_deposits_today: number;
    payments_today?: {
      cash: number;
      card: number;
      transfer: number;
      gift_card: number;
      tips: number;
      total: number;
    };
  };
}

export interface ServedOrder {
  uuid: string;
  order_number: string;
  type: string;
  status: "served";
  table?: {
    uuid: string;
    table_number: string;
    area_code: string;
  } | null;
  waiter?: {
    uuid: string;
    name: string;
  } | null;
  items: Array<{
    uuid: string;
    name: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
  }>;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  served_at: string;
  created_at: string;
}

export interface CreatePaymentPayload {
  order_uuid: string;
  payment_method_uuid: string;
  bill_uuid?: string;
  amount: number;
  tip_amount?: number;
  reference_code?: string;
  notes?: string;
  idempotency_key: string;
}

export interface SessionPayment {
  uuid: string;
  payment_number: string;
  method_code: string;
  amount: number;
  tip_amount: number;
  total_amount: number;
  reference_code: string | null;
  paid_at: string;
  order_number: string | null;
  table_number: string | null;
  cashier_name: string | null;
}

export interface SessionPaymentsData {
  session: {
    uuid: string;
    session_number: string;
    opening_amount: number;
    opened_at: string;
    user_name: string | null;
  } | null;
  payments: SessionPayment[];
  summary: {
    by_method: PaymentBreakdown;
    total_sales: number;
    total_tips: number;
    total_grand: number;
    total_cash_expected: number;
    transactions_count: number;
  } | null;
}
