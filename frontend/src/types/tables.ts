/**
 * Estados reales del enum TableStatus en el backend.
 */
export type TableStatus = "available" | "occupied" | "billing" | "maintenance";

/**
 * Mesa individual tal como la retorna el backend (TableResource).
 */
export interface RestaurantTable {
  uuid: string;
  table_number: string;
  area_code: string;
  area_name: string;
  capacity: number;
  status: TableStatus;
  has_active_order: boolean;
  current_order_id: number | null;
  created_at: string;
  updated_at: string;
}

/**
 * Área con sus mesas (estructura que retorna TableCollection).
 */
export interface TablesArea {
  area_code: string;
  area_name: string;
  tables: RestaurantTable[];
}

export const TABLE_STATUS_LABELS: Record<TableStatus, string> = {
  available: "Disponible",
  occupied: "Ocupada",
  billing: "Por cobrar",
  maintenance: "Mantenimiento",
};

export const TABLE_STATUS_STYLES: Record<
  TableStatus,
  { bg: string; text: string; border: string; icon: string }
> = {
  available: {
    bg: "bg-green-500/20",
    text: "text-green-400",
    border: "border-green-500/40",
    icon: "✓",
  },
  occupied: {
    bg: "bg-red-500/20",
    text: "text-red-400",
    border: "border-red-500/40",
    icon: "🍽",
  },
  billing: {
    bg: "bg-yellow-500/20",
    text: "text-yellow-400",
    border: "border-yellow-500/40",
    icon: "$",
  },
  maintenance: {
    bg: "bg-slate-500/20",
    text: "text-slate-400",
    border: "border-slate-500/40",
    icon: "🔧",
  },
};

/**
 * Aplana el array de áreas a una lista única de mesas (útil para stats/filtros).
 */
export function flattenAreas(areas: TablesArea[]): RestaurantTable[] {
  return areas.flatMap((area) => area.tables);
}
