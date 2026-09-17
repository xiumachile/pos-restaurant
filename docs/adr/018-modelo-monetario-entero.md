# ADR-018: Modelo Monetario Entero (Reemplaza ADR-010)

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Reemplaza**: ADR-010  
**Decisión**: Todos los montos monetarios se almacenan y procesan como enteros (pesos CLP)

## Contexto

El sistema actual tiene inconsistencias críticas en el manejo de dinero:

1. **ADR-010 proponía INTEGER en SQLite**, pero la implementación usa REAL (float)
2. **PHP usa float** con comparaciones de epsilon (`$amount > $available + 0.01`)
3. **No hay Value Object Money** en uso activo (existe pero es dead code)
4. **Reglas de redondeo implícitas** (PHP defaults, no documentadas)
5. **Sin garantía de equivalencia** entre PostgreSQL ↔ PHP ↔ SQLite ↔ TypeScript

Esto genera riesgos en:
- IVA con montos pequeños (redondeo no documentado)
- Split bill (remanente no asignado explícitamente)
- Propinas con porcentajes (redondeo inconsistente)
- Conciliación de caja (diferencias acumuladas)
- DTE/SII (errores de redondeo = rechazo)

## Decisión

**Todos los montos monetarios son enteros (pesos CLP, sin centavos).**

### Capas

| Capa | Tipo | Justificación |
|------|------|---------------|
| **PostgreSQL** | `INTEGER` o `BIGINT` | Precisión exacta, sin redondeos |
| **SQLite** | `INTEGER` | Nativo, sin pérdida de precisión |
| **PHP** | `int` (encapsulado en Money VO) | Operaciones exactas |
| **TypeScript** | `number` (entero validado) | JS number soporta enteros hasta 2^53 |
| **Display** | Formateado con separadores | `$12.990` (CLP: 0 decimales) |

### Ejemplo
$12.990 CLP:
PostgreSQL: 12990 (INTEGER)
SQLite: 12990 (INTEGER)
PHP: Money(12990)
TypeScript: 12990 (number)
Display: "$12.990"

## Reglas de Redondeo

### 1. IVA Incluido (CLP 19%)

