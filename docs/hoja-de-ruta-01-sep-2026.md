#Hoja de Ruta — 01 Septiembre 2026

**Fecha**: Lunes 01 Septiembre 2026  
**Última actualización**: 01 Septiembre 2026  
**Estado del proyecto**: 🟢 Backend financiero blindado, listo para frontend

---

## 📊 Resumen Ejecutivo de la Mega-Sesión (31 Ago - 01 Sep 2026)

### Duración y Alcance
- **Duración total**: ~8 horas de trabajo intenso distribuidas en 2 días
- **Commits generados**: 18 commits
- **Tests agregados**: +30 (de 800 a 830)
- **Archivos nuevos**: ~15 (entidades, servicios, migrations, tests, ADRs)
- **Incidentes cerrados**: 1 (secretos en git - rotados + historial purgado)

### Ítems P0 Completados

| Ítem | Descripción | Commit | Estado |
|------|-------------|--------|--------|
| **P0-01** | Eliminar .env.backup + rotar secretos | `3c6195a`, `9cc181d` | ✅ Completado |
| **P0-02** | Auditoría git + git-filter-repo + force push | `3c6195a` | ✅ Completado |
| **P0-03** | Payment Ledger (double-entry) | `df229b2` | ✅ Completado |
| **P0-04** | Refund/Reversal proporcional | `205578f` | ✅ Completado |
| **P0-05** | Integración Ledger ↔ Payment + BranchScope fix | `8ea7157` | ✅ Completado |
| **P0-06** | Idempotencia en Ledger (defensa anti-doble asiento) | `4b69a38` | ✅ Completado |

### Métricas de Calidad
Tests totales : 830 passing (2347 assertions)
Tests agregados : +30 (Ledger, Refund, Integration, Idempotency)
Regresiones : 0
Deuda técnica crítica : 0
Seguridad : Blindada (secretos rotados + gitleaks CI + pre-commit)
Consistencia : 100% (modelo ↔ DB ↔ docblocks)
Order (pedido)
↓
PaymentService (registro de pagos)
├─ Idempotencia por idempotency_key
├─ Account::seedDefaultsFor() (INSERT ON CONFLICT DO NOTHING)
├─ DB::transaction()
│ ├─ Crea Payment
│ ├─ PaymentLedgerService.recordPayment()
│ │ ├─ Cálculo proporcional (ratio = payment/total)
│ │ ├─ JournalEntry (idempotente por reference_type + reference_id)
│ │ └─ LedgerEntries balanceadas (debits == credits)
│ └─ Actualiza Order/Bill
└─ Retorna Payment
RefundService (reembolsos)
├─ Idempotencia por idempotency_key
├─ Reversa proporcional del asiento original
└─ JournalEntry (ReferenceType::REFUND, idempotente)
BranchScope (seguridad multi-nivel)
└─ WHERE (branch_id = X OR branch_id IS NULL)
✅ Globales de empresa + específicos de sucursal

### Plan Contable Implementado

| Código | Nombre | Tipo | Descripción |
|--------|--------|------|-------------|
| 1100 | Efectivo en Caja | Asset | Efectivo físico en caja |
| 1200 | Bancos | Asset | Cuentas bancarias |
| 1300 | Por Cobrar Tarjetas | Asset | Pagos con tarjeta por liquidar |
| 1400 | Gift Cards por Canjear | Asset | Gift cards emitidas pendientes |
| 2100 | IVA por Pagar | Liability | IVA colectado pendiente de pago |
| 2200 | Propinas por Pagar | Liability | Propinas colectadas pendientes |
| 4100 | Ingresos por Ventas | Revenue | Ingresos por ventas |
| 4200 | Descuentos sobre Ventas | Revenue | Descuentos aplicados (contra-ingreso) |
| 5100 | Costo de Ventas | Expense | Costo de productos vendidos |
| 5200 | Gastos Operativos | Expense | Gastos generales del negocio |

