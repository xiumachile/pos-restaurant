# Roadmap de Consolidación y Evolución

**Fecha:** 26 de agosto de 2026
**Commits de referencia:** 220 (punto de inicio)
**Duración total:** 45-48 semanas (secuencial), reducible con equipo paralelo

---

## Resumen ejecutivo

| Prioridad | Fases | Semanas | Objetivo |
|-----------|-------|---------|----------|
| **P0** | F0-F4 | 15 sem | Core estable, sync robusto, comercio unificado |
| **P1** | F5-F9 | 17 sem | Producción + multimodalidad + API pública |
| **P2** | F10-F12 | 14 sem | SaaS + omnichannel + enterprise |

**Versión objetivo v1.0:** Después de F9 (SaaS Platform)

---

## Mapa de prerrequisitos


Cada fase requiere que la anterior esté completada. Las excepciones están marcadas.

---

## FASE 0 — Estabilización de base (1 semana) — P0

**Objetivo:** Cerrar riesgos activos antes de construir flexibilidad.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F0.1 | CI/CD mínimo (GitHub Actions: PHPUnit + Vitest) | 0.5d | — |
| F0.2 | Cashier → evento `TableReleased` (eliminar `DB::table`) | 1.5d | F0.1 |
| F0.3 | Verificar flujo pull-based de impresión (PrintJob) | 1d | F0.1 |
| F0.4 | Bill: `delete()` físico → status `VOID` | 0.5d | F0.1 |

### Criterios de aceptación

- [ ] Un push a `main` corre PHPUnit + Vitest automáticamente
- [ ] Cero `DB::table('restaurant_tables')` fuera del módulo Tables
- [ ] Documento de 1 página confirmando flujo de impresión pull-based
- [ ] Cero `delete()` físico sobre bills; toda bill inválida es `VOID`

### Entregables

- `.github/workflows/ci.yml`
- Evento `TableReleased` + listener en módulo Tables
- Test de integración Cashier → Tables vía evento
- ADR-002 (si se descubre algo nuevo)

---

## FASE 1 — Consolidación Domain Core (3 semanas) — P0

**Objetivo:** Definir los verdaderos agregados + activar `Company.settings`.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F1.1 | Activar `Company.settings` con schema `capabilities` | 3d | F0 |
| F1.2 | Frontend de Business Profile | 3d | F1.1 |
| F1.3 | Domain Events formales (ver lista ADR-001) | 4d | F0 |
| F1.4 | Application Services por módulo | 4d | F1.3 |
| F1.5 | Retrofit trabajo A-F.2 a `capabilities` | 3d | F1.1, F1.2 |

### Criterios de aceptación

- [ ] `Company.settings` se lee en todas las validaciones que hoy están hardcodeadas
- [ ] Cero `withoutGlobalScopes()` sin justificación documentada en ADR
- [ ] 12 Domain Events principales emitidos y consumidos
- [ ] El frontend filtra tabs según `capabilities` de la empresa

### Entregables

- `CompanyController` con CRUD de settings/capabilities
- `SettingsPage` en frontend con formulario de capabilities
- 12 Domain Events + listeners
- Contratos de dominio entre módulos (interfaces en `Domain/Contracts/`)

### Retrofit específico del trabajo A-F.2

- **Fase A (CRUD categorías):** Validar `max_category_depth` de capabilities
- **Fase B (precios multicanal):** Conectar con Price Engine de F5
- **Fase C (menús):** Conectar con Availability de Commerce Engine (F4)
- **Fase D (sustituciones):** Generalizar a Modifier Engine en F5
- **Fase E (frontend admin):** Filtrar tabs por capabilities
- **Fase F.2 (recetas):** Conectar con Inventory Ledger de F6

---

## FASE 2 — Order / Table / Payment State Machine (3 semanas) — P0

**Objetivo:** Transiciones explícitas + Payment Ledger append-only.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F2.1 | Order State Machine (DRAFT→CLOSED) | 4d | F1 |
| F2.2 | Table State Machine (AVAILABLE→OUT_OF_SERVICE) | 3d | F1 |
| F2.3 | Payment Ledger (Payment/Attempt/Allocation/Refund/Reversal) | 5d | F1 |
| F2.4 | Order Core sin mesa (explotar `OrderType.requiresTable()`) | 3d | F2.1 |

