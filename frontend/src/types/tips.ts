export interface TipPolicy {
  uuid: string;
  company_id: number;
  branch_id?: number | null;
  policy_type: "waiter_keeps" | "shared_pool" | "percentage_split";
  policy_label: string;
  card_tip_handling: "cash_payout" | "payroll" | "mixed";
  pool_distribution?: "equal" | "by_hours" | "by_points" | null;
  waiter_percentage?: number | null;
  pool_percentage?: number | null;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
}

export interface UpdateTipPolicyPayload {
  policy_type: string;
  card_tip_handling: string;
  pool_distribution?: string | null;
  waiter_percentage?: number | null;
  pool_percentage?: number | null;
  apply_to: "company" | "branch";
}

export interface TipPayout {
  uuid: string;
  waiter_name: string;
  processed_by_name?: string;
  amount: number;
  payment_method: string;
  policy_type: string;
  notes?: string | null;
  created_at: string;
}

export interface TipSummary {
  policy: {
    type: string;
    label: string;
    card_tip_handling: string;
  };
  tips_received: {
    cash: number;
    card: number;
    transfer: number;
    gift_card: number;
    total: number;
  };
  payouts: {
    total: number;
    cash: number;
    count: number;
  };
  pending: number;
  by_waiter: Array<{
    waiter_id: number;
    waiter_name: string;
    total_amount: number;
    cash_amount: number;
    payout_count: number;
  }>;
}

export interface Waiter {
  id: number;
  name: string;
  role: string;
}

export interface TipByWaiter {
  waiter_id: number;
  waiter_name: string;
  cash: number;
  card: number;
  transfer: number;
  gift_card: number;
  total: number;
  already_paid: number;
  pending: number;
}

export interface TipsByWaiterData {
  policy: {
    type: string;
    label: string;
    card_tip_handling: string;
  };
  by_waiter: TipByWaiter[];
  total_pending: number;
  total_pending_cash: number;
}

export interface GeneratePayoutsResponse {
  policy_used: "cash_payout" | "payroll" | "mixed";
  cash_payouts: {
    count: number;
    total: number;
    items: Array<{
      uuid: string;
      waiter_id: number;
      waiter_name: string;
      amount: number;
      payment_method: string;
      policy: string;
    }>;
  };
  payroll_items: {
    count: number;
    total: number;
    items: Array<{
      uuid: string;
      waiter_id: number;
      waiter_name: string;
      amount: number;
      breakdown: {
        cash?: number;
        card?: number;
        transfer?: number;
        gift_card?: number;
      };
      policy: string;
    }>;
  };
}
