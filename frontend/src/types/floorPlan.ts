/**
 * Tipos para el Floor Plan (Fase 4.1)
 * Representan el estado del layout del restaurante.
 */

export type TableShape = "square" | "round" | "rectangle";
export type TableRotation = 0 | 90 | 180 | 270;
export type TableStatus = "available" | "occupied" | "billing" | "maintenance";

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
}

export interface FloorPlan {
  zones: DiningZone[];
  tables: TablePosition[];
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

export interface SaveFloorPlanPayload {
  tables: TablePositionUpdate[];
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
