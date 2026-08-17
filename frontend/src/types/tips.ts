export type TipPolicyType = "waiter_keeps" | "shared_pool" | "percentage_split";

export type CardTipHandling = "cash_payout" | "payroll" | "mixed";

export type PoolSplitMethod = "equal" | "by_hours" | "by_points";

export interface TipPolicy {
  uuid: string | null;
  policy_type: TipPolicyType;
  policy_label?: string;
  card_tip_handling: CardTipHandling;
  pool_split_method: PoolSplitMethod;
  waiter_percentage: number;
  pool_percentage: number;
  is_custom: boolean;
  is_active: boolean;
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
  payouts_created: number;
  total_amount: number;
  payouts: Array<{
    uuid: string;
    waiter_name: string;
    amount: number;
    payment_method: string;
  }>;
}