### Criterios de aceptación

- [ ] Transiciones de Order/Table explícitas en enum con validación
- [ ] Cero `delete()/update()` sobre pagos existentes
- [ ] Toda reversión crea transacción nueva (append-only)
- [ ] Frontend puede tomar pedido sin mesa (mostrador, delivery)

### Entregables

- `OrderStatus`, `TableStatus`, `PaymentStatus` enums formales
- `PaymentRefund`, `PaymentReversal`, `PaymentAllocation` entidades
- `OrderStateMachine`, `TableStateMachine` servicios
- Test de idempotencia en pagos

---

## FASE 3 — Sincronización Offline-First 2.0 (4 semanas) — P0

**Objetivo:** Convertir el motor de sync en arquitectura distribuida robusta.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F3.1 | Clasificar entidades (LWW vs versioning vs append-only) | 3d | F2 |
| F3.2 | Eliminar LWW de pagos/inventario/fiscal | 4d | F3.1 |
| F3.3 | Middleware Idempotency-Key obligatorio | 3d | F2 |
| F3.4 | Protocolo sync formal (device_id, sequence, operation_id) | 4d | F3.1 |
| F3.5 | Políticas de conflicto (AUTO_RESOLVE, MANUAL_REVIEW, REJECT) | 3d | F3.4 |
| F3.6 | **Stress test:** 20 terminales offline + reconexión | 4d | F3.1-F3.5 |

### Criterios de aceptación (stress test)

- [ ] 0 pagos duplicados
- [ ] 0 pagos perdidos
- [ ] 0 pedidos duplicados
- [ ] 0 fuga de datos entre tenants
- [ ] 0 inventario inválido

### Entregables

- Middleware `Idempotency-Key`
- Entidades con versioning donde corresponde
- Políticas de conflicto documentadas y testeadas
- Suite de stress test reproducible

---

## FASE 4 — Commerce Engine (4 semanas) — P0

**Objetivo:** Pedido desacoplado del modelo tradicional de restaurante.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F4.1 | OrderChannel enum (POS, QR, KIOSK, WEB, MOBILE, API) | 2d | F3 |
| F4.2 | ServiceMode enum (DINE_IN, TAKEAWAY, DELIVERY, etc.) | 2d | F4.1 |
| F4.3 | Fulfillment enum (TABLE, PICKUP, DELIVERY, etc.) | 2d | F4.2 |
| F4.4 | Integrar Availability (de Fase C) al Commerce Core | 4d | F4.3, retrofit F1.5 |
| F4.5 | Generalizar sustituciones a Modifier Engine | 5d | F4.4, retrofit F1.5 |
| F4.6 | Price Engine (branch/channel/time-based) | 5d | F4.5, retrofit F1.5 |

### Criterios de aceptación

- [ ] Un pedido puede existir con cualquier combinación Channel+ServiceMode+Fulfillment
- [ ] El mismo Order Core soporta POS, QR, KIOSK, WEB, MOBILE
- [ ] Los precios resuelven correctamente por contexto
- [ ] Los modificadores aplican a cualquier producto (no solo combos)

### Entregables

- `OrderChannel`, `ServiceMode`, `Fulfillment` enums
- `Modifier`, `ModifierGroup` entidades
- `PriceEngine` service con resolución multi-contexto
- `Availability` service integrado

---

## FASE 5 — Inventario y Costos (3 semanas) — P1

**Objetivo:** Inventory Ledger verdadero + recetas unificadas.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F5.1 | InventoryLedger con 9 tipos de movimiento | 5d | F4 |
| F5.2 | Unificar RawIngredient con InventoryItem | 4d | F5.1, retrofit F.2 |
| F5.3 | Recetas: emitir `InventoryMovementCreated` en deduct | 3d | F5.2 |
| F5.4 | Food cost en tiempo real con ledger | 3d | F5.3 |

