# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

## [2.1.0-split-bill-offline] - 2026-09-17

### ✨ Added

#### Split Bill Offline (ADR-020)
- **FASE 4**: Soporte completo de split bill en modo offline
  - Bills son sincronizables al backend (`POST /api/v1/bills`)
  - Payments vinculan `bill_uuid` desde `bill_local_uuid`
  - Orden de sincronización garantizado (Order → Bill → Payment)
  - Endpoint backend idempotente vía `idempotency_key`

**Commits**:
- `3353e69` docs: ADR-020 bills are syncable
- `c23cdc1` feat(bills): POST /api/v1/bills endpoint
- `7d40756` feat(sync): offline bills sync to backend
- `d8c3472` feat(sync): link payments to bills + E2E test

**Archivos clave**:
- `app/Modules/Payments/Interfaces/Controllers/BillController.php::store()`
- `app/Modules/Payments/Interfaces/Requests/StoreBillRequest.php`
- `database/migrations/2026_09_17_120001_add_idempotency_key_to_bills.php`
- `frontend/src/services/sync/SyncEngine.ts::processBill()`
- `frontend/src/db/repositories/BillRepository.ts`

#### Bill Local UUID Tracking (ADR-019)
- `bill_local_uuid` en `local_payments` para preservar estructura de split bill
- Migración 015 agregando columna e índice

**Commits**:
- `007c42c` feat(db): add bill_local_uuid to local_payments (migration 015)
- `566cebe` feat(offline): track bill in split payments

**Archivos clave**:
- `frontend/src/db/migrations/015_add_bill_link_to_payments.sql`
- `frontend/src/db/repositories/PaymentRepository.ts`
- `frontend/src/services/offlinePaymentService.ts`

### 🔧 Changed

- **ADR-009 parcialmente reemplazado**: bills ahora son sincronizables (commit `TBD-5a`)
- **BillingService**: removidos casts `(float)` innecesarios (commit `669b3c8`)
- **Especificación F13**: agregada sección 14 "Split Bill Offline" (commit `TBD-5b`)

### 🛡️ Tests

#### Frontend (nuevos)
- `syncEngine.splitBillE2E.test.ts`: 3 tests E2E
- `syncEngine.bill.test.ts`: 5 tests unitarios
- `migration.015.billLink.test.ts`: 5 tests de migración
- `offlinePaymentService.splitBill.test.ts`: 6 tests (ampliados con ADR-019)

#### Backend (nuevos)
- `BillSyncTest.php`: 7 tests del endpoint POST /bills
- `BillIntegerMigrationTest.php`: 5 tests de integridad INTEGER

### 📚 Documentation

- `docs/adr/020-bills-are-syncable.md`: Contrato completo de sincronización
- `docs/adr/009-bills-no-sincronizables.md`: Banner de supersede agregado
- `docs/especificacion_tecnica_f13_frontend.md`: Sección 14 Split Bill Offline

---

## [2.0.0-clp-integer] - 2026-09-16

### 🎯 Breaking Changes (ADR-018)

**Migración completa de DECIMAL a INTEGER para montos monetarios**

El peso chileno (CLP) no tiene centavos. Todos los campos monetarios en backend y frontend ahora usan INTEGER en lugar de DECIMAL(14,2).

**Impacto**:
- Frontend: todos los valores monetarios son enteros en TypeScript
- Backend: todos los campos monetarios son INTEGER en PostgreSQL
- Sync: payloads ya no requieren conversión de tipo
- Tests: 40+ tests actualizados para validar integer semantics

**Commits destacados**:
- `e108851` chore: cleanup migration backups
- `8b28c0c` fix(financial): OrderItemController uses int + LedgerTest complete
- `6db9ef2` fix(tests): DTE tests use int total_amount
- `8530629` fix(tests): complete int semantics in 5 financial test suites
- `0d86475` fix(tests): MasterE2E and PosFullFlow use int semantics
- `fc58ef9` fix(tests): CrashRecoveryTest uses int semantics
- `c61e3c9` fix(financial): LedgerService.getAccountBalance returns int

**Decisiones relacionadas**:
- ADR-010: Money type integer CLP
- ADR-011: Modelo de montos Chile
- ADR-018: Monetary values as integer

---

## [1.x.x] - Releases anteriores

Ver [historial completo de releases](https://github.com/xiumachile/pos-restaurant/releases) para cambios anteriores.

---

## Convenciones de este Changelog

### Tipos de cambios
- `Added` para nuevas funcionalidades
- `Changed` para cambios en funcionalidades existentes
- `Deprecated` para funcionalidades pronto a eliminar
- `Removed` para funcionalidades eliminadas
- `Fixed` para corrección de bugs
- `Security` para correcciones de seguridad

### Versionado
- **MAJOR** (X.0.0): Cambios incompatibles con API
- **MINOR** (0.X.0): Nuevas funcionalidades retrocompatibles
- **PATCH** (0.0.X): Correcciones retrocompatibles

### Etiquetas de commits
- `feat`: Nueva funcionalidad
- `fix`: Corrección de bug
- `docs`: Solo cambios en documentación
- `refactor`: Cambio de código que no agrega funcionalidad ni corrige bug
- `test`: Agregar o corregir tests
- `chore`: Mantenimiento, tooling, configs

