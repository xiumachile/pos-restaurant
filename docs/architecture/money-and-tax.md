# Modelo Monetario y Tributario

**Última actualización**: 2026-09-18  
**Estado**: Implementado y validado  
**ADRs relacionados**: ADR-011, ADR-018, ADR-019, ADR-022

---

## Decisión Arquitectónica: CLP Entero (Sin Decimales)

El sistema POS utiliza **pesos chilenos (CLP) como enteros** en toda la cadena de procesamiento, desde la base de datos hasta la capa de presentación.

### Justificación

1. **CLP no tiene decimales**: El peso chileno no utiliza centavos en transacciones reales
2. **Evita errores de precisión**: La aritmética de punto flotante puede causar bugs sutiles (ej: `0.1 + 0.2 !== 0.3`)
3. **Consistencia end-to-end**: Frontend y backend usan el mismo modelo de datos
4. **Performance**: Operaciones con enteros son más rápidas que con floats

---

## Implementación por Capa

### Base de Datos

#### PostgreSQL / MySQL (Backend)
```sql
-- Columnas monetarias como INTEGER
amount INTEGER NOT NULL DEFAULT 0,
tip_amount INTEGER NOT NULL DEFAULT 0,
total_amount INTEGER NOT NULL DEFAULT 0,
subtotal INTEGER NOT NULL DEFAULT 0,
tax_amount INTEGER NOT NULL DEFAULT 0,
discount_amount INTEGER NOT NULL DEFAULT 0,
grand_total INTEGER NOT NULL DEFAULT 0,
paid_amount INTEGER NOT NULL DEFAULT 0,
remaining_amount INTEGER NOT NULL DEFAULT 0

SQLite (Frontend - Tauri)

-- Columnas monetarias como INTEGER
amount INTEGER NOT NULL DEFAULT 0,
tip_amount INTEGER NOT NULL DEFAULT 0,
total_amount INTEGER NOT NULL DEFAULT 0,
subtotal INTEGER NOT NULL DEFAULT 0,
tax_amount INTEGER NOT NULL DEFAULT 0,
discount_amount INTEGER NOT NULL DEFAULT 0,
grand_total INTEGER NOT NULL DEFAULT 0,
paid_amount INTEGER NOT NULL DEFAULT 0,
remaining_amount INTEGER NOT NULL DEFAULT 0

Backend (PHP/Laravel)
Casts en Eloquent Models

protected $casts = [
    'amount' => 'integer',
    'tip_amount' => 'integer',
    'total_amount' => 'integer',
    'subtotal' => 'integer',
    'tax_amount' => 'integer',
    'discount_amount' => 'integer',
    'grand_total' => 'integer',
    'paid_amount' => 'integer',
    'remaining_amount' => 'integer',
];

Cálculos de Totales

// Payment.php
public static function calculateTotal(int $amount, int $tipAmount = 0): int
{
    return $amount + $tipAmount;  // Sin rounding, sin aritmética flotante
}

// Bill.php
public function registerPaymentAmount(int $amount): void
{
    $this->paid_amount = (int) $this->paid_amount + $amount;
    $this->remaining_amount = max(0, (int) $this->total - (int) $this->paid_amount);
    
    if ($this->isFullyPaid()) {
        $this->status = BillStatus::PAID;
    }
}

Comparaciones Exactas (Sin Epsilon)

// ✅ CORRECTO: Comparación exacta
if ($totalPaid >= $amountDue) {
    $order->status = OrderStatus::PAID;
}

// ❌ INCORRECTO: Comparación con tolerancia
if (abs($totalPaid - $amountDue) < 0.01) {  // NO USAR
    $order->status = OrderStatus::PAID;
}

Frontend (TypeScript/React)
Tipos de Datos

interface Payment {
  amount: number;        // Entero en CLP
  tip_amount: number;    // Entero en CLP
  total_amount: number;  // Entero en CLP
}

interface Bill {
  subtotal: number;      // Entero en CLP
  tax_amount: number;    // Entero en CLP
  total: number;         // Entero en CLP
  paid_amount: number;   // Entero en CLP
  remaining_amount: number;  // Entero en CLP
}

Cálculos

// ✅ CORRECTO: Operaciones con enteros
const totalAmount = payment.amount + payment.tip_amount;
const remaining = bill.total - bill.paid_amount;

// ❌ INCORRECTO: Redondeo innecesario
const totalAmount = Math.round(payment.amount + payment.tip_amount);  // NO USAR

Modelo Tributario Chileno (IVA 19%)
Cálculo de IVA desde Precio Bruto
En Chile, los precios se muestran con IVA incluido. El sistema calcula el neto y el IVA de la siguiente manera:

Precio Bruto (con IVA): $10.000 CLP
Neto = round(10.000 / 1.19) = $8.403 CLP
IVA = 10.000 - 8.403 = $1.597 CLP

Nota: El redondeo se aplica solo una vez al final, no en cada paso intermedio.
Implementación

// Order.php - recalculateTotals()
public function recalculateTotals(): void
{
    // subtotal_gross = suma de items.subtotal (IVA incluido)
    $this->subtotal_gross = $this->items()->sum('subtotal');
    
    // net_amount = round(subtotal_gross / 1.19)
    $this->net_amount = (int) round($this->subtotal_gross / 1.19);
    
    // tax_amount = subtotal_gross - net_amount (conserva invariante)
    $this->tax_amount = (int) ($this->subtotal_gross - $this->net_amount);
    
    // grand_total = subtotal_gross - discount_amount
    $this->grand_total = $this->subtotal_gross - ($this->discount_amount ?? 0);
    
    // amount_due = grand_total + tip_amount
    $this->amount_due = $this->grand_total + ($this->tip_amount ?? 0);
}

Invariante Financiera

net_amount + tax_amount = subtotal_gross

Esta invariante se mantiene exacta (sin errores de redondeo acumulados) porque:
1. net_amount se redondea una sola vez
2. tax_amount se calcula como la diferencia exacta

Flujo de Pagos con Propinas
Semántica de Campos
Campo	Significado	Ejemplo
amount	Monto de venta (sin propina)	$10.000
tip_amount	Propina	$1.000
total_amount	Total cobrado (venta + propina)	$11.000

Sincronización Frontend → Backend
// Frontend envía:
{
  order_uuid: "...",
  amount: 10000,        // Venta (sin propina)
  tip_amount: 1000,     // Propina
  idempotency_key: "..."
}

// Backend recibe y calcula:
$payment = Payment::create([
  'amount' => 10000,
  'tip_amount' => 1000,
  'total_amount' => Payment::calculateTotal(10000, 1000)  // 11000
]);

Semántica de Cierre de Order
Una order se marca como PAID cuando:
$totalPaid = Payment::where('order_id', $order->id)
    ->completed()
    ->sum('amount') + Payment::sum('tip_amount');

if ($totalPaid >= $order->amount_due) {
    $order->status = OrderStatus::PAID;
}

Donde:

amount_due = grand_total + tip_amount

Lo Que NO Hacemos
❌ No usamos DECIMAL(14,2)
-- ❌ INCORRECTO
amount DECIMAL(14,2) NOT NULL DEFAULT 0.00

❌ No usamos REAL en SQLite
-- ❌ INCORRECTO
amount REAL NOT NULL DEFAULT 0.0

❌ No usamos float en PHP
// ❌ INCORRECTO
protected $casts = [
    'amount' => 'float',
];

public function calculateTotal(float $amount, float $tipAmount): float
{
    return round($amount + $tipAmount, 2);
}

❌ No usamos tolerancia en comparaciones

// ❌ INCORRECTO
if (abs($paidAmount - $orderTotal) < 0.01) {
    // ...
}

Ejemplos de Cálculos
Ejemplo 1: Venta Simple

Items:
  - Hamburguesa: $5.000 (IVA incluido)
  - Bebida: $2.000 (IVA incluido)

Subtotal Gross: $7.000
Net Amount: round(7000 / 1.19) = $5.882
Tax Amount: 7000 - 5882 = $1.118
Grand Total: $7.000
Amount Due: $7.000 (sin propina)

Ejemplo 2: Venta con Propina

Subtotal Gross: $10.000
Net Amount: round(10000 / 1.19) = $8.403
Tax Amount: 10000 - 8403 = $1.597
Grand Total: $10.000
Tip Amount: $1.000
Amount Due: 10000 + 1000 = $11.000

Payment:
  amount: $10.000 (venta)
  tip_amount: $1.000 (propina)
  total_amount: $11.000
  
Ejemplo 3: Split Bill

Order Total: $20.000

Bill 1:
  items: [item1, item2]
  subtotal: $12.000
  tax_amount: 12000 - round(12000/1.19) = $1.840
  total: $12.000

Bill 2:
  items: [item3, item4]
  subtotal: $8.000
  tax_amount: 8000 - round(8000/1.19) = $1.232
  total: $8.000

Verificación: 12000 + 8000 = 20000 ✅

Validación y Testing
Tests de Integridad Financiera

// FinancialIntegrityTest.php
test('Order.amount_due == suma de payments completados + tip', function () {
    $order = Order::factory()->create(['amount_due' => 10000]);
    
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 8000,
        'tip_amount' => 1000,
        'status' => PaymentStatus::COMPLETED,
    ]);
    
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'tip_amount' => 0,
        'status' => PaymentStatus::COMPLETED,
    ]);
    
    $totalPaid = Payment::where('order_id', $order->id)
        ->completed()
        ->sum('amount') + Payment::sum('tip_amount');
    
    expect($totalPaid)->toBe($order->amount_due);  // 10000
});

Validación de Invariantes

test('Bill.paid_amount + remaining_amount = total', function () {
    $bill = Bill::factory()->create(['total' => 10000, 'paid_amount' => 0]);
    
    $bill->registerPaymentAmount(3000);
    expect($bill->paid_amount + $bill->remaining_amount)->toBe($bill->total);
    
    $bill->registerPaymentAmount(5000);
    expect($bill->paid_amount + $bill->remaining_amount)->toBe($bill->total);
});

Referencias
ADR-011: Modelo de Montos Chile (enteros, no decimales)
ADR-018: Integridad Financiera en Backend (sin floats, sin epsilon)
ADR-019: Contrato de sincronización de propinas
ADR-022: Contract Freeze Validado

Historial de Cambios
2026-09-18: Actualización completa para reflejar modelo CLP entero
Eliminado: DECIMAL(14,2), REAL, float, ROUND(..., 2), tolerancia de $0.01
Agregado: INTEGER, int, comparación exacta, ejemplos con enteros
Commits: d7d4a53, 28e9f40, fee8fcf, d82e061
2025-XX-XX: Versión inicial (modelo decimal, ahora obsoleta)
