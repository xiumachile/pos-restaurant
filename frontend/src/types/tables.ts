export type TableStatus =
  | "available"
  | "occupied"
  | "reserved"
  | "cleaning"
  | "maintenance";

export interface TableArea {
  id: number;
  uuid: string;
  name: string;
  description?: string;
  is_active: boolean;
}

export interface RestaurantTable {
  id: number;
  uuid: string;
  number: string;
  name?: string;
  capacity: number;
  status: TableStatus;
  area_id: number;
  area?: TableArea;
  current_order_id?: number;
  current_order_total?: number;
  updated_at: string;
}

export const TABLE_STATUS_LABELS: Record<TableStatus, string> = {
  available: "Disponible",
  occupied: "Ocupada",
  reserved: "Reservada",
  cleaning: "Limpieza",
  maintenance: "Mantenimiento",
};

export const TABLE_STATUS_COLORS: Record<TableStatus, string> = {
  available: "bg-green-500",
  occupied: "bg-red-500",
  reserved: "bg-yellow-500",
  cleaning: "bg-blue-500",
  maintenance: "bg-slate-500",
};
