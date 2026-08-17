export interface CashReportBreakdownItem {
  amount: number;
  tips: number;
  count: number;
}

export interface CashReportBreakdown {
  cash: CashReportBreakdownItem;
  card: CashReportBreakdownItem;
  transfer: CashReportBreakdownItem;
  gift_card: CashReportBreakdownItem;
}

export interface CashReportSession {
  uuid: string;
  session_number: string;
  user_name: string | null;
  register_name: string | null;
  opened_at: string;
  closed_at: string | null;
  opening_amount: number;
  closing_amount: number | null;
}

export interface CashReportMovement {
  type: string;
  amount: number;
  reason: string | null;
  created_at: string;
}

export interface CashReportCount {
  type: string;
  counted_amount: number;
  difference: number;
  has_discrepancy: boolean;
  created_at: string;
  notes: string | null;
}

export interface CashReport {
  type: "X" | "Z";
  generated_at: string;
  session: CashReportSession;
  sales: {
    breakdown: CashReportBreakdown;
    total_sales: number;
    total_tips: number;
    total_transactions: number;
    total_grand: number;
  };
  cash: {
    opening: number;
    sales: number;
    tips: number;
    withdrawals: number;
    deposits: number;
    expected: number;
    counted: number | null;
    difference: number | null;
  };
  movements: CashReportMovement[];
  counts: CashReportCount[];
}

export interface SessionHistoryItem {
  uuid: string;
  session_number: string;
  user_name: string | null;
  register_name: string | null;
  opened_at: string;
  closed_at: string | null;
  opening_amount: number;
  closing_amount: number | null;
  expected_amount: number;
  difference: number;
  has_discrepancy: boolean;
  payments_count: number;
  counts_count: number;
  movements_count: number;
}

/** Denominaciones de billetes/monedas chilenas */
export const CLP_DENOMINATIONS = [
  { value: 20000, label: "$20.000", type: "bill" },
  { value: 10000, label: "$10.000", type: "bill" },
  { value: 5000, label: "$5.000", type: "bill" },
  { value: 2000, label: "$2.000", type: "bill" },
  { value: 1000, label: "$1.000", type: "bill" },
  { value: 500, label: "$500", type: "coin" },
  { value: 100, label: "$100", type: "coin" },
  { value: 50, label: "$50", type: "coin" },
  { value: 10, label: "$10", type: "coin" },
] as const;

export interface DenominationCount {
  value: number;
  label: string;
  type: "bill" | "coin";
  quantity: number;
  subtotal: number;
}
