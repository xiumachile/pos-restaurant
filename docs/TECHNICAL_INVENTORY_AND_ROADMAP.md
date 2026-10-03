# Especificación Técnica y Hoja de Ruta — Wok & Mesa POS

**Proyecto:** Wok & Mesa POS  
**Autor:** Auditoría técnica y planificación colaborativa  
**Fecha:** Septiembre 2026  
**Estado del documento:** Activo (Fuente Única de la Verdad - SSOT)  
**Última actualización:** October 2026

---

## 0. Resumen Ejecutivo del Estado Actual

Auditoría realizada sobre el código real (backend Laravel + frontend React/Tauri) para identificar lo construido vs. lo pendiente.

| # | Funcionalidad | Backend | Frontend | Esfuerzo Restante |
|---|---|---|---|---|
| 1 | Gestión de cartas (N cartas, activa/default) | ✅ Completo (avanzado) | 🟡 Parcial (tab admin existe) | **Bajo** — terminar UI |
| 2 | Mesas configurables (tarjeta, fila, ingreso libre) | ❌ No existe | ❌ No existe | **Alto** — feature nueva |
| 3 | Stock/inventario con recetas e ingredientes elaborados | 🟡 Duplicado e inconsistente | ❌ No existe | **Alto** — requiere reconciliación arquitectónica |
| 4 | Producto único / compuesto (receta) / combo | ✅ Completo | 🟡 Parcial | **Bajo-Medio** — terminar UI |
| 5 | Usuarios con permisos por perfil (RBAC) | 🟡 Solo rol fijo (enum) | ❌ No existe | **Medio-Alto** — refactor transversal |
| 6 | N precios por producto (local, delivery, etc.) | ✅ Completo | 🟡 Parcial (hooks existen) | **Bajo** — terminar UI |
| 7 | Toma de pedido desde celular del garzón | ❌ No existe | ❌ No existe | **Alto** — requiere decisión de arquitectura (Opción A vs B) |
| 8 | Reportes/métricas remotos para el dueño | ❌ Módulo vacío (`.gitkeep`) | ❌ Placeholder "🚧" | **Alto** — no tiene dependencias, se puede iniciar ya |

**Conclusión clave:** El backend ya resuelve 3 de las 6 funcionalidades core

### Nota sobre GAP #5 (Fase 1.2)

**Estado:** Documentado como deuda técnica - diferido a Fase 2+

**Razón:** El POS frontend (`OrderTakingPage`, `CatalogPage`) NO usa el endpoint `/menus/active` actualmente. 
El flujo real carga el catálogo completo sin filtrar por carta activa. Implementar resolución de canal 
requeriría:

1. Crear store global `useActiveMenuStore` con `channel_type`
2. Modificar `OrderCatalogPanel` para usar `/menus/active?channel_type=X`
3. Agregar selector de canal en la UI del POS
4. Invalidar cache al cambiar canal

Esto es una **feature nueva** que debería implementarse junto con reportes (Fase 2) o features móviles (Fase 4),
no como parte del cierre de Fase 1. El endpoint `/menus/active` ya existe y funciona correctamente.

**Decisión:** Marcar GAP #5 como "deferred" y avanzar a Fase 2. (cartas, precios múltiples, productos/combos) a un nivel sofisticado. El esfuerzo inmediato debe centrarse en: (a) terminar las pantallas de administración en el frontend para esas tres, y (b) tomar la decisión arquitectónica sobre el inventario antes de construir encima.

---

## 1. Gestión de cartas (menús)
- **Estado:** ✅ **COMPLETADO** (Backend + Frontend)
- **Tareas Completadas:**
  - [x] Backend: Endpoint `GET /menus/resolve-preview` con test de integración (commit 0e8a0f4)
  - [x] Backend: Validación "no dejar sucursal sin carta default" con `BranchRequiresDefaultMenuException` (commit 0e8a0f4)
  - [x] Frontend: `MenusTab.tsx` con gestión completa de reglas de activación (commit 25f22ab)
  - [x] Frontend: Selector de carta con indicador "Predeterminada" + `useSetDefaultMenu` (commit 25f22ab)
  - [x] Frontend: `MenuProductsModal.tsx` para asignación de productos (commit 25f22ab)
  - [x] Frontend: `MenuPreviewModal.tsx` para vista previa (commit 25f22ab)

## 2. Mesas configurables
- **Estado:** Feature nueva de punta a punta.
- **Tareas Pendientes:**
  - [ ] Backend: Migración `branch_table_layout_settings` (`display_mode`, `table_selection_mode`).
  - [ ] Backend: `orders.table_id` nullable + nueva columna `orders.table_label`.
  - [ ] Backend: Validación de no-duplicado de `table_label` entre pedidos abiertos.
  - [ ] Frontend: Pantalla en Configuración → "Diseño de mesas".
  - [ ] Frontend: Componente de ingreso rápido (teclado numérico) para modo `free_entry`.
  - [ ] Frontend: `TablesGrid.tsx` adaptable a `card_grid` o `compact_row`.

