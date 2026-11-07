# DEP-03.04-B — Informe consolidado de auditoría multitenant e integridad operacional

**Proyecto:** POS Restaurante — Backend Laravel SaaS  
**Fecha de consolidación:** 2026-11-07  
**Estado:** Diagnóstico consolidado; correcciones pendientes de autorización.  
**Estrategia aprobada:** Saneamiento incremental con refactorización arquitectónica selectiva y pruebas de regresión obligatorias.

> **Alcance y límites de evidencia.** Este documento consolida los resultados de inspección de código y configuración compartidos durante DEP-03.04-B. No acredita explotación HTTP, ejecución de pruebas de concurrencia ni resolución de los defectos. La prioridad es una propuesta de planificación, no una medición de explotación demostrada. Los identificadores son los originales de la auditoría y no necesariamente son consecutivos.

## 1. Resumen ejecutivo

La auditoría identificó debilidades en cinco áreas: aislamiento multitenant, autorización, integridad de pedidos, procesamiento de eventos e inventario.

La recomendación es **conservar el backend Laravel y sanearlo incrementalmente**, no reconstruirlo desde cero. El patrón recurrente es la aplicación desigual de controles ya existentes: políticas de autorización omitidas en ciertas rutas, consultas que eluden scopes, modificaciones de estados fuera de la máquina de estados, claves idempotentes incompletas y listeners con registro efectivo inconsistente.

La cantidad de hallazgos no equivale a la cantidad de vulnerabilidades explotables ni de causas raíz independientes. La corrección deberá centrarse en garantías comunes y validarse con pruebas específicas y regresión integral.

## 2. Hallazgos principales

| IDs | Área | Diagnóstico | Prioridad propuesta | Evidencia / alcance |
|---|---|---|---|---|
| B-26, B-27 | Pedidos | Selección de productos y asociaciones sin validación multitenant completa | Crítica | Rutas de `OrderItemController`; explotación HTTP pendiente |
| B-28, B-33 | Autorización | Operaciones sobre pedidos sin comprobación explícita de política | Crítica | `OrderItemController::destroy` y `OrderController::update` |
| B-29 | Mesas | Comprobación insuficiente de sucursal al cambiar mesa | Alta | `OrderService::updateOrder`; efecto sujeto a scopes |
| B-30, B-34 | Precios | El servicio admite precios aportados por el cliente | Crítica | `UpdateOrderRequest` y `OrderService::syncOrderItems` |
| B-32 | Pedidos | Escrituras y recálculos sin transacción envolvente visible | Alta | `OrderItemController` |
| B-35, B-36 | Estados | Rutas que evitan la máquina de estados | Crítica | `OrderService::updateOrder` y creación de pedido |
| B-43 | Transiciones | `cashier_id` se guarda antes de validar transición | Alta | `OrderTransitionController::pay` |
| B-44, B-45 | Eventos | Reintentos e idempotencia de impresión por verificar | Alta | Riesgos condicionados; falta prueba integral |
| B-46 | Impresión | Asignaciones de estación sin filtro explícito de empresa | Alta | `PrintKitchenOnOrderConfirm`; alcance sujeto a datos y controles |
| B-47 | Recetas | Listener de descuento registrado dos veces | Alta | `event:list`; doble descuento **no** demostrado |
| B-48 | Impresión | Listeners de impresión ausentes del registro efectivo | Alta | `PrintersEventServiceProvider` no registrado en `bootstrap/providers.php`; otras vías posibles |
| B-49 | Mesas | Actualización directa de estado puede omitir evento de ocupación | Alta | Dependencia del flujo de `OrderConfirmed` |
| B-50 | Inventario | Excepciones de descuento se registran sin impedir confirmación | Crítica | `DeductRecipeOnOrderConfirm`; salvaguardas adicionales por verificar |
| B-51 | Inventario | Carrera posible entre búsqueda de clave e inserción | Alta | Restricción única evita doble inserción comprometida, pero puede provocar error en reintento concurrente |
| B-52 | Recetas | Clave de consumo incluye versión de receta | Alta | Riesgo condicionado a cambios de versión/reintentos y ausencia de snapshot |
| B-53 | Inventario | Falta comprobación central de pertenencia del ingrediente | Crítica | `RawIngredientMovement::record`; no demuestra acceso HTTP cruzado |
| B-54 | Inventario | Idempotencia opcional y sin verificación de equivalencia de contenido | Alta | `RawIngredientMovement::record` |
| B-55 | Inventario | Atomicidad no garantizada en movimiento manual | Alta | `IngredientController::movement` sin transacción visible; falla parcial no reproducida |
| B-56 | Pruebas | Prueba denominada `InventoryDoubleDeductionTest` no reproduce doble listener | Media | Brecha de cobertura, no defecto operacional demostrado |
| B-57 | Inventario | Posible colisión de claves de compensación | Alta | `ReturnIngredientsOnOrderCancel`; condicionado a múltiples consumos aplicables |
| B-58 | Sucursales | Falta comprobación explícita de sucursal en ciertos accesos | Alta | Controlador de ingredientes; alcance sujeto al scope efectivo |
| B-59 | Autorización | Movimiento manual sin permiso específico | Crítica | `RecordMovementRequest::authorize()` devuelve `true`; ruta solo exige JWT/contexto tenant; prueba HTTP pendiente |
| B-60 | Permisos | Compras autorizadas por lista fija de roles | Media | `RegisterPurchaseRequest`; inconsistente con matriz configurable aprobada |
| B-61 | Validación | Cantidades no se diferencian por tipo de movimiento | Alta | Riesgo condicionado a invariantes de `MovementType` y servicio |