### Criterios de aceptación

- [ ] Cero `stock = stock - quantity` directo
- [ ] Todo movimiento de inventario es append-only en ledger
- [ ] Se puede calcular: stock actual, costo promedio, consumo real, merma
- [ ] Food cost % calculado desde ledger, no desde entidad

---

## FASE 6 — POS + KDS + Caja productivos (3 semanas) — P1

**Objetivo:** Experiencia operativa consolidada.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F6.1 | POS unificado (table/counter/takeaway/delivery) | 5d | F5 |
| F6.2 | KDS por estaciones (Kitchen/Grill/Bar/Dessert/Pizza/Expo) | 5d | F5 |
| F6.3 | Caja completa (Opening→X/Z Report) | 4d | F5, F0.2 |

### Criterios de aceptación

- [ ] POS soporta todos los Service Modes de F4
- [ ] KDS enruta ítems a estaciones según producto
- [ ] Caja tiene apertura, movimientos, arqueo, cierre, reportes X/Z
- [ ] Todo auditable y append-only

---

## FASE 7 — Sistema Multimodal Gastronómico (3 semanas) — P1

**Objetivo:** Business Profile + capabilities por rubro.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F7.1 | Business Profile presets (restaurante, café, fast food, etc.) | 3d | F6, F1 |
| F7.2 | Feature flags por empresa | 4d | F7.1 |
| F7.3 | UI adaptativa según capabilities | 4d | F7.2 |

### Criterios de aceptación

- [ ] Un café puede operar sin mesas, con mostrador + KDS
- [ ] Un restaurante tradicional puede operar con mesas + KDS
- [ ] Un fast food puede operar con kiosco + mostrador
- [ ] Sin forks de código por cliente

---

## FASE 8 — API Platform / OpenAPI (3 semanas) — P1

**Objetivo:** Backend como plataforma con API formal.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F8.1 | OpenAPI spec generado automáticamente | 4d | F7 |
| F8.2 | SDK TypeScript + PHP desde OpenAPI | 3d | F8.1 |
| F8.3 | API Keys + scopes + rate limiting | 4d | F8.1 |
| F8.4 | Webhooks (order.*, payment.*, inventory.*) | 4d | F8.1 |

### Criterios de aceptación

- [ ] OpenAPI validado en CI en cada push
- [ ] Contract tests contra OpenAPI
- [ ] Partners pueden consumir API con keys scoping
- [ ] Webhooks firmados y reintentables

---

## FASE 9 — SaaS Platform (4 semanas) — P1

**Objetivo:** Separar plataforma SaaS del POS.

### Tareas

| ID | Tarea | Esfuerzo | Bloqueador |
|----|-------|----------|------------|
| F9.1 | Plans (Starter, Professional, Business, Enterprise) | 3d | F8 |
| F9.2 | Subscriptions con estados (TRIAL→CANCELLED) | 4d | F9.1 |
| F9.3 | Billing + Usage metering | 5d | F9.2 |
| F9.4 | Admin SaaS separado del POS | 4d | F9.3 |

### Criterios de aceptación

- [ ] Una empresa puede auto-registrarse y empezar trial
- [ ] Feature flags bloquean funcionalidades según plan
- [ ] Facturación automática según uso
- [ ] Admin SaaS no expone datos transaccionales del POS

---

## FASE 10 — Omnichannel (5 semanas) — P2

**Objetivo:** Canales adicionales consumiendo el mismo Order Core.

### Tareas

- F10.1 QR Ordering (2 sem)
- F10.2 Online Ordering (2 sem)
- F10.3 Mobile Waiter (1 sem)
- F10.4 Delivery integraciones (UberEats, Rappi, PedidosYa) (2 sem)
- F10.5 Loyalty básico (1 sem)

**Prerrequisito:** F7 + F8 + F9

---

## FASE 11 — Enterprise / BI / Integraciones (5 semanas) — P2

**Objetivo:** Multi-sucursal corporativo + analítica.

### Tareas

