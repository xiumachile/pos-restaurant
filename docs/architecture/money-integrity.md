# Integridad Monetaria (ADR-018 Addendum)

## Contexto

El sistema usa **INTEGER** para representar dinero en CLP (pesos chilenos, sin decimales).

## Problemas detectados (2026-09-21)

### P0: Inconsistencia en `bills.paid_amount`

**Síntoma**: 56 bills tenían `paid_amount = 2 × total`, causando `remaining_amount` negativo.

**Causa**: Bug histórico en seeder/listener que duplicaba el monto al actualizar `paid_amount`.
Los payments individuales eran correctos (amount = total), pero el aggregate estaba mal.

**Solución**: Migración recalcula `paid_amount = SUM(payments.amount WHERE status=completed)`
usando la tabla `payments` como fuente de verdad.

### P1: Falta de constraints de integridad

La migración `2026_09_17_000001_convert_decimal_to_integer` convertía DECIMAL→INTEGER
sin validar que los datos fueran enteros válidos. Era robusta ante errores SQL pero no
fail-safe ante pérdida de datos.

**Solución**: CHECK CONSTRAINTS `(column >= 0)` en columnas monetarias non-negative.

## Migración de integridad (2026-09-21)

`database/migrations/2026_09_21_000002_add_money_integrity_constraints.php`

### FASE 1: Corrección de datos

```sql
-- Recalcula paid_amount desde payments (fuente de verdad)
UPDATE bills b
SET paid_amount = subq.sum,
    remaining_amount = b.total - subq.sum
FROM (
    SELECT bill_id, SUM(amount) as sum
    FROM payments
    WHERE status = 'completed'
    GROUP BY bill_id
) subq
WHERE b.id = subq.bill_id
  AND b.paid_amount <> subq.sum;

FASE 2: CHECK CONSTRAINTS
Agrega CHECK (column >= 0) a estas columnas:

Tabla	Columnas
orders	subtotal, total, tax_amount, tip_amount, discount_amount
order_items	unit_price_snapshot, subtotal, tax_amount
bills	subtotal, total, tax_amount, tip_amount, discount_amount, paid_amount
payments	amount, tip_amount, total_amount
cash_sessions	opening_amount, closing_amount, expected_amount
cash_movements	amount
cash_counts	card/cash/counted/expected/other/transfer_amount
refunds	amount
tip_payouts	amount
ledger_entries	debit_amount, credit_amount

Columnas EXCLUIDAS (pueden ser negativas por diseño)
bills.remaining_amount: sobrepagos permitidos (cliente paga más del total)
cash_sessions.difference: faltante/sobrante de caja
cash_counts.difference: faltante/sobrante de caja
cash_movements.balance_after: depende del flujo
journal_entries: NO tiene columna amount (monto está en ledger_entries)
Patrón fail-safe para futuras migraciones

public function up(): void
{
    // 1. Verificar datos actuales
    $negatives = DB::table('orders')->where('total', '<', 0)->count();
    if ($negatives > 0) {
        throw new \RuntimeException("Hay {$negatives} valores negativos. Corregir primero.");
    }
    
    // 2. Agregar constraint
    DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_total_non_negative CHECK (total >= 0)');
}

Monitoreo
Violaciones de constraints aparecen como errores check_violation en PostgreSQL.
Son indicadores de bugs en código, no de datos inválidos. Configurar alertas.
Referencias
ADR-018: Money and Tax Architecture
Migración: 2026_09_21_000002_add_money_integrity_constraints.php