### Evidencia destacada del módulo de inventario

- `app/Modules/Recipes/Routes/api.php` aplica `auth:api` y `TenantContextMiddleware`, pero no un middleware específico de permiso sobre `/ingredients/{uuid}/movements`.
- `RecordMovementRequest::authorize()` devuelve `true`.
- `RegisterPurchaseRequest::authorize()` permite roles `manager`, `admin` y `cashier` mediante lista fija.
- `IngredientController::movement()` obtiene el ingrediente filtrando explícitamente por `company_id`, no por `branch_id`, y envía `branchId: $user->branch_id` a `RawIngredientMovement::record()`.
- La ruta de movimiento manual no muestra transacción envolvente; `RawIngredientMovement::record()` no abre una transacción propia en la versión inspeccionada.
- `RawIngredientMovementTest.php` prueba cálculos y validaciones funcionales; `IngredientPurchaseMovementTest.php` prueba compra vía HTTP y costo promedio. Los archivos inspeccionados no cubren explícitamente rechazo por rol, aislamiento cruzado, fallo parcial, idempotencia de payload ni concurrencia.

## 3. Causas raíz

| Código | Causa raíz | Línea de saneamiento |
|---|---|---|
| CR-01 | Autorización inconsistente | Políticas y permisos efectivos en todas las operaciones sensibles; respetar denegaciones explícitas |
| CR-02 | Aislamiento multitenant incompleto | Validar empresa y sucursal en referencias y operaciones internas; revisar consultas sin scopes |
| CR-03 | Múltiples rutas para una operación | Unificar estados, precios y efectos operacionales detrás de contratos de dominio obligatorios |
| CR-04 | Atomicidad e idempotencia parciales | Transacciones, bloqueo, claves idempotentes y comparación de payload; pruebas de reintento y concurrencia |
| CR-05 | Eventos y efectos secundarios inconsistentes | Registro verificable de listeners, deduplicación y recuperación segura de inventario, mesas e impresión |

## 4. Plan de saneamiento incremental propuesto

El orden es **preliminar** y se revisará tras DEP-03.04-C (idempotencia/replay) y DEP-03.04-D (concurrencia). No constituye autorización de implementación.

1. **S-01 — Autorización y multitenancy:** B-26, B-27, B-28, B-29, B-33, B-53, B-58, B-59 y B-60. Pruebas con múltiples empresas, sucursales y roles.
2. **S-02 — Pedidos, precios y estados:** B-30, B-32, B-34, B-35, B-36 y B-43. Precios autorizados, transiciones válidas y escrituras atómicas.
3. **S-03 — Inventario e idempotencia:** B-47, B-50, B-51, B-52, B-54, B-55 y B-57. Reintentos, cambios de versión, fallos parciales y concurrencia.
4. **S-04 — Eventos e impresión:** B-44, B-45, B-46, B-48 y B-49. Registro efectivo, efectos únicos y recuperación ante fallas.
5. **S-05 — Validaciones y regresión integral:** B-56 y B-61, además de las pruebas de integración entre bloques.

Las dependencias entre bloques prevalecen sobre su numeración: por ejemplo, el doble registro de listeners debe evaluarse junto con la idempotencia del descuento.

## 5. Criterios de aceptación por corrección

| Criterio | Evidencia requerida |
|---|---|
| Reproducción | Prueba que evidencie el defecto, cuando sea viable |
| Corrección | Cambio centrado en la causa raíz, con alcance documentado |
| Seguridad | Pruebas negativas de roles, empresas y sucursales |
| Integridad | Invariantes, transacciones y rollback ante fallos |
| Idempotencia | Reintentos equivalentes sin duplicación y conflictos explícitos ante payload diferente |
| Concurrencia | Pruebas simultáneas en operaciones críticas, preferentemente con PostgreSQL real |
| Regresión | Pruebas del módulo, integraciones y suite completa |
| Trazabilidad | IDs resueltos, archivos modificados, resultados y riesgos residuales |

Ningún hallazgo se declarará resuelto solo porque pase una prueba aislada.

## 6. Estado y próximos pasos

| Fase | Estado |
|---|---|
| DEP-03.04-A — JWT | Cerrada previamente |
| DEP-03.04-B — Multitenancy e integridad | Diagnóstico consolidado; correcciones pendientes |
| DEP-03.04-C — Idempotencia y replay | Pendiente |
| DEP-03.04-D — Concurrencia | Pendiente |
| DEP-03.04-E — Regresión | Pendiente |

**Decisión:** conservar el backend Laravel existente y realizar saneamiento incremental tras completar C y D, salvo autorización expresa para una intervención urgente. **No se ha autorizado modificar el código de la aplicación.**

---

**Trazabilidad:** síntesis de las inspecciones de `OrderItemController`, `OrderController`, `OrderService`, `OrderTransitionController`, servicios y listeners de recetas/impresión, `bootstrap/providers.php`, registro efectivo de eventos, rutas de recetas, `IngredientController`, `RecordMovementRequest`, `RegisterPurchaseRequest`, `RawIngredientMovement`, y pruebas de inventario revisadas durante DEP-03.04-B.
