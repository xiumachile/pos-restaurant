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