## 3. Stock, inventario y recetas (Ingredientes elaborados)
- **Estado:** ✅ **COMPLETADO** (Consolidación ADR-022, Octubre 2026)
- **Decisión Arquitectónica:** Consolidado en `Recipes` (Opción A del ADR-022)
- **Referencia:** `docs/adr/022-inventory-recipes-consolidation.md`
- **Commits:** `84d1298`..`26f67cc` en rama `feature/inventory-consolidation`
- **Tareas Completadas:**
  - [x] Backend: Tabla `raw_ingredient_movements` con historial completo (commit `efab124`)
  - [x] Backend: API de movimientos `POST/GET /ingredients/{uuid}/movements` (commit `fc4d1d8`)
  - [x] Backend: Integración orden→receta→movimientos (commit `6a65105`)
  - [x] Backend: Listener `ReturnIngredientsOnOrderCancel` para cancelaciones (commit `26f67cc`)
  - [x] Backend: Eliminación completa del módulo `Inventory` (commit `26f67cc`, -2039 líneas)
  - [x] Backend: Capability renombrada `CAN_MANAGE_INVENTORY` → `CAN_MANAGE_RECIPES`
- **Tareas Completadas (Fase 3.2 — Octubre 2026):**
  - [x] Backend: Endpoint `POST /recipes/production-batches` (commit `7d40e83`)
  - [x] Frontend: Pantalla "Historial de movimientos" por insumo (commit Bloque 3)
  - [x] Frontend: Tab "Producción" con formulario de lotes (commit Bloque 4)
  - [x] Frontend: Tests de componentes de inventario (11 tests, commit `a584fbe`)
- **Tareas Pendientes (Fase 3.3 — mejoras futuras):**
  - [ ] Frontend: Editor de receta mixto (insumos + elaborados)
  - [ ] Frontend: Selector visual de productos con receta en ProductionBatchForm (actualmente usa UUID manual)
  - [ ] Backend: Stock de productos elaborados como insumos de otras recetas
  - [ ] Frontend: Exportar historial de movimientos a CSV

## 4. Producto único / compuesto / combo
- **Estado:** ✅ **COMPLETADO** (Backend + Frontend)
- **Tareas Completadas:**
  - [x] Frontend: Wizard de tipo de producto (Simple / Compuesto / Combo) en `ProductFormModal.tsx` (commit 3620c58)
  - [x] Frontend: `RecipeSection.tsx` integrado para tipo "Compuesto" (commit 3620c58)
  - [x] Frontend: `ComboProductsEditor.tsx` para selección de productos hijos (commit 3620c58)
  - [x] Frontend: `ComboSubstitutionRules.tsx` usando `ComboReplacementRuleController` (commit fdbf295)
  - [x] Frontend: `ComboCompositionBadge` en `ProductsTab.tsx` (commit 3620c58)

## 5. Gestión de usuarios con permisos por perfil (RBAC)
- **Estado:** Solo enum fijo (`admin`, `manager`, etc.). Sin granularidad.
- **Tareas Pendientes:**
  - [ ] Backend: Migraciones `roles`, `permissions`, `role_permissions`, `users.role_id`.
  - [ ] Backend: Seeder con 5 roles de sistema y matriz de permisos por defecto.
  - [ ] Backend: Middleware/policy `can:{permission}` y actualizar `GET /auth/me` para devolver `permissions: string[]`.
  - [ ] Frontend: `usePermissionsStore` y componente wrapper `<Can permiso="...">`.
  - [ ] Frontend: Filtrar menú lateral y botones de acción según permisos.
  - [ ] Frontend: Pantalla "Usuarios y roles" en Configuración.

## 6. Configuración de precios múltiples
- **Estado:** ✅ **COMPLETADO** (Backend + Frontend)
- **Tareas Completadas:**
  - [x] Backend: Endpoint `POST /price-lists/{uuid}/bulk-prices` para carga masiva (commit fdbf295)
  - [x] Frontend: `PriceMatrixEditor.tsx` - tabla editable producto × lista de precios (commit fdbf295)
  - [x] Frontend: Hook `useBulkPriceUpsert` integrado con React Query (commit fdbf295)