```php
// Cálculo de neto desde bruto
$gross = 999;  // $999 CLP
$net = (int) round($gross / 1.19);  // = 839
$tax = $gross - $net;  // = 160

// Verificación: 839 + 160 = 999 ✅

Regla: round() con HALF_UP (default de PHP)
2. Propinas con Porcentaje
// Propina 10% de $25.347
$amount = 25347;
$tip = (int) round($amount * 0.10);  // = 2535

// Propina 15% de $25.347
$tip = (int) round($amount * 0.15);  // = 3802

Regla: round() con HALF_UP
3. Split Bill con Remanente
// $25.000 / 3 partes
$total = 25000;
$parts = 3;
$base = (int) floor($total / $parts);  // = 8333
$remainder = $total % $parts;  // = 1

$bills = [];
for ($i = 0; $i < $parts; $i++) {
    $bills[] = $base + ($i < $remainder ? 1 : 0);
}
// Resultado: [8334, 8333, 8333]
// Suma: 25000 ✅

Regla: floor() + asignar remanente a primeras bills
4. Descuentos
// Descuento 15% de $12.990
$amount = 12990;
$discount = (int) round($amount * 0.15);  // = 1949
$final = $amount - $discount;  // = 11041

Regla: round() con HALF_UP
Value Object Money
class Money {
    private int $cents;
    
    public function __construct(int $cents) {
        $this->cents = $cents;
    }
    
    public static function fromCents(int $cents): self {
        return new self($cents);
    }
    
    public static function fromString(string $amount): self {
        // "12990.00" → 12990
        return new self((int) round((float) $amount));
    }
    
    public function add(Money $other): Money {
        return new self($this->cents + $other->cents);
    }
    
    public function subtract(Money $other): Money {
        return new self($this->cents - $other->cents);
    }
    
    public function multiplyBy(int $factor): Money {
        return new self($this->cents * $factor);
    }
    
    public function divideBy(int $parts): array {
        $base = (int) floor($this->cents / $parts);
        $remainder = $this->cents % $parts;
        
        $result = [];
        for ($i = 0; $i < $parts; $i++) {
            $result[] = new self($base + ($i < $remainder ? 1 : 0));
        }
        
        return $result;
    }
    
    public function equals(Money $other): bool {
        return $this->cents === $other->cents;
    }
    
    public function isGreaterThan(Money $other): bool {
        return $this->cents > $other->cents;
    }
    
    public function isZero(): bool {
        return $this->cents === 0;
    }
    
    public function toCents(): int {
        return $this->cents;
    }
    
    public function toString(): string {
        return number_format($this->cents, 0, '', '');
    }
    
    public function toFloat(): float {
        return (float) $this->cents;
    }
    
    // Helpers estáticos para cálculos comunes
    public static function calculateTax(int $gross, float $rate = 0.19): Money {
        $net = (int) round($gross / (1 + $rate));
        $tax = $gross - $net;
        return new self($tax);
    }
    
    public static function calculateTip(int $amount, float $percentage): Money {
        $tip = (int) round($amount * $percentage);
        return new self($tip);
    }
}

Implementación
Paso 1: Migración de Schema
PostgreSQL:
-- Migración: DECIMAL(14,2) → INTEGER
ALTER TABLE orders 
  ALTER COLUMN subtotal_gross TYPE INTEGER USING subtotal_gross::INTEGER,
  ALTER COLUMN net_amount TYPE INTEGER USING net_amount::INTEGER,
  ALTER COLUMN tax_amount TYPE INTEGER USING tax_amount::INTEGER,
  ALTER COLUMN tip_amount TYPE INTEGER USING tip_amount::INTEGER,
  ALTER COLUMN amount_due TYPE INTEGER USING amount_due::INTEGER;

-- Repetir para: bills, payments, cash_sessions, journal_entries

SQLite:
-- Migración: REAL → INTEGER
-- SQLite no soporta ALTER COLUMN, hay que recrear tabla
CREATE TABLE local_orders_new (
  local_uuid TEXT PRIMARY KEY,
  -- ... otros campos
  subtotal_gross INTEGER NOT NULL,
  net_amount INTEGER NOT NULL,
  tax_amount INTEGER NOT NULL,
  tip_amount INTEGER NOT NULL,
  amount_due INTEGER NOT NULL
);

INSERT INTO local_orders_new 
SELECT *, CAST(subtotal_gross AS INTEGER) as subtotal_gross, ...
FROM local_orders;

DROP TABLE local_orders;
ALTER TABLE local_orders_new RENAME TO local_orders;

Paso 2: Refactor de Servicios
// ANTES
class PaymentService {
    public function registerPayment(
        Order $order,
        float $amount,
        float $tipAmount = 0
    ): Payment {
        if ($amount > $available + 0.01) {
            throw new PaymentException('Insufficient funds');
        }
    }
}

// DESPUÉS
class PaymentService {
    public function registerPayment(
        Order $order,
        Money $amount,
        Money $tipAmount = null
    ): Payment {
        $tipAmount = $tipAmount ?? Money::fromCents(0);
        $available = $this->getAvailableAmount($order);
        
        if ($amount->isGreaterThan($available)) {
            throw new PaymentException('Insufficient funds');
        }
    }
    
    private function getAvailableAmount(Order $order): Money {
        $amountDue = Money::fromCents($order->amount_due);
        $paidAmount = Payment::where('order_id', $order->id)
            ->get()
            ->reduce(
                fn($sum, $p) => $sum->add(Money::fromCents($p->total_amount)),
                Money::fromCents(0)
            );
        
        return $amountDue->subtract($paidAmount);
    }
}

Paso 3: Frontend TypeScript
// src/utils/money.ts
export class Money {
  private constructor(private readonly cents: number) {}
  
  static fromCents(cents: number): Money {
    if (!Number.isInteger(cents)) {
      throw new Error('Money must be an integer');
    }
    return new Money(cents);
  }
  
  static fromApi(value: number): Money {
    // API retorna número, convertir a entero
    return Money.fromCents(Math.round(value));
  }
  
  add(other: Money): Money {
    return Money.fromCents(this.cents + other.cents);
  }
  
  subtract(other: Money): Money {
    return Money.fromCents(this.cents - other.cents);
  }
  
  equals(other: Money): boolean {
    return this.cents === other.cents;
  }
  
  toCents(): number {
    return this.cents;
  }
  
  format(): string {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP',
      minimumFractionDigits: 0,
    }).format(this.cents);
  }
}

// Uso en componentes
const amount = Money.fromApi(payment.amount);
const tip = Money.fromApi(payment.tip_amount);
const total = amount.add(tip);

console.log(total.format());  // "$12.990"

Consecuencias
Positivas
✅ Precisión exacta: Sin errores de punto flotante
✅ Sin epsilon: Comparaciones directas (=== en lugar de abs(a - b) < 0.01)
✅ Consistencia: Mismo tipo en todas las capas
✅ Auditoría: Cálculos reproducibles y verificables
✅ SII/DTE: Cumplimiento normativo garantizado
✅ Split bill: Remanente asignado explícitamente
Negativas
⚠️ Migración compleja: Requiere convertir datos existentes
⚠️ Refactor masivo: PaymentService, BillingService, LedgerService, etc.
⚠️ Frontend: Actualizar tipos y validaciones
⚠️ Tests: Actualizar todos los tests que usan floats

Mitigaciones
1. Migración: Backup completo + script de rollback
2. Refactor: Incremental, con tests después de cada fase
3. Frontend: Helpers de conversión temporal
4. Tests: Property-based tests para casos de borde
Validación
Tests de Precisión Requeridos

test('IVA con monto pequeño', function () {
    $gross = 999;
    $tax = Money::calculateTax($gross, 0.19);
    expect($tax->toCents())->toBe(160);  // 999 - 839
});

test('split bill con remanente', function () {
    $total = Money::fromCents(25000);
    $bills = $total->divideBy(3);
    
    expect($bills)->toHaveCount(3);
    expect($bills[0]->toCents())->toBe(8334);
    expect($bills[1]->toCents())->toBe(8333);
    expect($bills[2]->toCents())->toBe(8333);
    
    $sum = $bills[0]->add($bills[1])->add($bills[2]);
    expect($sum->toCents())->toBe(25000);
});

test('propina con porcentaje', function () {
    $amount = 25347;
    $tip = Money::calculateTip($amount, 0.10);
    expect($tip->toCents())->toBe(2535);
});

test('equivalencia entre capas', function () {
    $amount = Money::fromCents(12990);
    
    // Guardar en DB
    $payment = Payment::create(['amount' => $amount->toCents()]);
    
    // Recuperar de DB
    $recovered = Money::fromCents($payment->amount);
    
    expect($amount->equals($recovered))->toBeTrue();
});

Referencias
ADR-010 (reemplazado)
ADR-011 (complementario)
Money.php
PaymentService.php
Decisión: Aceptada
Fecha: Septiembre 2026
Responsable: Equipo POS Restaurant
