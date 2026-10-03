# ADR-022: Consolidación de Inventory en Recipes

**Fecha:** 2026-10-02
**Estado:** ✅ Implementada (Octubre 2026)
**Autores:** Auditoría técnica colaborativa
**Reemplaza:** Estado previo de deuda técnica documentado en `TECHNICAL_INVENTORY_AND_ROADMAP.md` (Sección 3)

## Contexto

El proyecto tenía dos sistemas paralelos para gestionar insumos y recetas que **no se comunicaban entre sí**, generando deuda técnica crítica:

### Sistema 1: `Modules\Inventory`
- Tablas: `inventory_items`, `inventory_stocks`, `stock_movements`
- Filosofía: sistema simple de stock con tipos de movimiento (in/out/adjustment)
- Historial completo de movimientos con balance_after
- **0 filas de datos reales en producción**

### Sistema 2: `Modules\Recipes`
- Tablas: `raw_ingredients`, `raw_ingredient_purchases`, `product_recipes`, `recipe_items`
- Filosofía: BOM (Bill of Materials) con ingredientes elaborados
- **Stock ya por branch** (`raw_ingredients.branch_id` NOT NULL, unique `(branch_id, sku)`)
- Conversión de unidades (`UnitConversionService`)
- Cálculo de Food Cost % por producto
- Merma por ítem (`waste_percentage`)
- Costo promedio ponderado en recompras
- **0 filas de datos reales en producción**
- Frontend integrado (`RecipeSection.tsx`, `useRecipe`, `recipeService`)

### Hallazgos clave de la auditoría (Octubre 2026)

1. **Ambos módulos vacíos en producción** → sin necesidad de migración de datos
2. **Recipes ya maneja stock por branch** → no se necesita tabla separada de stocks
3. **Frontend ya usa Recipes** → menor costo de migración de UI
4. **0 dependencias externas** de Inventory → eliminación limpia

## Decisión

**Consolidar todo en `Modules\Recipes`** (Opción A del roadmap) con una extensión mínima: agregar historial de movimientos.

### Modelo unificado resultante
Modules\Recipes
├── RawIngredient (EXISTENTE)
│ ├── branch_id, sku, name_translations
│ ├── dimension_type, base_unit
│ ├── current_stock_base, minimum_stock_base
│ └── cost_per_base_unit (promedio ponderado)
│
├── RawIngredientPurchase (EXISTENTE)
│
├── RawIngredientMovement (NUEVO) ← único agregado
│ ├── branch_id, raw_ingredient_id
│ ├── type: in_purchase|in_production|out_consumption|out_waste|adjustment
│ ├── quantity_base, balance_after
│ ├── reference_type + reference_id (polymorphic)
│ └── user_id + reason
│
├── ProductRecipe (EXISTENTE)
└── RecipeItem (EXISTENTE)


### Lo que se elimina

- `Modules\Inventory` completo (entidades, servicios, controllers, routes)
- Tablas: `inventory_items`, `inventory_stocks`, `stock_movements`
- Capability `can_manage_inventory` (reemplazada por `can_manage_recipes`)
- Tests legacy de Inventory (4 tests)

### Lo que se conserva de Inventory

- Concepto de **tipos de movimiento** (purchase/production/consumption/waste/adjustment)
- Patrón de **balance_after** en cada movimiento (trazabilidad completa)
- Campos `reference_type` + `reference_id` (polimórfico, vincula con orders/purchases)
- Campos `user_id` + `reason` (auditoría de quién y por qué)

## Consecuencias

### Positivas ✅

1. **Una sola fuente de verdad** para insumos + recetas + stock
2. **Frontend consistente**: todos los componentes usan `recipeService`
3. **Menos código a mantener**: eliminar ~15 archivos del módulo Inventory
4. **Capacidades ricas combinadas**:
   - Stock por branch (de Recipes)
   - Historial de movimientos (de Inventory)
   - Conversión de unidades (de Recipes)
   - BOM + Food Cost % (de Recipes)
5. **Sin migración de datos**: ambos módulos vacíos en producción
6. **ADR-002 respetado**: `RawIngredient` ya tiene `BelongsToTenant`

### Negativas ⚠️

1. **Ruptura de API**: los endpoints `/api/v1/inventory/*` dejan de existir
   - **Mitigación**: no hay consumidores reales (frontend no usa inventory)
2. **Cambio en capability**: `can_manage_inventory` → `can_manage_recipes`
   - **Mitigación**: migración SQL en la migración de drop
3. **Refactor de listener**: `DeductRecipeOnOrderConfirm` debe integrarse con el nuevo MovementService
   - **Mitigación**: tests end-to-end antes del cambio

## Alternativas consideradas

### Opción B: Adaptador entre módulos

Mantener ambos módulos con un adaptador que sincronice stock.

**Rechazada porque:**
- Duplica lógica de negocio
- Requiere sincronización eventual (source of truth ambiguo)
- Mayor complejidad de mantenimiento
- Frontend tendría que decidir qué API usar

### Opción C: Consolidar en Inventory

Mover la lógica de Recipes a Inventory.

**Rechazada porque:**
- Frontend ya usa Recipes (3 componentes)
- Inventory no tiene conceptos clave (BOM, conversión de unidades, Food Cost)
- Mayor costo de migración

## Trazabilidad

- **Roadmap**: Sección 3 de `docs/TECHNICAL_INVENTORY_AND_ROADMAP.md`
- **Rama de trabajo**: `feature/inventory-consolidation`
- **Commits implementados**: 6 commits en `feature/inventory-consolidation`
  - `84d1298` docs(adr)
  - `0070fbb` test(TDD rojo)
  - `efab124` feat(entity)
  - `fc4d1d8` feat(API)
  - `6a65105` feat(integración)
  - `26f67cc` refactor(drop)

## Plan de implementación (8 bloques)

| # | Bloque | Commit type |
|---|---|---|
| 1 | ADR + rama feature (este documento) | docs |
| 2 | TDD tests de movimientos | test |
| 3 | Migración + entity + enum | feat |
| 4 | MovementService unificado | feat |
| 5 | Hook DeductStockOnOrderConfirm | feat |
| 6 | Tests end-to-end de deducción | test |
| 7 | Drop módulo Inventory | refactor |
| 8 | Frontend + docs | feat + docs |

## Criterios de aceptación

- [x] Un solo módulo (`Recipes`) maneja insumos + recetas + stock
- [x] Tabla `raw_ingredient_movements` con historial completo
- [x] Cada pedido confirmado deduce stock vía movimientos
- [x] Tablas `inventory_*` eliminadas
- [x] Capability `can_manage_inventory` → `can_manage_recipes`
- [x] Suite backend: 1052 tests pasando, 3204 assertions
- [ ] Frontend: pantallas de movimientos funcionales (pendiente, Fase 3.2)
- [x] ADR documentado e implementado
