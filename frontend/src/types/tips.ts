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
