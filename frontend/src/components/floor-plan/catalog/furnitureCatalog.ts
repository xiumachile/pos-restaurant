// src/components/floor-plan/catalog/furnitureCatalog.ts

export type ObjectType =
  | 'table_round'
  | 'table_square'
  | 'table_rectangle'
  | 'chair'
  | 'stool'
  | 'booth'
  | 'sofa'
  | 'wall'
  | 'column'
  | 'door_single'
  | 'door_double'
  | 'window'
  | 'plant'
  | 'counter'
  | 'service_station';

export type Category = 'mesas' | 'asientos' | 'arquitectura' | 'servicio' | 'decoracion';

export interface SeatLayout {
  top: number;
  bottom: number;
  left: number;
  right: number;
}

export interface FurnitureDefinition {
  type: ObjectType;
  name: string;
  category: Category;
  defaultWidth: number;
  defaultHeight: number;
  defaultCapacity: number;
  defaultShape: 'round' | 'square' | 'rectangle';
  editableProperties: string[];
}

export function calculateSeatLayout(capacity: number, shape: 'round' | 'square' | 'rectangle'): SeatLayout {
  if (shape === 'round') {
    return { top: capacity, bottom: 0, left: 0, right: 0 };
  }
  
  if (capacity <= 2) return { top: 1, bottom: 1, left: 0, right: 0 };
  if (capacity === 3) return { top: 1, bottom: 1, left: 1, right: 0 };
  if (capacity === 4) return { top: 1, bottom: 1, left: 1, right: 1 };
  if (capacity === 5) return { top: 2, bottom: 2, left: 1, right: 0 };
  if (capacity === 6) return { top: 2, bottom: 2, left: 1, right: 1 };
  if (capacity === 8) return { top: 3, bottom: 3, left: 1, right: 1 };
  
  const perSide = Math.floor(capacity / 4);
  const remainder = capacity % 4;
  return {
    top: perSide + (remainder > 0 ? 1 : 0),
    bottom: perSide + (remainder > 1 ? 1 : 0),
    left: perSide + (remainder > 2 ? 1 : 0),
    right: perSide,
  };
}

export const FURNITURE_CATALOG: Record<ObjectType, FurnitureDefinition> = {
  table_round: {
    type: 'table_round',
    name: 'Mesa Redonda',
    category: 'mesas',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultCapacity: 4,
    defaultShape: 'round',
    editableProperties: ['capacity', 'width', 'height'],
  },
  table_square: {
    type: 'table_square',
    name: 'Mesa Cuadrada',
    category: 'mesas',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultCapacity: 4,
    defaultShape: 'square',
    editableProperties: ['capacity', 'width', 'height'],
  },
  table_rectangle: {
    type: 'table_rectangle',
    name: 'Mesa Rectangular',
    category: 'mesas',
    defaultWidth: 120,
    defaultHeight: 80,
    defaultCapacity: 6,
    defaultShape: 'rectangle',
    editableProperties: ['capacity', 'width', 'height'],
  },
  chair: {
    type: 'chair',
    name: 'Silla',
    category: 'asientos',
    defaultWidth: 40,
    defaultHeight: 45,
    defaultCapacity: 1,
    defaultShape: 'square',
    editableProperties: ['rotation'],
  },
  stool: {
    type: 'stool',
    name: 'Taburete',
    category: 'asientos',
    defaultWidth: 35,
    defaultHeight: 35,
    defaultCapacity: 1,
    defaultShape: 'round',
    editableProperties: ['rotation'],
  },
  booth: {
    type: 'booth',
    name: 'Booth / Banqueta',
    category: 'asientos',
    defaultWidth: 140,
    defaultHeight: 100,
    defaultCapacity: 4,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'height'],
  },
  sofa: {
    type: 'sofa',
    name: 'Sofá',
    category: 'asientos',
    defaultWidth: 160,
    defaultHeight: 70,
    defaultCapacity: 3,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'seats'],
  },
  wall: {
    type: 'wall',
    name: 'Muro',
    category: 'arquitectura',
    defaultWidth: 200,
    defaultHeight: 16,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'thickness'],
  },
  column: {
    type: 'column',
    name: 'Columna',
    category: 'arquitectura',
    defaultWidth: 50,
    defaultHeight: 50,
    defaultCapacity: 0,
    defaultShape: 'square',
    editableProperties: ['width', 'height'],
  },
  door_single: {
    type: 'door_single',
    name: 'Puerta Simple',
    category: 'arquitectura',
    defaultWidth: 80,
    defaultHeight: 16,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'swing_direction'],
  },
  door_double: {
    type: 'door_double',
    name: 'Puerta Doble',
    category: 'arquitectura',
    defaultWidth: 160,
    defaultHeight: 16,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width'],
  },
  window: {
    type: 'window',
    name: 'Ventana',
    category: 'arquitectura',
    defaultWidth: 120,
    defaultHeight: 16,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width'],
  },
  counter: {
    type: 'counter',
    name: 'Barra / Mostrador',
    category: 'servicio',
    defaultWidth: 180,
    defaultHeight: 60,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'height'],
  },
  service_station: {
    type: 'service_station',
    name: 'Estación de Garzón',
    category: 'servicio',
    defaultWidth: 80,
    defaultHeight: 60,
    defaultCapacity: 0,
    defaultShape: 'rectangle',
    editableProperties: ['width', 'height'],
  },
  plant: {
    type: 'plant',
    name: 'Planta Decorativa',
    category: 'decoracion',
    defaultWidth: 60,
    defaultHeight: 60,
    defaultCapacity: 0,
    defaultShape: 'round',
    editableProperties: ['diameter'],
  },
};
