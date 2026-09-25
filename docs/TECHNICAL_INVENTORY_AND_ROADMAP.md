# Especificación Técnica y Hoja de Ruta — Wok & Mesa POS

**Proyecto:** Wok & Mesa POS  
**Autor:** Auditoría técnica y planificación colaborativa  
**Fecha:** Septiembre 2026  
**Estado del documento:** Activo (Fuente Única de la Verdad - SSOT)  
**Última actualización:** Septiembre 2026

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

**Conclusión clave:** El backend ya resuelve 3 de las 6 funcionalidades core (cartas, precios múltiples, productos/combos) a un nivel sofisticado. El esfuerzo inmediato debe centrarse en: (a) terminar las pantallas de administración en el frontend para esas tres, y (b) tomar la decisión arquitectónica sobre el inventario antes de construir encima.

---

## 1. Gestión de cartas (menús)
- **Estado:** Backend completo (`MenuResolutionService`, activaciones por canal/horario). Frontend parcial.
- **Tareas Pendientes:**
  - [ ] Backend: Endpoint de previsualización de resolución (`GET /menus/resolve-preview`).
  - [ ] Backend: Regla que impida dejar una sucursal sin carta `is_default`.
  - [ ] Frontend: Completar `MenusTab.tsx` con gestión de reglas de activación.
  - [ ] Frontend: Selector de carta con indicador "Predeterminada" visible.

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
- **Estado:** ⚠️ **DEUDA TÉCNICA CRÍTICA**. Dos sistemas paralelos (`Modules\Inventory` y `Modules\Recipes`) que no se hablan.
- **Decisión Arquitectónica Pendiente:** Consolidar todo en `Recipes` (Opción A, recomendada) o mantener ambos con adaptador (Opción B).
- **Tareas Pendientes (post-decisión):**
  - [ ] Backend: Migraciones de `recipe_items` (tipo + FK a receta) y `product_recipes` (tipo de receta).
  - [ ] Backend: Endpoint `POST /recipes/{id}/production-batches` para registrar producción de elaborados.
  - [ ] Backend: Completar TODO de reserva de stock al confirmar pedido.
  - [ ] Frontend: Pantallas "Insumos", "Producción" y editor de receta mixto.

## 4. Producto único / compuesto / combo
- **Estado:** Backend completo (incluye reglas de sustitución). Frontend requiere bifurcación de UI.
- **Tareas Pendientes:**
  - [ ] Frontend: Selector inicial de tipo (Simple / Con receta / Combo) en formulario de producto.
  - [ ] Frontend: Enlace a editor de `ProductRecipe` para tipo "Con receta".
  - [ ] Frontend: UI de reglas de sustitución usando `ComboReplacementRuleController`.

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
- **Estado:** Backend completo (`PriceList`, `ProductPrice`). Frontend parcial.
- **Tareas Pendientes:**
  - [ ] Backend: Verificar soporte de carga masiva de precios en `PriceListController`.
  - [ ] Frontend: Pantalla de administración con tabla editable (producto × lista de precios).
  - [ ] Frontend: Confirmar re-resolución automática de precios al cambiar canal en el POS.

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

## 9. Orden de Implementación Sugerido (Fases)

### Fase 1: Quick Wins (Entrega de valor visible, bajo riesgo)
- [ ] 1.1 Completar UI de Cartas (Menús)
- [ ] 1.2 Completar UI de Precios Múltiples
- [ ] 1.3 UI de Productos: Bifurcación Simple / Compuesto / Combo

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