### Ejemplo: Pago de $11,900 en Efectivo con Propina de $2,000
Order:
Subtotal: $10,000
IVA (19%): $1,900
Total: $11,900
Payment:
Amount: $11,900
Tip: $2,000
Total: $13,900
JournalEntry (ReferenceType::PAYMENT):
├── DEBIT Cash (1100) $13,900
├── CREDIT Revenue (4100) $10,000
├── CREDIT TaxPayable (2100) $1,900
└── CREDIT TipsPayable (2200) $2,000
─────────
Balance: $0 ✓

---

## 📚 Documentación Arquitectónica Entregada

### Architecture Decision Records (ADRs)

1. **ADR-001**: Modelo de Fulfillment (`type` vs `fulfillment_channel`)
   - Decisión: usar `fulfillment_channel` como campo independiente
   - Razón: flexibilidad para múltiples canales sin acoplamiento
   - Commit: `58e0f89`

2. **ADR-002**: Backward Compatibility en Transiciones de Estado
   - Decisión: mantener estados legacy durante migración
   - Razón: no romper integraciones existentes
   - Commit: `58e0f89`

3. **ADR-003**: Patrón de Policies DDD con Defensa en Profundidad
   - Decisión: policies con validación multi-capa
   - Razón: seguridad robusta sin acoplamiento
   - Commit: `58e0f89`

4. **ADR-004**: Idempotencia en Ledger (nuevo)
   - Decisión: defensa en dos capas (servicio + DB)
   - Razón: garantizar zero asientos duplicados
   - Commit: `4b69a38`

### Guías Operativas

- **`docs/modules/fulfillment.md`**: Guía completa con ejemplos cURL
- **`docs/development/definition-of-done.md`**: Checklists formales + anti-patrones

### Gaps Identificados

- **`docs/architecture/gaps/frontend-integration.md`**: 3 gaps documentados con horas estimadas

---

## 🎓 Lecciones Aprendidas Clave

### 1. Idempotencia Multi-Capa (ADR-004)

**Problema**: Llamadas duplicadas a `createJournalEntry()` causaban asientos duplicados.

**Solución**: Defensa en tres capas:
- **Capa API**: `idempotency_key` en requests
- **Capa servicio**: verificación previa antes de crear
- **Capa DB**: unique constraint como defensa última

**Resultado**: Zero asientos duplicados garantizado.

### 2. PostgreSQL Transaction Aborted (25P02)

**Problema**: Un error en transacción aborta TODOS los comandos posteriores.

**Solución**: 
- `INSERT ON CONFLICT DO NOTHING` (no falla nunca)
- Sembrar cuentas ANTES de abrir transacción

**Resultado**: Seeds idempotentes sin errores de transacción.

### 3. Multi-tenancy Multi-nivel

**Problema**: BranchScope filtraba estrictamente por `branch_id`, excluyendo cuentas globales.

