export type TableShape = "square" | "round" | "rectangle";
export type TableRotation = 0 | 90 | 180 | 270;
export type TableStatus = "available" | "occupied" | "billing" | "maintenance";

export type FurnitureType = 
  | "table_square_2"
  | "table_square_4"
  | "table_round_4"
  | "table_round_6"
  | "table_rectangle_6"
  | "table_rectangle_8"
  | "bar_counter"
  | "booth"
  | "wall"
  | "pillar"
  | "plant"
  | "service_station";

export interface DiningZone {
  uuid: string;
  code: string;
  name_translations: Record<string, string>;
  name: string;
  color: string;
  floor_level: number;
  sort_order: number;
  is_active: boolean;
  tables_count?: number;
  created_at?: string;
}

export interface TablePosition {
  uuid: string;
  table_number: string;
  zone_id: number | null;
  zone_uuid: string | null;
  capacity: number;
  status: TableStatus;
  has_active_order: boolean;
  current_order_id: number | null;
  position_x: number;
  position_y: number;
  rotation: TableRotation;
  shape: TableShape;
  width: number | null;
  height: number | null;
  furniture_type?: FurnitureType;
}

export interface DecorationPosition {
  uuid: string;
  type: FurnitureType;
  position_x: number;
  position_y: number;
  rotation: number;
  width: number;
  height: number;
}

export interface FloorPlan {
  zones: DiningZone[];
  tables: TablePosition[];
  decorations?: DecorationPosition[];
}

export interface TablePositionUpdate {
  uuid: string;
  position_x: number;
  position_y: number;
  rotation: TableRotation;
  shape: TableShape;
  width: number | null;
  height: number | null;
}

export interface DecorationPositionUpdate {
  uuid: string;
  type: FurnitureType;
  position_x: number;
  position_y: number;
  rotation: number;
  width: number;
  height: number;
}

export interface SaveFloorPlanPayload {
  tables: TablePositionUpdate[];
  decorations?: DecorationPositionUpdate[];
}

export interface CreateDiningZonePayload {
  code: string;
  name_translations: Record<string, string>;
  color: string;
  floor_level?: number;
  sort_order?: number;
}

export interface UpdateDiningZonePayload {
  name_translations?: Record<string, string>;
  color?: string;
  sort_order?: number;
  is_active?: boolean;
}
