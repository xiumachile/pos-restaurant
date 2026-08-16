/**
 * Estados reales del enum TableStatus en el backend.
 */
export type TableStatus = "available" | "occupied" | "billing" | "maintenance";

export interface RestaurantTable {
  id: number;
  uuid: string;
  table_number: string;
  capacity: number;
  status: TableStatus;
  area_code: string;
  area_name_translations?: Record<string, string> | null;
  current_order_id?: number | null;
  company_id: number;
  branch_id: number;
  created_at: string;
  updated_at: string;
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
 * Obtiene el nombre del área con fallbacks seguros.
 * Prioriza es-CL, luego es, luego el area_code.
 */
export function getAreaName(table: RestaurantTable): string {
  const translations = table.area_name_translations;
  if (!translations || typeof translations !== "object") {
    return table.area_code || "Sin área";
  }
  return (
    translations["es-CL"] ||
    translations["es"] ||
    translations["zh-CN"] ||
    Object.values(translations)[0] ||
    table.area_code ||
    "Sin área"
  );
}

/**
 * Agrupa mesas por area_code (defensivo ante datos inconsistentes).
 */
export function groupTablesByArea(
  tables: RestaurantTable[]
): Record<string, { name: string; tables: RestaurantTable[] }> {
  const groups: Record<string, { name: string; tables: RestaurantTable[] }> = {};

  for (const table of tables) {
    const code = table.area_code || "OTHER";
    if (!groups[code]) {
      groups[code] = {
        name: getAreaName(table),
        tables: [],
      };
    }
    groups[code].tables.push(table);
  }

  return groups;
}
