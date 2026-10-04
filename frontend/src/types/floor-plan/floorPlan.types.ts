/**
 * Tipos TypeScript para el diseñador de plano de mesas
 * Basado en Sección 8 de la especificación técnica
 */

// ============================================
// 8.1 ENTIDAD FLOOR PLAN
// ============================================

export type FloorPlanStatus = 'draft' | 'published' | 'archived';

export interface FloorPlanBackground {
  type: 'color' | 'image' | 'none';
  color?: string;
  imageUrl?: string;
  opacity?: number;
}

export interface FloorPlanSettings {
  gridSize: number;
  snapToGrid: boolean;
  showGrid: boolean;
}

export interface FloorPlan {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number;
  name: string;
  slug: string | null;
  width: number;
  height: number;
  scale: number;
  background: FloorPlanBackground | null;
  settings: FloorPlanSettings | null;
  version: number;
  status: FloorPlanStatus;
  published_at: string | null;
  published_by: number | null;
  created_at: string;
  updated_at: string;
}

// ============================================
// 8.2 ENTIDAD FLOOR PLAN OBJECT
// ============================================

export type FloorPlanObjectType =
  | 'table'
  | 'chair'
  | 'plant'
  | 'wall'
  | 'door'
  | 'window'
  | 'column'
  | 'bar'
  | 'decoration'
  | 'furniture'
  | 'separator'
  | 'service'
  | 'infrastructure'
  | 'custom';

export type TableShape = 'round' | 'square' | 'rectangle' | 'oval';

export type ChairPosition = 'top' | 'right' | 'bottom' | 'left' | 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';

export interface ChairConfig {
  position: ChairPosition;
  customRotation?: number;
}

export interface TableProperties {
  shape: TableShape;
  capacity: number;
  chairs: ChairConfig[];
  color: string;
  label: string;
  reference?: string;
}

export interface PlantProperties {
  variant: 'small_indoor' | 'large_indoor' | 'tree' | 'pot';
}

export interface WallProperties {
  thickness: number;
}

export interface DoorProperties {
  type: 'single' | 'double' | 'sliding';
  opensInward: boolean;
}

export type FloorPlanObjectProperties =
  | TableProperties
  | PlantProperties
  | WallProperties
  | DoorProperties
  | Record<string, any>;

export interface FloorPlanObject {
  id: number;
  uuid: string;
  floor_plan_id: number;
  object_type: FloorPlanObjectType;
  object_key: string | null; // UUID de mesa operativa (si aplica)
  x: number;
  y: number;
  width: number | null;
  height: number | null;
  rotation: number;
  z_index: number;
  properties: FloorPlanObjectProperties;
  created_at: string;
  updated_at: string;
}

// ============================================
// TIPOS DE EDITOR
// ============================================

export interface EditorState {
  selectedObjectIds: string[];
  isDragging: boolean;
  isResizing: boolean;
  isRotating: boolean;
  mode: 'select' | 'pan' | 'create';
  creatingObjectType: FloorPlanObjectType | null;
}

export interface CanvasViewport {
  x: number;
  y: number;
  scale: number;
}

// ============================================
// TIPOS DE CATÁLOGO
// ============================================

export interface CatalogItem {
  type: FloorPlanObjectType;
  subtype?: string;
  label: string;
  icon: string;
  defaultProperties: Partial<FloorPlanObjectProperties>;
  defaultWidth: number;
  defaultHeight: number;
}

// ============================================
// TIPOS DE HISTORIAL (UNDO/REDO)
// ============================================

export type HistoryAction =
  | { type: 'CREATE_OBJECT'; object: FloorPlanObject }
  | { type: 'UPDATE_OBJECT'; objectId: string; changes: Partial<FloorPlanObject> }
  | { type: 'DELETE_OBJECT'; objectId: string; object: FloorPlanObject }
  | { type: 'MOVE_OBJECTS'; objectIds: string[]; positions: { [id: string]: { x: number; y: number } } };

export interface HistoryState {
  past: HistoryAction[];
  future: HistoryAction[];
}

// ============================================
// TIPOS DE SINCRONIZACIÓN
// ============================================

export interface FloorPlanWithObjects extends FloorPlan {
  objects: FloorPlanObject[];
}

export interface SaveFloorPlanPayload {
  name?: string;
  width?: number;
  height?: number;
  scale?: number;
  background?: FloorPlanBackground;
  settings?: FloorPlanSettings;
  objects?: FloorPlanObject[];
}

// ============================================
// PAYLOADS DE API (F5/F8)
// ============================================

export interface CreateFloorPlanPayload {
  name: string;
  slug?: string;
  width?: number;
  height?: number;
  scale?: number;
  background?: FloorPlanBackground;
  settings?: FloorPlanSettings;
}

export interface UpdateFloorPlanPayload {
  name?: string;
  slug?: string;
  width?: number;
  height?: number;
  scale?: number;
  background?: FloorPlanBackground;
  settings?: FloorPlanSettings;
  status?: FloorPlanStatus;
  objects?: FloorPlanObject[];
}

// ============================================
// TIPOS PARA VISTA OPERATIVA (F6)
// ============================================

export type OperationalTableStatus = 'available' | 'occupied' | 'reserved' | 'blocked';

export interface OperationalTableState {
  uuid: string;
  table_number: string;
  status: OperationalTableStatus;
  current_order_id: number | null;
  waiter_name?: string | null;
  opened_at?: string | null;
  guest_count?: number | null;
  total_amount?: number | null;
  has_pending_items?: boolean;
  reference?: string | null;
}
