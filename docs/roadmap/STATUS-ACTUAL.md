# Estado Actual del Sistema

**Fecha:** 26 de agosto de 2026
**Commits totales:** 220
**Estado del repo:** Limpio (post-commit F.2)

---

## 1. Matriz de módulos

### Clasificación de estado

- **READY:** Producción, bien testeado, sin deuda conocida
- **STABLE:** Funcional, tests básicos, deuda menor
- **PARTIAL:** Funcionalidad parcial, falta integración completa
- **EXPERIMENTAL:** Prototipo o en desarrollo activo
- **DEPRECATED:** Obsoleto, plan de eliminación

### Estado por módulo

| Módulo | Status | Tests | Deuda técnica | Prioridad |
|--------|--------|-------|---------------|-----------|
| Identity | STABLE | ✅ | — | — |
| Companies | STABLE | ✅ | `settings` sin usar | F1 |
| Branches | STABLE | ✅ | — | — |
| Catalog | PARTIAL | ⚠️ | Sin `capabilities`, `withoutGlobalScopes` | F1, F5 |
| Orders | PARTIAL | ⚠️ | Sin state machine formal | F2 |
| Tables | PARTIAL | ⚠️ | Sin state machine, DB directo desde Cashier | F0.2, F2 |
| Kitchen | STABLE | ✅ | — | F7 |
| Payments | PARTIAL | ⚠️ | Sin ledger append-only, sin refund/reversal | F2 |
| Cashier | PARTIAL | ⚠️ | `DB::table` cross-module, idempotencia parcial | F0.2, F7 |
| Inventory | PARTIAL | ⚠️ | Sin ledger, paralelo a Recipes | F6 |
| Recipes | PARTIAL | ⚠️ | `deductStock` con decrement directo | F6 |
| Billing | STABLE | ⚠️ | `delete()` físico de bill corrupta | F0.4 |
| Fiscal | EXPERIMENTAL | ❌ | Sin DTE completo | F12 |
| Sync | STABLE | ✅ | Sin LWW formalizado, sin stress test | F3 |
| Printers | PARTIAL | ⚠️ | Backend hace `fsockopen` directo | F0.3 |
| Reports | EXPERIMENTAL | ❌ | Sin desarrollo | F11 |
| Delivery | EXPERIMENTAL | ❌ | Sin desarrollo | F10 |

---

## 2. Trabajo pre-consolidación (Fases A-F.2)

### Estado: APROVECHABLE CON RETROFIT

El trabajo realizado en catálogo/pricing/recetas es funcional y bien construido,
pero fue realizado antes de consolidar el core. Cada pieza debe retrofitearse a
las capacidades configurables en **Fase 1 (Business Profile / Capabilities)**.

#### Fase A: CRUD categorías y productos
- **Commits:** `0d569c3`, `cbb2be3`
- **Archivos:** CategoryController, ProductController, CreateProductRequest
- **Deuda:** `withoutGlobalScopes()` en Product cuando es consultado cross-module
- **Retrofit F1:** Agregar validación de `capabilities.max_category_depth`
- **Retrofit F5:** Separar `Product` de `SellableItem` según Commerce Engine

#### Fase B: Sistema de precios múltiples por canal
- **Commit:** `e6305ad`
- **Archivos:** PriceList, ProductPrice, PriceListController
- **Deuda:** Precios hardcodeados por canal, no consultan `capabilities`
- **Retrofit F5:** Integrar con Price Engine (branch/channel/time-based pricing)

#### Fase C: Cartas/Menús con resolución automática
- **Commit:** `f0b8cc5`
- **Archivos:** Menu, MenuActivation, MenuResolutionService, MenuController
- **Deuda:** `withoutGlobalScopes()` en MenuResolutionService
- **Retrofit F5:** Conectar con `Availability` de Commerce Engine
- **Nota:** El fix de timezone (America/Santiago) es correcto y se mantiene

#### Fase D: Sustituciones de combos
- **Commit:** `c588e7d`
- **Archivos:** MenuItemReplacementRule, ComboReplacementRuleController,
  SetComboItemSubstitutionPolicy
- **Estado:** **PATRÓN A REPLICAR** en toda la plataforma
- **Por qué:** Implementa jerarquía sucursal>empresa, modos configurables,
  sin código hardcodeado
- **Retrofit F5:** Generalizar a "Modifier Engine" (no solo combos)

#### Fase E: Frontend admin unificado
- **Commit:** `55b2f9e`
- **Archivos:** CatalogSettingsPage, 4 tabs (Categories/Products/PriceLists/Menus)
- **Deuda:** No respeta `capabilities` (muestra todo a todos los tenants)
- **Retrofit F1:** Filtrar tabs según `capabilities` de la empresa

#### Fase F.1: Fix modal + precios múltiples
- **Commit:** `59c5dd6`
- **Archivos:** ProductsTab (color-scheme dark), useProductPrices
- **Estado:** Funcional, retrofit junto a Fase B

