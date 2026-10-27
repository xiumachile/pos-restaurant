import type { TablePosition } from "@/types/floorPlan";

export interface LiveOrderInfo {
  table_uuid?: string | null;
  status: string;
  grand_total: number;
  created_at: string;
}

const BILLING_STATUSES = new Set(["billed", "bill_requested", "awaiting_payment", "billing"]);

export function mergeLiveStatus(tables: TablePosition[], orders: LiveOrderInfo[]): TablePosition[] {
  const byTable = new Map<string, LiveOrderInfo>();
  for (const o of orders) {
    if (o.table_uuid) byTable.set(o.table_uuid, o);
  }

  return tables.map((t) => {
    const o = byTable.get(t.uuid);
    if (!o) {
      return { 
        ...t, 
        status: "available" as const, 
        has_active_order: false,
        current_order_id: null, 
        live_order_total: null, 
        live_since: null 
      };
    }
    return {
      ...t,
      status: BILLING_STATUSES.has(o.status) ? ("billing" as const) : ("occupied" as const),
      has_active_order: true,
      current_order_id: 1, // Placeholder si no viene en el tipo, ajusta según tu schema
      live_order_total: o.grand_total,
      live_since: o.created_at,
    };
  });
}

export function formatElapsed(iso: string): string {
  const min = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
  return min < 60 ? `${min}m` : `${Math.floor(min / 60)}h ${min % 60}m`;
}
