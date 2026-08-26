# ADR-001: F0 — Baseline y Congelación Arquitectónica

## Status
✅ Completado (2026-08-26)

## Contexto
El proyecto tenía 220 commits y una arquitectura considerable, pero sin tests funcionales.
Era imposible saber qué funcionaba y qué no sin ejecutar manualmente cada funcionalidad.

## Decisión
Implementar una suite de tests completa y documentar el estado del sistema como baseline.

## Resultados Finales

### Métricas
- **Tests totales:** 584
- **Tests pasando:** 511 (87.5%)
- **Tests fallando:** 73 (12.5%)
- **Assertions totales:** 1594
- **Duración de tests:** ~27 segundos (paralelo)

### Core Estable (511 tests pasan)
✅ **Identity & Access**
- Users: CRUD, autenticación, autorización
- Companies: Multi-tenant isolation
- Branches: Scope por sucursal
- Roles/Permissions: RBAC completo

✅ **Catálogo**
- Products: CRUD, impuestos, categorías
- Categories: Jerarquía, traducciones
- Modifiers: Grupos, opciones
- Combos: Items, sustituciones
- Taxes: Herencia, cálculos

✅ **Sincronización**
- SyncEngine: Push/Pull, conflictos
- PullEngine: Descarga de cambios
- PushEngine: Envío de cambios
- ConflictResolver: SERVER_WINS, CLIENT_WINS, MERGE

✅ **Infraestructura**
- Multi-tenant isolation
- Autenticación JWT
- Autorización por roles
- API versioning

### Deuda Técnica (73 tests fallan)
⚠️ **Order State Machine** (~30 tests)
- Transiciones de estado no implementadas correctamente
- Validación de permisos incompleta
- Idempotencia no implementada

⚠️ **Table State Machine** (~5 tests)
- Integración Order ↔ Table no funciona
- Estados no se actualizan automáticamente

⚠️ **Payment Ledger** (~15 tests)
- Ledger append-only no implementado
- PaymentAllocation incompleto
- Split bill no funciona

⚠️ **Validación de endpoints** (~23 tests)
- Retornan 400 (Bad Request) en lugar de 422 (Unprocessable Entity)
- Validación de campos incompleta
- Errores de negocio retornan códigos HTTP incorrectos

## Consecuencias

### Positivas
✅ Baseline estable establecido
✅ CI/CD funcional (tests corren automáticamente en cada push)
✅ Deuda técnica cuantificada y priorizada
✅ Equipo sabe exactamente qué funciona y qué no
✅ Gate de F0 completado

### Negativas
⚠️ 73 tests fallan consistentemente (deuda técnica documentada)
⚠️ No se puede avanzar a funcionalidades nuevas sin resolver Order/Table/Payment State Machines

## Próximos Pasos

### F1 — Domain Core (3 semanas)
Definir agregados, eventos de dominio y contratos formales entre módulos.

**Objetivo:** Preparar la arquitectura para F2 (State Machines)

### F2 — State Machines (3 semanas)
Resolver los 73 tests fallidos implementando:
1. Order State Machine formal
2. Table State Machine formal
3. Payment Ledger append-only
4. Validación correcta de endpoints

**Objetivo:** 584/584 tests pasando (100%)

## Gate de F0
✅ **Completado:** Se puede responder "¿Cuál es exactamente el core que no puede romperse?"

**Respuesta:**
- Core estable: 511 tests (Identity, Catalog, Sync)
- Core inestable: 73 tests (Order, Table, Payment) — backlog de F2

**Decisión:** Avanzar a F1 (Domain Core) con los 73 tests fallidos como deuda técnica priorizada para F2.
