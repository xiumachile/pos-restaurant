# ADR-016: Migración de Modelo NETO a BRUTO (Cumplimiento SII Chile)

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Contexto**: Sistema POS para restaurantes en Chile

## Contexto

El sistema tiene una inconsistencia semántica crítica entre frontend y backend:

| Capa | Semántica | Fórmula | Ejemplo ($10,000) |
|------|-----------|---------|-------------------|
| **Frontend SQLite** | Bruto (ADR-011) | `net = gross / 1.19` | subtotal=10000, net=8403, tax=1597 |
| **Backend PostgreSQL** | Neto (legacy) | `tax = subtotal × 19%` | subtotal=10000, tax=1900, total=11900 |
| **DTE SII** | Bruto (correcto) | net_amount + tax_amount = total | Requiere net=8403, tax=1597 |

## Problema Identificado

### 1. Doble Imposición (ilegal según SII)
Cliente ve: Hamburguesa $10,000 (IVA incluido)
Backend calcula: total = 10000 + 1900 = $11,900 ❌
Cliente paga: $11,900 (19% extra no declarado)

### 2. Asientos Contables Cuadran Mal

PaymentLedgerService usa subtotal=10000 (asumiendo neto)
Revenue acreditado: $10,000
Tax acreditado: $1,900
→ Pero el cliente pagó $10,000 bruto, NO $11,900
→ Los libros contables NO reflejan la realidad

### 3. Sync Frontend ↔ Backend Roto
Frontend guarda: gross=10000, net=8403, tax=1597
Backend espera: subtotal=10000, tax=1900, total=11900
→ Al sincronizar, los valores no coinciden
→ DTE se emite con datos inconsistentes

## Decisión

**Migrar el backend de modelo NETO a modelo BRUTO** para cumplir con normativa chilena y alinear con ADR-011.

### Modelo de Datos Corregido (ADR-011)

```php
// ORDER
{
  // Precios del catálogo (IVA incluido)
  subtotal_gross: number    // Suma de (unit_price × quantity)
  discount_amount: number   // Descuentos aplicados
  
  // Desglose tributario (calculado)
  net_amount: number        // = gross / 1.19 (redondeado)
  tax_amount: number        // = gross - net
  
  // Total de la venta (IVA incluido)
  grand_total: number       // = gross - discount
  
  // Propina (separada, no afecta IVA)
  tip_amount: number        // Opcional, no tributaria
  
  // Total a cobrar al cliente
  amount_due: number        // = grand_total + tip
}

Estrategia de Migración
Agregar campos nuevos a tabla orders:
subtotal_gross (DECIMAL 14,2)
net_amount (DECIMAL 14,2)
tip_amount (DECIMAL 14,2, default 0)
amount_due (DECIMAL 14,2)
Backfill de datos existentes (79 orders):

UPDATE orders SET
  subtotal_gross = total,  -- El total actual ya incluye el tax
  net_amount = ROUND(total / 1.19, 2),
  tax_amount = total - ROUND(total / 1.19, 2),
  tip_amount = 0,
  amount_due = total;

Refactor de servicios:
Order::recalculateTotals() usar modelo bruto
OrderItem::saving() interpretar unit_price_snapshot como bruto
TaxType::calculate() aceptar parámetro $isGrossBase
PaymentLedgerService usar nuevos campos
RefundService usar nuevos campos
Mantener campos legacy temporalmente:
subtotal → renombrar a subtotal_gross en lógica
total → renombrar a grand_total en lógica
Eliminar en futura migración después de validar estabilidad
Consecuencias
Positivas
✅ Cumplimiento con normativa SII (precios IVA incluido)
✅ Alineación frontend ↔ backend
✅ DTE emitidos con datos correctos
✅ Asientos contables reflejan realidad
✅ Propinas separadas del IVA (correcto según SII)

Negativas
⚠️ Requiere migración de datos existentes
⚠️ Requiere actualizar 210 tests financieros
⚠️ Breaking change en contratos de API (si hay clientes externos)
Alternativas Consideradas
Opción A: Mantener modelo NETO (rechazada)
❌ Ilegal según SII
❌ Doble imposición
❌ Inconsistencia frontend ↔ backend
Opción B: Adapter/Wrapper (rechazada)
❌ Complejidad adicional
❌ Mantiene deuda técnica
❌ No resuelve problema raíz
Opción C: Migración directa a BRUTO (aceptada)
✅ Cumple normativa
✅ Elimina deuda técnica
✅ Alinea todas las capas
Validación
Tests a Crear
test_chilean_gross_price_tax_calculation: $10,000 bruto → net=8403, tax=1597
test_tip_separate_from_iva_in_ledger: Propina va a cuenta 2200
test_discount_applied_before_tax_split: Descuento reduce el bruto
test_partial_payment_proportional_ledger: Pago parcial distribuye proporcionalmente
test_refund_reverses_proportionally: Reembolso revierte proporcionalmente
test_dte_emitted_with_correct_amounts: DTE usa net=8403, tax=1597
test_cash_session_expected_calculation: Expected incluye solo efectivo real
test_journal_entry_always_balanced: SUM(debits) == SUM(credits) siempre
Implementación
Archivos a Modificar
database/migrations/2026_09_15_000004_add_chilean_model_fields_to_orders.php (nuevo)
app/Modules/Orders/Domain/Entities/Order.php
app/Modules/Orders/Domain/Entities/OrderItem.php
app/Modules/Tax/Domain/ValueObjects/TaxType.php
app/Modules/Payments/Domain/Services/PaymentLedgerService.php
app/Modules/Payments/Domain/Services/RefundService.php
tests/Feature/FinancialIntegrityTest.php (nuevo)
Referencias
ADR-011: Modelo de montos para POS chileno
ADR-010: Money type integer CLP
Normativa SII: Precios deben mostrarse con IVA incluido
Changelog
Fecha	Versión	Cambios
2026-09-14	1.0	Versión inicial
