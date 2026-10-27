// src/types/floorPlan.ts

// ============================================================================
// TIPOS LEGACY Y CATÁLOGO (Para compatibilidad con componentes existentes)
// ============================================================================
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

export type TableStatus = "available" | "occupied" | "billing" | "maintenance";
export type TableShape = "square" | "round" | "rectangle";
export type TableRotation = 0 | 90 | 180 | 270;

// ============================================================================
// 4. NUEVOS TIPOS DE DOMINIO (Hoja de Ruta)
// ============================================================================
export type ObjectType = 
  | 'table' | 'chair' | 'wall' | 'door' | 'window' 
  | 'column' | 'plant' | 'counter' | 'booth' | 'bench' 
  | 'sofa' | 'service_station' | 'partition' | 'other';

export type ZoneType = 
  | 'dining_room' | 'terrace' | 'bar' | 'private_room' 
  | 'waiting_area' | 'other';

export interface FloorPlanConfig {
  uuid?: string;
  company_id?: number;
  branch_id?: number;
  name?: string;
  width: number;
  height: number;
  grid_size: number;
  unit?: 'cm' | 'px'; // ✅ OPCIONAL para compatibilidad
  version?: number;   // ✅ OPCIONAL para compatibilidad
}

// Alias para compatibilidad con componentes que usan "CanvasConfig"
export type CanvasConfig = FloorPlanConfig;

export interface FloorPlanZone {
  uuid: string;
  floor_plan_uuid?: string;
  name: string;
  code: string;
  type: ZoneType;
  color: string;                      // ✅ AGREGADO: Necesario para la UI
  name_translations?: Record<string, string>; // ✅ AGREGADO: Necesario para la UI
  position_x: number;
  position_y: number;
  width: number;
  height: number;
  rotation: number;
  properties?: Record<string, any>;
  tables_count?: number;
}

// Alias para compatibilidad con componentes que usan "DiningZone"
export type DiningZone = FloorPlanZone;

export interface OperationalTableData {
  uuid: string;
  table_number: string;
  capacity: number;
  status: TableStatus;
  has_active_order: boolean;
  current_order_id: number | null;
}

export interface FloorPlanObject {
  uuid: string;
  floor_plan_uuid?: string;
  zone_uuid: string | null;
  object_type: ObjectType;
  table_uuid: string | null;
  position_x: number;
  position_y: number;
  width: number;
  height: number;
  rotation: number;
  z_index: number;
  properties?: Record<string, any>;
  operational_data?: OperationalTableData | null;
}

export interface FloorPlanResponse {
  plan: FloorPlanConfig;
  zones: FloorPlanZone[];
  objects: FloorPlanObject[];
  available_tables: OperationalTableData[];
  tables?: LegacyTablePosition[];
  decorations?: LegacyDecorationPosition[];
}

export interface SaveFloorPlanPayload {
  plan_version?: number;
  plan?: Partial<FloorPlanConfig>;
  zones?: Omit<FloorPlanZone, 'floor_plan_uuid' | 'tables_count'>[];
  objects?: Omit<FloorPlanObject, 'floor_plan_uuid' | 'operational_data'>[];
  
  // ✅ LEGACY: Parciales para permitir construir el payload gradualmente sin errores TS
  tables?: Partial<LegacyTablePosition>[];
  decorations?: Partial<LegacyDecorationPosition>[];
  canvas?: Partial<FloorPlanConfig>;
}

export interface CreateDiningZonePayload {
  code: string;
  name_translations: Record<string, string>;
  color: string;
  floor_level?: number;
  sort_order?: number;
  position_x?: number;
  position_y?: number;
  width?: number;
  height?: number;
}

export interface UpdateDiningZonePayload {
  name_translations?: Record<string, string>;
  color?: string;
  sort_order?: number;
  is_active?: boolean;
  position_x?: number;
  position_y?: number;
  width?: number;
  height?: number;
  rotation?: number;
}

// ============================================================================
// TIPOS LEGACY DE POSICIÓN (Solo para transición)
// ============================================================================
export interface LegacyTablePosition {
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

export interface LegacyDecorationPosition {
  uuid: string;
  type: FurnitureType | string;
  position_x: number;
  position_y: number;
  rotation: number;
  width: number;
  height: number;
  properties?: Record<string, any>;
}

export type TablePosition = LegacyTablePosition;
export type DecorationPosition = LegacyDecorationPosition;
