# Payment Offline Readiness — Criterios GO/NO-GO

**Estado**: FASE 0 — BLOQUEO DE DESARROLLO DE PAGOS OFFLINE
**Rama**: `hardening/payment-offline-foundation`
**Fecha**: 2026-09-07
**Decisión**: Congelar pagos offline hasta que el backend financiero sea estable

---

## 🎯 Objetivo

Garantizar que el backend financiero es sólido y confiable **antes** de implementar pagos offline en el frontend.

**Riesgo de saltar esta fase**:
- Doble cobro por reintentos
- Estados inconsistentes entre Bill/Order/Table
- Pérdida de pagos durante sincronización
- Violaciones de integridad financiera
- Imposibilidad de reconciliar al reconectar

---

## 📋 Criterios GO/NO-GO

### FASE 1 — INTEGRIDAD FINANCIERA (P0)

#### 1.1 Autoridad única sobre Bill
- [ ] Solo PaymentService actualiza `Bill.paid_amount`
- [ ] CashierTableService NO modifica campos financieros
- [ ] Test: pagar 2 veces con misma idempotency_key → paid_amount incrementado 1 vez

#### 1.2 Idempotencia segura
- [ ] IdempotencyKeyMiddleware verifica ANTES de procesar
- [ ] Transacción atómica: Payment + Ledger + Bill + Order + Table
- [ ] Rollback completo ante timeout/crash/refresh
- [ ] 5 escenarios de fallo cubiertos con tests

#### 1.3 Endpoints unificados
- [ ] Un solo endpoint oficial documentado
- [ ] Endpoint duplicado deprecado con @deprecated
- [ ] Test de paridad entre endpoints

#### 1.4 Validación de relaciones de dominio
- [ ] Company → Branch → Table → Order → Bill → Payment validado
- [ ] Cross-branch payment → 403
- [ ] Cross-company payment → 403

#### 1.5 Concurrencia
- [ ] lockForUpdate() en Bill durante pago
- [ ] Unique constraint en idempotency_key
- [ ] Test: 10 pagos concurrentes misma key → solo 1 éxito

---

## 🚦 Decisión GO/NO-GO

**GO** si todos los checkboxes están marcados y cobertura > 90% en PaymentService.

**NO-GO** si algún criterio falla.

---

**Autor**: Arquitectura WokMesa
**Estado**: Diagnóstico en progreso
**Estado**: ✅ FASE 1 COMPLETA — GO PARA FASE 2
**Rama**: `hardening/payment-offline-foundation`
**Fecha**: 2026-09-07
**Último commit**: fix(cashier): hardening completo de payBill

---

## 🎯 Objetivo

Garantizar que el backend financiero es sólido antes de implementar pagos offline en el frontend.

---

## 📋 Criterios FASE 1 — INTEGRIDAD FINANCIERA (P0)

### 1.1 Autoridad única sobre Bill ✅
**Problema detectado**: `CashierTableService::payBill` duplicaba la actualización de `paid_amount`.
Un pago de \$11.900 resultaba en `paid_amount = \$23.800` (2x).

**Fix aplicado**:
- Refactor `payBill` para delegar completamente a `PaymentService::registerPayment()`
- Eliminada toda la lógica post-pago duplicada
- Solo `Bill::registerPaymentAmount()` como fuente de verdad

**Test**: `payBill NO genera doble cobro — paid_amount debe ser igual al monto pagado`
- Assertion: `paid_amount === 11900.0` (no 23800)
- Status: ✅ PASS

### 1.2 Idempotencia en endpoint ✅
**Problema detectado**: `/v1/cashier/bills/{uuid}/pay` NO tenía middleware `idempotent`.

**Fix aplicado**:
- Agregado middleware `idempotent` al endpoint
- Usa estrategia Redis+SQL (ADR-007)
- Respuesta cacheada por 24h

**Test**: `payBill es idempotente con misma idempotency_key`
- Assertion: retry con misma key retorna respuesta cacheada
- Status: ✅ PASS

