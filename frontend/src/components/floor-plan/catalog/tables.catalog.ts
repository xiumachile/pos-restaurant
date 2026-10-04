import type { CatalogItem, ChairConfig } from '@/types/floor-plan/floorPlan.types';

/**
 * Catálogo de mesas según Sección 3.1 de la especificación.
 * Incluye 6 tipos: redonda, cuadrada, rectangular, ovalada, comunitaria, alta/bar.
 * Las sillas son OBLIGATORIAS como parte de la representación visual.
 */

// Distribuciones predefinidas de sillas por forma y capacidad
const ROUND_CHAIRS: Record<number, ChairConfig[]> = {
  2: [{ position: 'top' }, { position: 'bottom' }],
  3: [
    { position: 'top' },
    { position: 'bottom-right' },
    { position: 'bottom-left' },
  ],
  4: [
    { position: 'top' },
    { position: 'right' },
    { position: 'bottom' },
    { position: 'left' },
  ],
  5: [
    { position: 'top' },
    { position: 'top-right' },
    { position: 'bottom-right' },
    { position: 'bottom-left' },
    { position: 'top-left' },
  ],
  6: [
    { position: 'top' },
    { position: 'top-right' },
    { position: 'bottom-right' },
    { position: 'bottom' },
    { position: 'bottom-left' },
    { position: 'top-left' },
  ],
};

const RECT_CHAIRS: Record<number, ChairConfig[]> = {
  2: [{ position: 'left' }, { position: 'right' }],
  4: [
    { position: 'left' },
    { position: 'left' },
    { position: 'right' },
    { position: 'right' },
  ],
  6: [
    { position: 'left' },
    { position: 'left' },
    { position: 'left' },
    { position: 'right' },
    { position: 'right' },
    { position: 'right' },
  ],
  8: [
    { position: 'top' },
    { position: 'left' },
    { position: 'left' },
    { position: 'left' },
    { position: 'bottom' },
    { position: 'right' },
    { position: 'right' },
    { position: 'right' },
  ],
  10: [
    { position: 'top' },
    { position: 'top' },
    { position: 'left' },
    { position: 'left' },
    { position: 'left' },
    { position: 'bottom' },
    { position: 'bottom' },
    { position: 'right' },
    { position: 'right' },
    { position: 'right' },
  ],
};