#### Fase F.2: Recetas + food cost + ingredientes
- **Commit:** `5602cb7`
- **Archivos:** RecipeSection, recipeService, useRecipe
- **Deuda:** `deductStock` usa `decrement()` directo (viola Inventory Ledger)
- **Retrofit F6:** Unificar RawIngredient con InventoryLedger

---

## 3. Deuda técnica priorizada

### 🔴 Crítica (bloquea F1+)

1. **Cashier → Tables por DB directa** (F0.2)
   - Reemplazar `DB::table('restaurant_tables')` por evento `TableReleased`
   - Archivos: `CashierTablesController.php` líneas 255, 408

2. **`withoutGlobalScopes()` cross-module** (F1)
   - Diseñar mecanismo explícito para consultas cross-module legítimas
   - Archivos: RecipeController, ComboReplacementRuleController, MenuResolutionService

3. **Sin CI/CD** (F0.1)
   - Configurar GitHub Actions mínimo: PHPUnit + Vitest en push
   - Protege todo refactor posterior

### 🟡 Alta (bloquea F2+)

4. **Impresión backend hace fsockopen** (F0.3)
   - Verificar que Tauri reclama PrintJobs
   - Documentar que backend NUNCA hace fsockopen en producción

5. **Sin state machines formales** (F2)
   - Order, Table, Payment necesitan enum + transiciones explícitas

6. **Sin Payment Ledger** (F2)
   - Crear PaymentRefund, PaymentReversal, PaymentAllocation
   - Eliminar delete/update sobre pagos

7. **Sin idempotencia generalizada** (F3)
   - Middleware Idempotency-Key para POST críticos

### 🟢 Media (F5+)

8. **Dos sistemas de inventario paralelos** (F6)
   - Unificar RawIngredient e InventoryItem en un solo ledger

9. **Recetas sin ledger** (F6)
   - `deductStock` debe emitir InventoryMovement, no hacer decrement

---

## 4. Trabajo aprovechable al 100%

No se tira nada. Cada pieza construida tiene un destino claro:

| Componente | Destino |
|------------|---------|
| PriceList/ProductPrice | F5 (Price Engine) |
| Menu/MenuActivation | F5 (Commerce Engine - Availability) |
| Sustituciones de combos | F5 (Modifier Engine - patrón a replicar) |
| Recetas/food cost | F6 (Inventory Ledger + Recipes) |
| Frontend admin | F1 (filtrar por capabilities) |
| Sync offline | F3 (validar con stress test) |
| PrintJobs | F0.3 (verificar flujo pull-based) |

---

## 5. Infraestructura subutilizada a activar

| Componente | Estado | Activación en |
|------------|--------|---------------|
| `Company.settings` (JSONB) | 0 usos | F1 (Business Profile) |
| `OrderType.requiresTable()` | Dominio listo, app no lo explota | F2 (Order Core sin mesa) |
| `RawIngredient.cost_per_base_unit` | Funcional, sin ledger | F6 |
| `MenuActivation.priority` | Funcional | F5 (Availability) |
| `PrintJob` pull-based | Infraestructura existe | F0.3 |

---

## 6. Tests existentes

**Total:** 67 tests (sin CI/CD)

- Unit tests de reglas de negocio
- Integration tests de endpoints
- Sin contract tests (OpenAPI)
- Sin E2E de flujos completos
- Sin distributed/offline stress test

**Gap:** Los tests no corren automáticamente. Primera acción de F0.1.

---

## 7. Convenciones arquitectónicas (a formalizar en F0)

### Estructura de módulo

### Reglas de comunicación entre módulos
- ❌ Nunca `DB::table(otro_modulo)` desde un controller
- ❌ Nunca `withoutGlobalScopes()` sin justificación documentada
- ✅ Emitir evento de dominio + listener en módulo consumidor
- ✅ O llamar un caso de uso del módulo destino

### Tenant isolation
- Todo request debe derivar `company_id` y `branch_id` del token, no del payload
- `BelongsToTenant` trait en todas las entidades
- Nunca confiar en datos enviados por frontend

---

## 8. Decisiones pendientes

Las siguientes decisiones arquitectónicas deben resolverse antes de las fases indicadas:

| Decisión | Fecha límite | Fase |
|----------|--------------|------|
| Mecanismo de consultas cross-module legítimas | Antes de F1 | F0 |
| Formato de `Company.settings` (capabilities schema) | Antes de F1.1 | F0 |
| Estrategia de migración de sustituciones a Modifier Engine | Antes de F5 | F2 |
| Definición de "core que no puede romperse" | Antes de F1 | F0 |
| Criterio de "v1 Production Ready" | Antes de F7 | F0 |

---

## 9. Referencias

- ADR-001: `docs/architecture/ADR-001-redireccion-arquitectonica.md`
- Roadmap: `docs/roadmap/ROADMAP-F0-F12.md`
- Documento original: `redireccion_proyecto.txt` (raíz del repo)
