import { z } from 'zod';

// ============================================
// VALIDADORES BÁSICOS
// ============================================

export const FloorPlanStatusSchema = z.enum(['draft', 'published', 'archived']);

export const TableShapeSchema = z.enum(['round', 'square', 'rectangle', 'oval']);

export const ChairPositionSchema = z.enum([
  'top',
  'right',
  'bottom',
  'left',
  'top-right',
  'top-left',
  'bottom-right',
  'bottom-left',
]);

export const FloorPlanObjectTypeSchema = z.enum([
  'table',
  'chair',
  'plant',
  'wall',
  'door',
  'window',
  'column',
  'bar',
  'decoration',
  'furniture',
  'separator',
  'service',
  'infrastructure',
  'custom',
]);

// ============================================
// VALIDADORES DE PROPIEDADES
// ============================================

export const ChairConfigSchema = z.object({
  position: ChairPositionSchema,
  customRotation: z.number().min(0).max(360).optional(),
});

export const TablePropertiesSchema = z.object({
  shape: TableShapeSchema,
  capacity: z.number().int().min(1).max(20),
  chairs: z.array(ChairConfigSchema),
  color: z.string().regex(/^#[0-9a-fA-F]{6}$/, 'Color debe ser hex válido'),
  label: z.string().min(1).max(20),
  reference: z.string().max(100).optional(),
});

export const PlantPropertiesSchema = z.object({
  variant: z.enum(['small_indoor', 'large_indoor', 'tree', 'pot']),
});

export const WallPropertiesSchema = z.object({
  thickness: z.number().int().min(1).max(50),
});

export const DoorPropertiesSchema = z.object({
  type: z.enum(['single', 'double', 'sliding']),
  opensInward: z.boolean(),
});

// ============================================
// VALIDADORES DE ENTIDADES
// ============================================

export const FloorPlanBackgroundSchema = z.object({
  type: z.enum(['color', 'image', 'none']),
  color: z.string().optional(),
  imageUrl: z.string().url().optional(),
  opacity: z.number().min(0).max(1).optional(),
});

export const FloorPlanSettingsSchema = z.object({
  gridSize: z.number().int().min(5).max(100),
  snapToGrid: z.boolean(),
  showGrid: z.boolean(),
});

export const FloorPlanSchema = z.object({
  id: z.number().int().positive(),
  uuid: z.string().uuid(),
  company_id: z.number().int().positive(),
  branch_id: z.number().int().positive(),
  name: z.string().min(1).max(100),
  slug: z.string().max(100).nullable(),
  width: z.number().int().min(100).max(5000),
  height: z.number().int().min(100).max(5000),
  scale: z.number().min(0.1).max(10),
  background: FloorPlanBackgroundSchema.nullable(),
  settings: FloorPlanSettingsSchema.nullable(),
  version: z.number().int().min(1),
  status: FloorPlanStatusSchema,
  published_at: z.string().datetime().nullable(),
  published_by: z.number().int().positive().nullable(),
  created_at: z.string().datetime(),
  updated_at: z.string().datetime(),
});

export const FloorPlanObjectSchema = z.object({
  id: z.number().int().positive(),
  uuid: z.string().uuid(),
  floor_plan_id: z.number().int().positive(),
  object_type: FloorPlanObjectTypeSchema,
  object_key: z.string().uuid().nullable(),
  x: z.number().int(),
  y: z.number().int(),
  width: z.number().int().positive().nullable(),
  height: z.number().int().positive().nullable(),
  rotation: z.number().int().min(0).max(360),
  z_index: z.number().int(),
  properties: z.any(), // Validación específica por tipo se hace en runtime
  created_at: z.string().datetime(),
  updated_at: z.string().datetime(),
});

export const FloorPlanWithObjectsSchema = FloorPlanSchema.extend({
  objects: z.array(FloorPlanObjectSchema),
});

// ============================================
// VALIDADORES DE ACCIONES
// ============================================

export const CreateFloorPlanSchema = z.object({
  name: z.string().min(1).max(100),
  slug: z.string().max(100).optional(),
  width: z.number().int().min(100).max(5000).default(1200),
  height: z.number().int().min(100).max(5000).default(1800),
  scale: z.number().min(0.1).max(10).default(100),
});

export const UpdateFloorPlanSchema = CreateFloorPlanSchema.partial();

export const SaveFloorPlanPayloadSchema = z.object({
  name: z.string().min(1).max(100).optional(),
  width: z.number().int().min(100).max(5000).optional(),
  height: z.number().int().min(100).max(5000).optional(),
  scale: z.number().min(0.1).max(10).optional(),
  background: FloorPlanBackgroundSchema.optional(),
  settings: FloorPlanSettingsSchema.optional(),
  objects: z.array(FloorPlanObjectSchema).optional(),
});

// ============================================
// TIPOS INFERIDOS (solo para uso interno del schema)
// Los tipos públicos están en floorPlan.types.ts (fuente única de verdad)
// ============================================

// Inferir tipos desde schemas para validación en runtime
export type FloorPlanSchemaType = z.infer<typeof FloorPlanSchema>;
export type FloorPlanObjectSchemaType = z.infer<typeof FloorPlanObjectSchema>;
export type FloorPlanWithObjectsSchemaType = z.infer<typeof FloorPlanWithObjectsSchema>;
export type CreateFloorPlanSchemaType = z.infer<typeof CreateFloorPlanSchema>;
export type UpdateFloorPlanSchemaType = z.infer<typeof UpdateFloorPlanSchema>;
export type SaveFloorPlanPayloadSchemaType = z.infer<typeof SaveFloorPlanPayloadSchema>;
