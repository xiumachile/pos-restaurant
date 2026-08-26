# Matriz de Módulos — Baseline F0

Generado: 2026-08-26
Estado: Baseline (F0 completado)

## Resumen

| Categoría | Módulos | Status | Tests |
|-----------|---------|--------|-------|
| Core | 12 | STABLE/PARTIAL | 510/584 |
| Sincronización | 4 | PARTIAL | Incluídos en Core |
| Fiscal | 3 | EXPERIMENTAL | 0 (no implementado) |
| Hardware | 2 | EXPERIMENTAL | 0 (no implementado) |

## Módulos Core

### Identity & Access
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| Users | STABLE | ✅ Pasa | - |
| Companies | STABLE | ✅ Pasa | - |
| Branches | STABLE | ✅ Pasa | - |
| Roles/Permissions | STABLE | ✅ Pasa | - |

### Catálogo
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| Products | STABLE | ✅ Pasa | - |
| Categories | STABLE | ✅ Pasa | - |
| Modifiers | PARTIAL | ⚠️ 74 fallan | State Machine (F2) |
| Combos | PARTIAL | ⚠️ 74 fallan | State Machine (F2) |
| Recipes | EXPERIMENTAL | ❌ No probado | Requiere testing (F5) |

### Transaccional
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| Orders | PARTIAL | ⚠️ 74 fallan | **State Machine incompleta (F2)** |
| Tables | PARTIAL | ⚠️ 74 fallan | **State Machine incompleta (F2)** |
| Payments | PARTIAL | ⚠️ 74 fallan | **Ledger incompleto (F2)** |
| Cashier | PARTIAL | ⚠️ 74 fallan | Auditoría incompleta (F6) |

### Sincronización
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| SyncEngine | STABLE | ✅ Pasa | - |
| PullEngine | STABLE | ✅ Pasa | - |
| PushEngine | STABLE | ✅ Pasa | - |
| ConflictResolver | STABLE | ✅ Pasa | - |

### Kitchen
| Módulo | Status | Tests | Deuda Técnica |
|--------|--------|-------|---------------|
| KitchenDisplay | PARTIAL | ⚠️ 74 fallan | Routing incompleto (F7) |
| PrintJobs | EXPERIMENTAL | ❌ No probado | Hardware abstraction (F7) |

## Módulos Pendientes (No implementados)

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
- **Base de datos:** PostgreSQL 16 (producción/test), SQLite (offline)
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

### P0 — Bloqueante para producción
1. **Order State Machine** (74 tests fallan)
   - Transiciones: DRAFT → OPEN → SUBMITTED → ... → CLOSED
   - Validación de permisos por rol
   - Idempotencia en mutaciones

2. **Table State Machine** (incluido en los 74 tests)
   - Estados: AVAILABLE → OCCUPIED → BILLING → AVAILABLE
   - Integración con Orders

3. **Payment Ledger** (incluido en los 74 tests)
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

| Fase | Objetivo | Duración | Dependencias |
|------|----------|----------|--------------|
| **F0** | Baseline ✅ | **COMPLETADO** | - |
| F1 | Domain Core | 3 semanas | F0 |
| **F2** | **State Machines** | 3 semanas | F1 |
| F3 | Sync 2.0 | 4 semanas | F2 |
| F4 | Commerce Engine | 4 semanas | F2 |

## Gate de F0

✅ **Completado:**
- CI/CD funcionando (510 tests pasan automáticamente)
- Matriz de módulos documentada
- Deuda técnica identificada y priorizada
- Convenciones arquitectónicas definidas

⚠️ **Pendiente:**
- Architecture Decision Record (ADR) detallado
- Mapa de dependencias visual

**Recomendación:** Avanzar a F1 (Domain Core) con los 74 tests fallidos como backlog priorizado para F2.