export const tablesCatalog: CatalogItem[] = [
  // Redondas
  {
    type: 'table',
    subtype: 'round-2',
    label: 'Redonda 2',
    icon: '⚪',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultProperties: { shape: 'round', capacity: 2, chairs: ROUND_CHAIRS[2], color: '#8B4513', label: '' },
  },
  {
    type: 'table',
    subtype: 'round-4',
    label: 'Redonda 4',
    icon: '⚪',
    defaultWidth: 100,
    defaultHeight: 100,
    defaultProperties: { shape: 'round', capacity: 4, chairs: ROUND_CHAIRS[4], color: '#8B4513', label: '' },
  },
  {
    type: 'table',
    subtype: 'round-6',
    label: 'Redonda 6',
    icon: '⚪',
    defaultWidth: 120,
    defaultHeight: 120,
    defaultProperties: { shape: 'round', capacity: 6, chairs: ROUND_CHAIRS[6], color: '#8B4513', label: '' },
  },
  // Cuadradas
  {
    type: 'table',
    subtype: 'square-2',
    label: 'Cuadrada 2',
    icon: '🟫',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultProperties: { shape: 'square', capacity: 2, chairs: RECT_CHAIRS[2], color: '#A0522D', label: '' },
  },
  {
    type: 'table',
    subtype: 'square-4',
    label: 'Cuadrada 4',
    icon: '🟫',
    defaultWidth: 100,
    defaultHeight: 100,
    defaultProperties: { shape: 'square', capacity: 4, chairs: RECT_CHAIRS[4], color: '#A0522D', label: '' },
  },
  // Rectangulares
  {
    type: 'table',
    subtype: 'rect-4',
    label: 'Rectangular 4',
    icon: '▬',
    defaultWidth: 140,
    defaultHeight: 80,
    defaultProperties: { shape: 'rectangle', capacity: 4, chairs: RECT_CHAIRS[4], color: '#D2691E', label: '' },
  },
  {
    type: 'table',
    subtype: 'rect-6',
    label: 'Rectangular 6',
    icon: '▬',
    defaultWidth: 180,
    defaultHeight: 90,
    defaultProperties: { shape: 'rectangle', capacity: 6, chairs: RECT_CHAIRS[6], color: '#D2691E', label: '' },
  },
  {
    type: 'table',
    subtype: 'rect-8',
    label: 'Rectangular 8',
    icon: '▬',
    defaultWidth: 220,
    defaultHeight: 100,
    defaultProperties: { shape: 'rectangle', capacity: 8, chairs: RECT_CHAIRS[8], color: '#D2691E', label: '' },
  },
  {
    type: 'table',
    subtype: 'rect-10',
    label: 'Rectangular 10',
    icon: '▬',
    defaultWidth: 260,
    defaultHeight: 110,
    defaultProperties: { shape: 'rectangle', capacity: 10, chairs: RECT_CHAIRS[10], color: '#D2691E', label: '' },
  },
  // Ovaladas
  {
    type: 'table',
    subtype: 'oval-4',
    label: 'Ovalada 4',
    icon: '🥚',
    defaultWidth: 140,
    defaultHeight: 90,
    defaultProperties: { shape: 'oval', capacity: 4, chairs: RECT_CHAIRS[4], color: '#BC8F8F', label: '' },
  },
  {
    type: 'table',
    subtype: 'oval-6',
    label: 'Ovalada 6',
    icon: '🥚',
    defaultWidth: 180,
    defaultHeight: 100,
    defaultProperties: { shape: 'oval', capacity: 6, chairs: RECT_CHAIRS[6], color: '#BC8F8F', label: '' },
  },
  // Comunitarias (8+)
  {
    type: 'table',
    subtype: 'community-8',
    label: 'Comunitaria 8',
    icon: '🍽️',
    defaultWidth: 280,
    defaultHeight: 120,
    defaultProperties: { shape: 'rectangle', capacity: 8, chairs: RECT_CHAIRS[8], color: '#654321', label: '' },
  },
  {
    type: 'table',
    subtype: 'community-10',
    label: 'Comunitaria 10',
    icon: '🍽️',
    defaultWidth: 320,
    defaultHeight: 130,
    defaultProperties: { shape: 'rectangle', capacity: 10, chairs: RECT_CHAIRS[10], color: '#654321', label: '' },
  },
  // Altas/Bar
  {
    type: 'table',
    subtype: 'bar-2',
    label: 'Bar 2',
    icon: '🍺',
    defaultWidth: 60,
    defaultHeight: 60,
    defaultProperties: { shape: 'round', capacity: 2, chairs: ROUND_CHAIRS[2], color: '#2F4F4F', label: '' },
  },
  {
    type: 'table',
    subtype: 'bar-3',
    label: 'Bar 3',
    icon: '🍺',
    defaultWidth: 70,
    defaultHeight: 70,
    defaultProperties: { shape: 'round', capacity: 3, chairs: ROUND_CHAIRS[3], color: '#2F4F4F', label: '' },
  },
  {
    type: 'table',
    subtype: 'bar-4',
    label: 'Bar 4',
    icon: '🍺',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultProperties: { shape: 'round', capacity: 4, chairs: ROUND_CHAIRS[4], color: '#2F4F4F', label: '' },
  },
];

export const tablesCatalogByCategory = {
  'Redondas': tablesCatalog.filter(t => t.subtype?.startsWith('round')),
  'Cuadradas': tablesCatalog.filter(t => t.subtype?.startsWith('square')),
  'Rectangulares': tablesCatalog.filter(t => t.subtype?.startsWith('rect')),
  'Ovaladas': tablesCatalog.filter(t => t.subtype?.startsWith('oval')),
  'Comunitarias': tablesCatalog.filter(t => t.subtype?.startsWith('community')),
  'Altas/Bar': tablesCatalog.filter(t => t.subtype?.startsWith('bar')),
};