- **Tarea Diferida:**
  - [ ] Frontend: Re-resolución automática de precios al cambiar canal en el POS (ver Nota GAP #5)

## 7. Toma de pedido desde celular del garzón
- **Estado:** No existe. App actual es solo escritorio (Tauri).
- **Decisión Arquitectónica Pendiente:** 
  - *Opción A:* Compilar la misma app Tauri para móvil (mantiene offline-first, alto esfuerzo de UI/CI).
  - *Opción B:* PWA web liviana aparte (pierde offline-first, menor esfuerzo, requiere conexión siempre).
- **Tareas Pendientes (post-decisión):**
  - [ ] Configurar toolchain (A) o nuevo proyecto PWA (B).
  - [ ] Adaptar `OrderTakingPage` a layout de carrito tipo "bottom sheet" para móvil.

## 8. Reportes y métricas remotos
- **Estado:** Módulo vacío (`.gitkeep`). Infraestructura Reverb existe pero no conectada.
- **Tareas Pendientes:**
  - [ ] Backend: Implementar módulo `Reports` con 3 reportes de alta prioridad: Ventas por período, Productos más vendidos, Ventas por mesero/cajero.
  - [ ] Frontend: Nuevo panel web separado del POS de escritorio (mobile-first, login propio).
  - [ ] Frontend: Gráficos básicos (usando librería existente en `package.json`).

---

### Nota sobre GAP #5 (Fase 1.2) — RESUELTO

**Estado:** ✅ **RESUELTO** (Commits: `952e175` - Fase 2 H1)

**Resolución (Octubre 2026):** El GAP #5 fue resuelto como parte del refactor de "canal por pedido" (Fase 2 H1).

**Cambios implementados:**
1. ~~`useActiveMenuStore` global~~ → Canal ahora vive como atributo del pedido (`TableCart.channel`)
2. `OrderCatalogPanel` recibe `channel` como prop → resuelve carta vía `useActiveMenu(channel)`
3. ~~Selector global de canal~~ → Canal se fija al crear el pedido (implícito `dine_in` para mesa, selección explícita para takeaway)
4. Cache por canal vía `queryKey: ["pos", "active-menu", channel]` de react-query

**Resultado:** Cada pedido resuelve su propia carta sin contaminación cruzada entre pedidos.
El endpoint `/menus/active?channel_type=X` ahora es consumido correctamente desde el POS.

---

## Hallazgos de Auditoría (Septiembre-Octubre 2026)

Hallazgos identificados durante auditoría técnica profunda del flujo POS + catálogo.
Cada hallazgo tiene prioridad, fix implementado y tests que garantizan no-regresión.

### H1 — Canal de venta por pedido (no por sesión) — ✅ RESUELTO

**Problema (P0 - UX y dinero):** El canal de venta (`dine_in`/`delivery`/`takeout`) vivía en un store global con persistencia en `sessionStorage`. Al tomar un pedido delivery y luego entrar a una mesa física sin cerrar la app, la mesa se facturaba con precios de delivery (contaminación cruzada de canales).

**Solución (Commit: `952e175`):**
- Canal movido a atributo del pedido (`TableCart.channel`) fijado al crear el pedido
- Nuevo método `useCartStore.initOrder({ tableUuid, channel })` que retorna el cartKey generado
- Pedidos de mesa: canal `dine_in` implícito (sin UI de selección)
- Pedidos fuera de mesa: nuevo flujo `/orders/new` → `ChannelSelectionModal` → `/orders/takeaway/:cartKey`
- `useActiveChannelStore` marcado como DEPRECADO (sin persistencia sessionStorage)
- `OrderCartPanel` refactorizado con props genéricas (`cartKey`, `tableId`, `title`)
- `OrderCatalogPanel` con badge persistente (canal + carta + lista de precios activa) como salvaguarda visual

**Tests (15 tests, todos pasando):**
- `useCartStore.channel.test.ts` (7 tests): aislamiento entre pedidos
- `OrderTakingPage.channel.test.tsx` (3 tests): canal dine_in implícito
- `ChannelSelectionModal.test.tsx` (5 tests): selección explícita para takeaway

### H2 — Scoping de precio por sucursal — ✅ RESUELTO

**Problema (P0 - dinero):** `Product::resolvePrice()` no incluía `branch_id` en la jerarquía de resolución. En empresas multi-sucursal con listas default por sucursal, se podían mezclar precios cruzados.

**Solución (Commit: `87fdd8c`):**
- Nueva jerarquía de 4 niveles en `Product::resolvePrice()`:
  1. Lista indicada explícitamente
  2. **Default scoped por `branch_id` del producto** (NUEVO)
  3. Default company-wide legacy (`branch_id` null)
  4. `base_price` como fallback final
- Compatibilidad con configuraciones legacy mono-sucursal

**Tests (4 tests, todos pasando):**
- `ProductResolvePriceTest`: cubre los 4 niveles de la jerarquía

### H4 — Garantía de carta default por sucursal — ✅ RESUELTO

**Problema (P1 - disponibilidad):** No existía garantía de que cada sucursal tuviera una PriceList y Menu default. Si una sucursal nueva se creaba sin defaults, el POS mostraba "no hay carta activa" sin forma automática de repararlo.

**Solución (Commit: `87fdd8c`):**
- Nuevo `BranchDefaultProvisioner` (lógica reutilizable)
- Nuevo comando `php artisan catalog:ensure-default-menu` (idempotente)
  - Sin argumentos: recorre todas las sucursales activas
  - Con `{branchId}`: repara una específica
- Nuevo `BranchObserver` que provisiona defaults automáticamente al crear una Branch
- Endpoint de diagnóstico `GET /api/v1/catalog/health` (rol admin) con 4 checks:
  - `has_default_price_list`
  - `has_default_menu`
  - `products_without_menu`
  - `products_without_default_price`
- Backfill ejecutado: Branch #2 (Providencia) reparada, Branch #1 (Centro) ya tenía defaults

**Tests (6 tests, todos pasando):**
- `EnsureDefaultMenuCommandTest` (3 tests): creación + idempotencia + recorrido completo
- `CatalogHealthEndpointTest` (3 tests): healthy + unhealthy scenarios

### H3 — Auto-asignación de productos nuevos a carta default (parcial)

**Estado:** Implementado en `ProductController::store()` (commit `87fdd8c`).
Un producto creado vía API se asigna automáticamente a la carta default de su sucursal (transaccional, idempotente).
Flag `skip_default_menu: true` disponible para casos especiales.

### H5 — Tests de canal por pedido — ✅ RESUELTO

Tests de H1 escritos primero (TDD), garantizan que el canal de un pedido no se filtra a otro pedido/mesa.
Ver sección H1 para detalle de los 15 tests.

## 9. Orden de Implementación Sugerido (Fases)

### Fase 1: Quick Wins (Entrega de valor visible, bajo riesgo)
- [x] 1.1 Completar UI de Cartas (Menús) - **Commits: 25f22ab**
- [x] 1.2 Completar UI de Precios Múltiples - **Commits: 8826178, fdbf295**
- [x] 1.3 UI de Productos: Bifurcación Simple / Compuesto / Combo - **Commits: 3620c58, fdbf295**

### Fase 2: Visibilidad para el Dueño (Superficie nueva y aislada)
- [ ] 2.1 Módulo de Reportes Backend (3 reportes de alta prioridad)
- [ ] 2.2 Panel Web de Reportes (Mobile-first, login propio)

### Fase 3: Decisiones Arquitectónicas Críticas
- [ ] 3.1 Reconciliación de Inventario/Recetas (Decisión Opción A vs B)
- [ ] 3.2 Ingredientes elaborados + registro de producción

### Fase 4: Features Nuevas y Refactor Transversal
- [ ] 4.1 Mesas configurables (incluyendo modo "número libre")
- [ ] 4.2 Usuarios y permisos por perfil (RBAC)
- [ ] 4.3 Móvil para garzones (sujeto a decisión Opción A vs B)

---

## 10. Notas Metodológicas
- Todo lo indicado como "qué ya existe" fue verificado leyendo directamente el código fuente.
- Este documento es vivo. Al completar una tarea, marcar el checkbox `[x]` y referenciar el Pull Request correspondiente.
- Cualquier desviación de esta hoja de ruta debe ser documentada aquí como una actualización de estado.

---

## 11. Histórico de Commits de Auditoría (Septiembre-Octubre 2026)

Commits que resolvieron los hallazgos de auditoría documentados en la sección correspondiente.

| Fecha | Commit | Hallazgo | Descripción |
|-------|--------|----------|-------------|
| Octubre 2026 | `87fdd8c` | H2, H4, H3 parcial | fix(catalog): scoping de precio + provisioning + health endpoint (Fase 1 H2/H4) |
| Octubre 2026 | `c84c1f5` | - | test(catalog): factories, HasFactory traits y reorganización de tests (fix CI) |
| Octubre 2026 | `952e175` | H1, H5 | feat(pos): canal por pedido - aislamiento de canales (Fase 2) |
| Octubre 2026 | `3a64ec5` | - | Merge branch 'feature/channel-per-order' into main |

### Rama de desarrollo
- **Rama feature:** `feature/channel-per-order` (Fase 2 H1)
- **Estrategia:** TDD (tests escritos antes de implementación)
- **Merge:** `--no-ff` para preservar trazabilidad de la rama feature

### Verificación de calidad
- **Tests de Fase 1 (H2/H4):** 10 tests pasando (4 + 3 + 3)
- **Tests de Fase 2 (H1):** 15 tests pasando (7 + 3 + 5)
- **TypeCheck frontend:** 0 errores
- **PHP linting:** limpio en todos los archivos modificados
- **Suite completo:** 1051 passed (1 test flaky preexistente no relacionado)