- F11.1 Corporate → Brand → Branch (2 sem)
- F11.2 Central Catalog + Pricing + Promotions (2 sem)
- F11.3 Data Warehouse + ETL (2 sem)
- F11.4 BI dashboards (2 sem)

**Prerrequisito:** F9 + F10

---

## FASE 12 — Hardening y lanzamiento comercial (4 semanas) — P0

**Objetivo:** Preparar para producción con clientes reales.

### Tareas

- F12.1 Backup / restore probado
- F12.2 Observabilidad completa (logs, métricas, tracing)
- F12.3 Instalador Tauri production-ready
- F12.4 Auditoría de seguridad
- F12.5 Documentación de usuario final
- F12.6 Plan de rollout

**Prerrequisito:** F7 mínimo (puede ejecutarse en paralelo con F8-F11)

---

## Roadmap de versiones

| Versión | Fase | Criterio de lanzamiento |
|---------|------|------------------------|
| **v0.3** | F0-F2 | Core stabilizado (state machines + tenant hardening) |
| **v0.4** | F3 | Sync 2.0 (stress test offline pasado) |
| **v0.5** | F4 | Commerce Core (channels + pricing + modifiers) |
| **v0.6** | F5 | Inventory & Cost (ledger + food cost) |
| **v0.7** | F6 | Production POS (POS + KDS + Cashier) |
| **v0.8** | F7 | Multimodal POS (rubros configurables) |
| **v0.9** | F8 | API Platform (OpenAPI + SDK + Webhooks) |
| **v1.0** | F9 | **SaaS POS** (primer cliente pagando) |
| **v1.1** | F10 | Omnichannel (QR + Online + Mobile) |
| **v1.5** | F11 | Enterprise (multi-brand + BI) |
| **v2.0** | F12 | Producción comercial masiva |

---

## Gate para declarar v1.0 Production Ready

No se declara v1.0 porque "todos los módulos tengan pantalla". Se declara cuando:

- [x] Multi-tenant probado
- [x] Multi-branch probado
- [x] Offline probado (varias horas sin internet, sync sin pérdida)
- [x] Pagos idempotentes
- [x] Inventario consistente (ledger)
- [x] Order state machine operativa
- [x] Table state machine operativa
- [x] Cashier auditado (sin DB directo)
- [x] KDS funcional
- [x] API documentada (OpenAPI)
- [x] E2E completo (Login → Order → Pay → Close)
- [x] Backup / restore probado
- [x] Observabilidad en producción
- [x] Seguridad auditada
- [x] Instalador Tauri estable
- [x] Impresión funcionando en multi-sucursal
- [x] Recuperación tras pérdida de conexión verificada
- [x] Migraciones reproducibles con rollback

**Criterio rector:** Un restaurante debe poder operar durante varias horas
sin Internet y recuperar la sincronización sin pérdida, duplicación ni
corrupción de transacciones.

---

## Equipo y paralelismo

Con 2-3 desarrolladores, algunas fases pueden ejecutarse en paralelo:


Con equipo paralelo, el calendario se reduce de 45-48 semanas a **~25-30 semanas**.

---

## Métricas de progreso

| Métrica | F0 | F7 | F12 |
|---------|----|----|-----|
| Tests unitarios | 67 | 250+ | 400+ |
| Tests integración | ~20 | 100+ | 200+ |
| Contract tests | 0 | 50+ | 150+ |
| E2E flows | 0 | 10+ | 25+ |
| Stress test offline | 0 | 1 | 5+ |
| API endpoints | ~50 | 120+ | 180+ |
| Cobertura código | ~40% | 70%+ | 80%+ |

---

## Qué NO haremos

Siguiendo la hoja de ruta original, explícitamente evitamos:

- ❌ Reescribir Laravel o React
- ❌ Cambiar PostgreSQL o SQLite
- ❌ Eliminar la arquitectura modular (es correcta)
- ❌ Microservicios prematuros (modular monolith es mejor a esta escala)
- ❌ Agregar features sin estabilizar el core
- ❌ LWW para pagos/inventario/fiscal
- ❌ Acceso directo entre módulos a tablas
- ❌ Un POS diferente por tipo de restaurante