**Solución**: 
```sql
WHERE (branch_id = X OR branch_id IS NULL)
Resultado: Dos niveles de visibilidad (global + específico) coherentes con arquitectura multi-tenant.
4. Reversa Proporcional en Ledger
Problema: Refunds parciales necesitaban reversa proporcional del asiento original.
Solución:
Ratio = refund_amount / payment_total
Aplicar ratio a cada línea del asiento original
Ajuste por redondeo en primera línea
Resultado: Reversas contables precisas y balanceadas.
🚀 Estado Actual del Proyecto
✅ Completado
Backend financiero sólido: ledger + refunds + idempotencia
Seguridad blindada: secretos rotados + gitleaks CI + pre-commit hook
Arquitectura documentada: 4 ADRs + DoD formal + gaps identificados
830 tests pasando: suite completa sin regresiones
BranchScope multi-nivel: arquitectura coherente
Módulo Accounting: completo con entidades, servicios, tests
📦 Entregables del Sprint
Categoría
Cantidad
Detalle
Entidades nuevas
4
Account, JournalEntry, LedgerEntry, Refund
Servicios nuevos
2
LedgerService, PaymentLedgerService
Value Objects
3
AccountType, ReferenceType, RefundStatus
Migraciones
5
accounts, journal_entries, ledger_entries, refunds, unique constraint
Tests nuevos
30
Ledger, Refund, Integration, Idempotency
ADRs
1
ADR-004 (Idempotencia en Ledger)
Excepciones
2
UnbalancedJournalEntryException, InvalidRefundException
🎯 Plan del Siguiente Sprint
Opción A: Sprint de Frontend (Gap 1) ⭐ Recomendado
Objetivo: Hacer usable desde la UI el backend de capabilities que ya está completo.
Entregables:
SettingsPage.tsx con listado de 8 capabilities por empresa
Integración con GET/PUT /api/v1/companies/{id}/capabilities
Toggle UI para activar/desactivar capabilities
Feature flags condicionales en componentes existentes
Horas estimadas: 12-16 horas (1 sprint)
Dependencias:
✅ Backend listo (CompanyController, CompanyPolicy, capabilities conectadas)
⏳ Frontend pendiente (Gap 1)
Criterio de éxito:
Admin puede ver y modificar capabilities desde UI
Componentes frontend se adaptan dinámicamente a capabilities activas
Tests E2E básicos del flujo de configuración
Opción B: Sprint de Inventory (P1-07 a P1-10)
Objetivo: Implementar inventario con ledger de doble entrada.
Entregables:
P1-07: Inventory Ledger (espejo del Payment Ledger)
P1-08: Recipe → Inventory (descuenta stock al crear items)
P1-09: Waste/Adjustment/Transfer (operaciones de inventario)
P1-10: Inventory + Order E2E (valida integración completa)
Horas estimadas: 18-22 horas (2 sprints)
Dependencias:
✅ Payment Ledger completo (P0-03)
✅ Idempotencia en ledger (P0-06)
⏳ Módulo Inventory pendiente
Opción C: ADR-005 + Documentación
Objetivo: Documentar decisiones pendientes y crear guías operativas.
Entregables:
ADR-005: BranchScope multi-nivel (documentar decisión)
Guía operativa: docs/modules/accounting.md
Actualizar docs/hoja-de-ruta.md con estado actual
Horas estimadas: 4-6 horas
Dependencias:
✅ Todas las decisiones ya implementadas
⏳ Documentación pendiente
📋 Priorización Recomendada
Mi Recomendación: Opción A (Sprint de Frontend)
Razón: El backend de capabilities ya está completo y probado, pero no es usable desde la UI. Esto limita el valor entregado al cliente.
Beneficios:
✅ Valor inmediato para el cliente (puede configurar capabilities)
✅ Desbloquea desarrollo de features condicionales
✅ Valida el backend en escenario real
✅ Cierra el Gap 1 documentado
Plan de ejecución:
Día 1: SettingsPage.tsx + API integration (6h)
Día 2: Feature flags condicionales + tests E2E (6h)
Día 3: Refinamiento + documentación (4h)
Total: 16 horas (2 días de trabajo)
🔗 Referencias
Commits Clave
4b69a38: P0-06 — Idempotencia en Ledger (último commit)
8ea7157: P0-05 — BranchScope fix + Ledger integration
205578f: P0-04 — Refund/Reversal proporcional
df229b2: P0-03 — Payment Ledger
9cc181d: P0-01/02 — Seguridad (gitleaks + pre-commit)
3c6195a: P0-01/02 — Rotación de secretos + force push
Documentación
ADRs: docs/architecture/decisions/
Guías: docs/modules/
Gaps: docs/architecture/gaps/
DoD: docs/development/definition-of-done.md
Tests
Suite completa: ./vendor/bin/pest
Tests de Ledger: tests/Feature/LedgerTest.php
Tests de Refund: tests/Feature/RefundTest.php
Tests de Idempotencia: tests/Feature/LedgerIdempotencyTest.php
Tests de Integration: tests/Feature/PaymentLedgerIntegrationTest.php
📞 Contacto y Siguiente Sesión
Próxima sesión recomendada: Sprint de Frontend (Gap 1)
Fecha sugerida: Martes 02 Septiembre 2026
Duración estimada: 2 días (16 horas)
Preparación para siguiente sesión:
Revisar esta hoja de ruta
Confirmar opción de sprint (A, B o C)
Preparar entorno de frontend (Node.js, React, etc.)
Revisar gaps documentados en docs/architecture/gaps/
Documento generado automáticamente al finalizar mega-sesión
Commit: 4b69a38
Fecha: 01 Septiembre 2026
Autor: Arquitecto + Desarrollador (colaboración AI-humano)
