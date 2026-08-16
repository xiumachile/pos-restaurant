export type OrderPriority = "normal" | "rush" | "vip";

export type KitchenOrderStatus = "confirmed" | "preparing" | "ready" | "served";

export interface KitchenOrderItem {
  uuid: string;
  name: string;
  quantity: number;
  notes: string | null;
  modifiers: Array<{
    type: "substitution" | "addition";
    reason: string;
  }>;
}

export interface KitchenOrder {
  uuid: string;
  order_number: string;
  type: string;
  status: KitchenOrderStatus;
  priority: OrderPriority;
  table_number: string | null;
  table_uuid?: string | null;
  area_code: string | null;
  waiter_name: string | null;
  items: KitchenOrderItem[];
  items_count: number;
  notes: string | null;
  confirmed_at: string;
  waiting_minutes: number;
}

export interface KitchenZone {
  zone: string;
  orders: KitchenOrder[];
  count: number;
}

export interface KitchenStats {
  confirmed: number;
  preparing: number;
  ready: number;
  total_active: number;
  avg_preparation_minutes: number;
  orders_last_hour: number;
}

/**
 * Obtiene el color de urgencia basado en tiempo de espera.
 */
export function getUrgencyColor(waitingMinutes: number): {
  bg: string;
  text: string;
  border: string;
  pulse: boolean;
} {
  if (waitingMinutes >= 30) {
    return {
      bg: "bg-red-900/40",
      text: "text-red-300",
      border: "border-red-500",
      pulse: true,
    };
  }
  if (waitingMinutes >= 15) {
    return {
      bg: "bg-orange-900/30",
      text: "text-orange-300",
      border: "border-orange-500",
      pulse: false,
    };
  }
  return {
    bg: "bg-slate-800",
    text: "text-slate-300",
    border: "border-slate-700",
    pulse: false,
  };
}

/**
 * Formatea minutos a formato legible.
 */
export function formatWaitingTime(minutes: number): string {
  if (minutes < 1) return "< 1 min";
  if (minutes < 60) return `${Math.floor(minutes)} min`;
  const hours = Math.floor(minutes / 60);
  const mins = Math.floor(minutes % 60);
  return `${hours}h ${mins}m`;
}
