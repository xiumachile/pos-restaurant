import type { CatalogItem } from '@/types/floor-plan/floorPlan.types';

/**
 * Catálogo de elementos decorativos y arquitectónicos (Sección 3.2)
 * La decoración es referencia espacial, no solo estética.
 */

export const decorationsCatalog: CatalogItem[] = [
  // VEGETACIÓN
  {
    type: 'plant',
    subtype: 'small-plant',
    label: 'Planta pequeña',
    icon: '🌱',
    defaultWidth: 40,
    defaultHeight: 40,
    defaultProperties: { variant: 'small', color: '#22c55e' },
  },
  {
    type: 'plant',
    subtype: 'large-plant',
    label: 'Planta grande',
    icon: '🌿',
    defaultWidth: 70,
    defaultHeight: 70,
    defaultProperties: { variant: 'large', color: '#16a34a' },
  },
  {
    type: 'plant',
    subtype: 'tree',
    label: 'Árbol interior',
    icon: '🌳',
    defaultWidth: 90,
    defaultHeight: 90,
    defaultProperties: { variant: 'tree', color: '#15803d' },
  },
  {
    type: 'plant',
    subtype: 'pot',
    label: 'Macetero',
    icon: '🪴',
    defaultWidth: 50,
    defaultHeight: 50,
    defaultProperties: { variant: 'pot', color: '#a16207' },
  },

  // DECORACIÓN
  {
    type: 'decoration',
    subtype: 'painting',
    label: 'Cuadro',
    icon: '🖼️',
    defaultWidth: 60,
    defaultHeight: 40,
    defaultProperties: { variant: 'painting', color: '#854d0e' },
  },
  {
    type: 'decoration',
    subtype: 'mirror',
    label: 'Espejo',
    icon: '🪞',
    defaultWidth: 60,
    defaultHeight: 40,
    defaultProperties: { variant: 'mirror', color: '#e0e7ff' },
  },
  {
    type: 'decoration',
    subtype: 'lamp',
    label: 'Lámpara',
    icon: '💡',
    defaultWidth: 40,
    defaultHeight: 40,
    defaultProperties: { variant: 'lamp', color: '#fbbf24' },
  },
  {
    type: 'decoration',
    subtype: 'fountain',
    label: 'Fuente',
    icon: '⛲',
    defaultWidth: 80,
    defaultHeight: 80,
    defaultProperties: { variant: 'fountain', color: '#3b82f6' },
  },

  // MOBILIARIO
  {
    type: 'furniture',
    subtype: 'sofa',
    label: 'Sofá',
    icon: '🛋️',
    defaultWidth: 120,
    defaultHeight: 60,
    defaultProperties: { variant: 'sofa', color: '#7c3aed' },
  },
  {
    type: 'furniture',
    subtype: 'armchair',
    label: 'Sillón',
    icon: '💺',
    defaultWidth: 70,
    defaultHeight: 70,
    defaultProperties: { variant: 'armchair', color: '#7c3aed' },
  },
  {
    type: 'furniture',
    subtype: 'bench',
    label: 'Banca',
    icon: '🪑',
    defaultWidth: 100,
    defaultHeight: 40,
    defaultProperties: { variant: 'bench', color: '#78350f' },
  },
  {
    type: 'furniture',
    subtype: 'stool',
    label: 'Taburete',
    icon: '🪑',
    defaultWidth: 35,
    defaultHeight: 35,
    defaultProperties: { variant: 'stool', color: '#78350f' },
  },

  // ARQUITECTURA
  {
    type: 'column',
    subtype: 'column',
    label: 'Columna',
    icon: '🏛️',
    defaultWidth: 50,
    defaultHeight: 50,
    defaultProperties: { variant: 'column', color: '#6b7280' },
  },
  {
    type: 'wall',
    subtype: 'wall',
    label: 'Muro',
    icon: '🧱',
    defaultWidth: 200,
    defaultHeight: 20,
    defaultProperties: { variant: 'wall', color: '#44403c' },
  },
  {
    type: 'window',
    subtype: 'window',
    label: 'Ventana',
    icon: '🪟',
    defaultWidth: 120,
    defaultHeight: 15,
    defaultProperties: { variant: 'window', color: '#67e8f9' },
  },
  {
    type: 'door',
    subtype: 'door',
    label: 'Puerta',
    icon: '🚪',
    defaultWidth: 80,
    defaultHeight: 15,
    defaultProperties: { variant: 'door', color: '#a16207' },
  },

  // SEPARACIÓN
  {
    type: 'separator',
    subtype: 'screen',
    label: 'Biombo',
    icon: '🎭',
    defaultWidth: 150,
    defaultHeight: 15,
    defaultProperties: { variant: 'screen', color: '#d97706' },
  },
  {
    type: 'separator',
    subtype: 'lattice',
    label: 'Celosía',
    icon: '🪜',
    defaultWidth: 150,
    defaultHeight: 15,
    defaultProperties: { variant: 'lattice', color: '#ca8a04' },
  },
  {
    type: 'separator',
    subtype: 'panel',
    label: 'Panel',
    icon: '🪧',
    defaultWidth: 150,
    defaultHeight: 15,
    defaultProperties: { variant: 'panel', color: '#a3a3a3' },
  },

  // SERVICIO
  {
    type: 'service',
    subtype: 'bar',
    label: 'Barra',
    icon: '🍸',
    defaultWidth: 250,
    defaultHeight: 80,
    defaultProperties: { variant: 'bar', color: '#7c2d12' },
  },
  {
    type: 'service',
    subtype: 'waiter-station',
    label: 'Estación garzones',
    icon: '📋',
    defaultWidth: 80,
    defaultHeight: 60,
    defaultProperties: { variant: 'waiter-station', color: '#525252' },
  },
  {
    type: 'service',
    subtype: 'drink-station',
    label: 'Estación bebidas',
    icon: '🍹',
    defaultWidth: 100,
    defaultHeight: 70,
    defaultProperties: { variant: 'drink-station', color: '#be123c' },
  },

  // INFRAESTRUCTURA
  {
    type: 'infrastructure',
    subtype: 'stairs',
    label: 'Escalera',
    icon: '🪜',
    defaultWidth: 120,
    defaultHeight: 80,
    defaultProperties: { variant: 'stairs', color: '#78716c' },
  },
  {
    type: 'infrastructure',
    subtype: 'entrance',
    label: 'Acceso',
    icon: '🚪',
    defaultWidth: 100,
    defaultHeight: 20,
    defaultProperties: { variant: 'entrance', color: '#16a34a' },
  },
  {
    type: 'infrastructure',
    subtype: 'emergency-exit',
    label: 'Salida emergencia',
    icon: '🚨',
    defaultWidth: 80,
    defaultHeight: 20,
    defaultProperties: { variant: 'emergency-exit', color: '#dc2626' },
  },
];

export const decorationsCatalogByCategory = {
  'Vegetación': decorationsCatalog.filter(t => t.type === 'plant'),
  'Decoración': decorationsCatalog.filter(t => t.type === 'decoration'),
  'Mobiliario': decorationsCatalog.filter(t => t.type === 'furniture'),
  'Arquitectura': decorationsCatalog.filter(t =>
    t.type === 'column' || t.type === 'wall' || t.type === 'window' || t.type === 'door'
  ),
  'Separación': decorationsCatalog.filter(t => t.type === 'separator'),
  'Servicio': decorationsCatalog.filter(t => t.type === 'service'),
  'Infraestructura': decorationsCatalog.filter(t => t.type === 'infrastructure'),
};