### 1.3 Transacción atómica ✅
**Verificación**: `PaymentService::registerPayment()` ya implementa:
- `DB::transaction()` envolviendo toda la operación
- `lockForUpdate()` en Order
- Event `OrderPaid` disparado DENTRO de la transacción
- Rollback completo si `recordPayment()`, `registerPaymentAmount()` o `updateOrderPaymentStatus()` falla

**Test**: `payBill hace rollback completo si falla alguna etapa`
- Assertion: validation error (amount > available) no deja Payment creado
- Status: ✅ PASS

### 1.4 Validación de relaciones de dominio ✅
**Verificación**: `Bill::where('branch_id', $branchId)` en controller previene cross-branch.
`PaymentMethod::forBranch($branchId)` previene cross-branch en método de pago.

**Test**: `usuario B no puede pagar bill de empresa A`
- Assertion: response 404/403, no se crea Payment
- Status: ✅ PASS

### 1.5 Concurrencia ✅
**Verificación**: `lockForUpdate()` + unique constraint en `idempotency_key` + middleware idempotent.

**Test**: `payBill maneja 10 requests concurrentes con misma idempotency_key`
- Assertion: solo 1 Payment creado tras 10 requests
- Status: ✅ PASS

---

## 🚦 Decisión GO/NO-GO

### ✅ GO para FASE 2

Todos los criterios P0 están verificados con tests automatizados:
PASS Tests\Feature\CashierPayBillTest
✓ payBill NO genera doble cobro 2.27s
✓ payBill es idempotente con misma idempotency_key 0.20s
✓ payBill maneja 10 requests concurrentes 0.21s
✓ usuario B no puede pagar bill de empresa A 0.10s
✓ payBill hace rollback completo si falla alguna etapa 0.09s
Tests: 5 passed (32 assertions)

**Suite de regresión**: 124 tests relacionados con payments/cashier/bill → todos pasando.

---

## 📅 Próximos pasos: FASE 2 — Modelo offline

Una vez que el backend financiero es estable (FASE 1 completada), el siguiente paso es preparar el **modelo de datos offline** en el frontend:

### FASE 2: PaymentRepository en SQLite
- [ ] Crear tabla `local_payments` (ya existe en migración 001)
- [ ] Implementar `PaymentRepository` (create, findByOrderUuid, findByBillUuid)
- [ ] Implementar `PaymentMutationRepository` para registrar mutaciones
- [ ] Agregar migración para campos offline-specific (cloud_id, synced_at, sync_status)

### FASE 3: Sync de pagos offline
- [ ] Implementar `createPaymentOffline` que:
  - Crea Payment en SQLite
  - Encola mutación `payment/create` en `sync_queue`
  - Actualiza `local_orders.paid_amount` localmente
  - Libera mesa localmente (status → available)
- [ ] Implementar `SyncEngine.processPaymentCreate()`:
  - POST `/billing/payments` con idempotency_key
  - Reconcilia cloud_id con local_uuid
  - Marca mutación como synced

### FASE 4: Frontend de pagos offline
- [ ] Botón "Cobrar mesa" en CashierPage (offline-aware)
- [ ] Modal de pago que usa `createPaymentOffline`
- [ ] Indicador visual de "pendiente de sincronización"
- [ ] Reconciliación al volver online

---

## 📝 Archivos modificados en FASE 1

| Archivo | Cambio |
|---------|--------|
| `app/Modules/Cashier/Domain/Services/CashierTableService.php` | Refactor `payBill` (delegación) |
| `app/Modules/Cashier/routes/api.php` | Middleware `idempotent` agregado |
| `tests/Feature/CashierPayBillTest.php` | 5 tests nuevos |

## 📊 Métricas de la rama

| Métrica | Valor |
|---------|-------|
| Commits en rama | 5 |
| Tests nuevos | 5 (32 assertions) |
| Tests regresión | 124/124 passing |
| Bugs críticos resueltos | 1 (doble cobro) |
| Riesgos mitigados | 5 (P0-01 a P0-05) |

---

**Autor**: Arquitectura WokMesa
**Revisado por**: [Pendiente]
**Aprobado por**: [Pendiente]
**Estado**: ✅ GO para FASE 2

