# Matriz de Módulos — Baseline F0

Generado: 2026-08-26
Estado: Baseline completado
Tests: 511 pasando / 73 fallando (87% success rate)

## Resumen Ejecutivo

| Categoría | Módulos | Status | Tests | Deuda Técnica |
|-----------|---------|--------|-------|---------------|
| Identity & Access | 4 | ✅ STABLE | 100% pasan | - |
| Catálogo | 5 | ✅ STABLE | 100% pasan | - |
| Sincronización | 4 | ✅ STABLE | 100% pasan | - |
| Transaccional | 3 | ❌ PARTIAL | ~50 fallan | **State Machine (F2)** |
| Kitchen | 2 | ⚠️ PARTIAL | ~5 fallan | Routing incompleto |
| Fiscal | 3 | 🔬 EXPERIMENTAL | 0 probados | No implementado |

## Módulos STABLE (511 tests pasan)

### Identity & Access
| Módulo | Status | Tests | Cobertura |
|--------|--------|-------|-----------|
| Users | ✅ STABLE | ✅ Pasa | Autenticación, autorización, CRUD |
| Companies | ✅ STABLE | ✅ Pasa | Multi-tenant isolation |
| Branches | ✅ STABLE | ✅ Pasa | Scope por sucursal |
| Roles/Permissions | ✅ STABLE | ✅ Pasa | RBAC completo |

### Catálogo
| Módulo | Status | Tests | Cobertura |
|--------|--------|-------|-----------|
| Products | ✅ STABLE | ✅ Pasa | CRUD, impuestos, categorías |
| Categories | ✅ STABLE | ✅ Pasa | Jerarquía, traducciones |
| Modifiers | ✅ STABLE | ✅ Pasa | Grupos, opciones |
| Combos | ✅ STABLE | ✅ Pasa | Items, sustituciones |
| Taxes | ✅ STABLE | ✅ Pasa | Herencia, cálculos |

### Sincronización
| Módulo | Status | Tests | Cobertura |
|--------|--------|-------|-----------|
| SyncEngine | ✅ STABLE | ✅ Pasa | Push/Pull, conflictos |
| PullEngine | ✅ STABLE | ✅ Pasa | Descarga de cambios |
| PushEngine | ✅ STABLE | ✅ Pasa | Envío de cambios |
| ConflictResolver | ✅ STABLE | ✅ Pasa | SERVER_WINS, CLIENT_WINS, MERGE |

## Módulos PARTIAL (73 tests fallan)

### Transaccional — DEUDA TÉCNICA PRIORITARIA
| Módulo | Status | Tests | Deuda Técnica | Prioridad |
|--------|--------|-------|---------------|-----------|
| Orders | ❌ PARTIAL | ⚠️ ~30 fallan | **State Machine incompleta** | P0 (F2) |
| Tables | ❌ PARTIAL | ⚠️ ~5 fallan | **State Machine incompleta** | P0 (F2) |
| Payments | ❌ PARTIAL | ⚠️ ~15 fallan | **Ledger incompleto** | P0 (F2) |

**Detalle de fallos:**
- `POST /api/v1/orders/{uuid}/confirm` → Retorna 400 (espera 200)
- `POST /api/v1/orders/{uuid}/cancel` → Retorna 400 (espera 200/403/422)
- `POST /api/v1/billing/payments` → Retorna 400 (espera 201)
- Table no cambia de estado cuando Order se confirma/cierra

### Kitchen
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| KitchenDisplay | ⚠️ PARTIAL | ⚠️ ~5 fallan | Routing incompleto |
| PrintJobs | 🔬 EXPERIMENTAL | ❌ No probado | Hardware abstraction |

## Módulos EXPERIMENTAL (no implementados)

### Fiscal (F8+)
- DTE Chile (SII integration)
- Fiscal Documents
- Folios Management

### Inventory (F5)
- Inventory Ledger
- Cost Tracking
- Purchasing

### Analytics (F11)
- Reports
- BI Dashboard
- Data Warehouse

## Convenciones Arquitectónicas

### Testing
- **Framework:** Pest (PHP), Vitest (TypeScript)
- **Base de datos:** PostgreSQL 16 (producción/test)
- **Cobertura objetivo:** 80% para módulos STABLE
- **Gate:** Ningún módulo PARTIAL avanza a STABLE sin 100% tests pasando

### Comunicación entre módulos
- **Permitido:** Domain Events, Application Services, Contratos
- **Prohibido:** Acceso directo a tablas de otros módulos (DB::table)
- **Ejemplo prohibido:** Cashier modificando `restaurant_tables` directamente

### Versionamiento
- **API:** `/api/v1/` (versionado en URL)
- **Eventos:** `{ModuleName}.{Action}` (ej: `Order.Created`)
- **Breaking changes:** Requieren nueva versión de API

## Deuda Técnica Priorizada

### P0 — Bloqueante para producción (73 tests)
1. **Order State Machine** (~30 tests fallan)
   - Transiciones: DRAFT → CONFIRMED → PREPARING → READY → SERVED → PAID → CLOSED
   - Validación de permisos por rol
   - Idempotencia en mutaciones

2. **Table State Machine** (~5 tests fallan)
   - Estados: AVAILABLE → OCCUPIED → BILLING → AVAILABLE
   - Integración con Orders

3. **Payment Ledger** (~15 tests fallan)
   - Append-only (no borrar, solo reversar)
   - PaymentAllocation, PaymentRefund

### P1 — Siguiente nivel
4. Inventory Ledger (F5)
5. KDS Routing (F7)
6. Hardware Abstraction (F7)

### P2 — Expansión
7. DTE Chile (F8)
8. Recipes & Food Cost (F5)
9. OpenAPI Platform (F9)

## Próximas Fases

| Fase | Objetivo | Duración | Dependencias | Tests Objetivo |
|------|----------|----------|--------------|----------------|
| **F0** | Baseline ✅ | **COMPLETADO** | - | 511/584 (87%) |
| **F1** | Domain Core | 3 semanas | F0 | 511/584 (87%) |
| **F2** | **State Machines** | 3 semanas | F1 | **584/584 (100%)** |
| F3 | Sync 2.0 | 4 semanas | F2 | 584/584 (100%) |
| F4 | Commerce Engine | 4 semanas | F2 | 584/584 (100%) |

## Gate de F0

✅ **Completado:**
- CI/CD funcionando (511 tests pasan automáticamente)
- Matriz de módulos documentada
- Deuda técnica identificada y priorizada
- Convenciones arquitectónicas definidas
- ADR documentado

⚠️ **Pendiente:**
- Mapa de dependencias visual (opcional)

**Recomendación:** Avanzar a F1 (Domain Core) con los 73 tests fallidos como backlog priorizado para F2.

**Nota importante:** Según la hoja de ruta, NO se debe avanzar a nuevas funcionalidades sin consolidar el core transaccional. 
Los 73 tests fallidos representan exactamente la deuda técnica que debe resolverse en F2 antes de agregar más funcionalidades.
