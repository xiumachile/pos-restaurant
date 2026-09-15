
# Money and Tax Architecture

**Estado**: ✅ Aceptado
**Fecha**: 2026-09-07
**Rama**: `hardening/money-normalization`

---

## 🎯 Principios arquitectónicos

1. **Configurabilidad total**: Ningún valor monetario o impositivo está hardcodeado
2. **Single source of truth**: Tabla `taxes` + `Company.settings.currency`
3. **Multi-impuesto**: Soporta IVA, exentos, tasas reducidas, impuestos fijos
4. **Snapshots históricos**: Cambios de tasa NO afectan registros pasados (cumplimiento fiscal)
5. **Cascada de impuestos**: product → category → company default → legacy

---

## 💰 Representación de Money

### Capa | Tipo | Justificación
|-------|------|---------------|
| **PostgreSQL** | `DECIMAL(14, 2)` | Precisión exacta para montos financieros |
| **SQLite (frontend)** | `REAL` | Compatibilidad con SQLite (sin DECIMAL) |
| **PHP** | `float` | Suficiente para decimales financieros (< 2^53) |
| **TypeScript** | `number` | Nativo de JavaScript |
| **Display** | Formateado | Según `Company.settings.currency` |

### Ejemplo: `$12.990 CLP`
- **DB**: `12990.00` (DECIMAL)
- **PHP**: `12990.0` (float)
- **Display**: `$12.990` (formateado según config CLP: 0 decimales, separador `.`)

### Ejemplo: `$12.99 USD`
- **DB**: `12.99` (DECIMAL)
- **PHP**: `12.99` (float)
- **Display**: `$12.99` (formateado según config USD: 2 decimales, separador `.`)

---

## 💱 Configuración de Moneda (Company.settings.currency)

Almacenada en el JSON `settings` de la entidad `Company`:

```json
{
  "currency": {
    "code": "CLP",
    "symbol": "$",
    "decimals": 0,
    "thousands_separator": ".",
    "decimal_separator": ","
  }
}
Campos
code: Código ISO 4217 (CLP, USD, EUR, etc.)
symbol: Símbolo visual ($, €, £)
decimals: Cantidad de decimales (CLP=0, USD=2)
thousands_separator: Separador de miles
decimal_separator: Separador decimal
Defaults por país
Chile (CLP): 0 decimales, separador . miles, , decimal
USA (USD): 2 decimales, separador , miles, . decimal
Europa (EUR): 2 decimales, separador . miles, , decimal
UI: Editable en /admin/company/settings (settings generales de empresa)
🧾 Sistema de Impuestos
Estructura
┌─────────────────────────────────────────────────────┐
│  Tax (tabla taxes)                                  │
│  - company_id: foreign key                          │
│  - name: "IVA 19%"                                  │
│  - code: "IVA" (para SII)                           │
│  - type: percent | fixed | exempt                   │
│  - rate: 19.0000 (DECIMAL 10,4)                     │
│  - is_default: boolean                              │
│  - is_active: boolean                               │
│  - valid_from / valid_until (opcional)              │
└─────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────┐
│  Product / Category                                 │
│  - tax_id: foreign key nullable                     │
└─────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────┐
│  OrderItem (snapshots históricos)                   │
│  - tax_amount: DECIMAL(14,2)                        │
│  - tax_rate_snapshot: DECIMAL(10,4)                 │
│  - tax_name_snapshot: string                        │
└─────────────────────────────────────────────────────┘
Cascada de resolución de impuesto
Product::getEffectiveTax() resuelve en este orden:
product.tax_id: Si el producto tiene impuesto específico
category.tax_id: Si la categoría tiene impuesto por defecto
company default: Tax::where('is_default', true)->where('company_id', $companyId)
Legacy product.tax_rate: Fallback para productos antiguos sin tax_id
Tipos de impuesto (TaxType enum)
percent: Porcentaje sobre neto (IVA 19%)
fixed: Monto fijo por unidad (ej: $500 por cigarrillo)
exempt: Exento de impuesto
Snapshots históricos (CRÍTICO)
Regla: Una vez que un OrderItem se crea, sus snapshots NO se modifican.
Por qué: Si el IVA cambia del 19% al 21%, los orders antiguos deben mantener el 19% para:
DTEs emitidos históricamente (boletas/facturas)
Reportes fiscales del SII
Auditoría contable
Cómo se implementa:
Al crear OrderItem, se guarda tax_rate_snapshot con la tasa efectiva actual
El evento saving() de OrderItem NO sobrescribe si ya hay snapshot
DteType::taxRateFromOrder() lee del snapshot, NO de la tabla taxes
🧮 Cálculo de Totales
OrderItem
subtotal = unit_price_snapshot * quantity
tax_amount = Tax::calculate(subtotal, quantity, rate)  // según TaxType
total_line = subtotal + tax_amount
Order
subtotal = sum(items.subtotal)
tax_amount = sum(items.tax_amount)
discount_amount = ... (según reglas de descuento)
total = subtotal + tax_amount - discount_amount
Bill
total = sum(orders.total) de orders asignados
paid_amount = sum(payments.amount)
remaining_amount = total - paid_amount
🖨️ Formateo de Display
Servicio MoneyFormatter
class MoneyFormatter
{
    public function format(float $amount, Company $company): string
    {
        $currency = $company->settings['currency'] ?? self::defaults();
        return number_format(
            $amount,
            $currency['decimals'],
            $currency['decimal_separator'],
            $currency['thousands_separator']
        );
    }
    
    public function formatWithSymbol(float $amount, Company $company): string
    {
        $symbol = $company->settings['currency']['symbol'] ?? '$';
        return $symbol . ' ' . $this->format($amount, $company);
    }
}
Ejemplos
MoneyFormatter::format(12990, CLP_company) → "12.990"
MoneyFormatter::format(12.99, USD_company) → "12.99"
MoneyFormatter::formatWithSymbol(12990, CLP_company) → "$ 12.990"
Uso en ReceiptFormatter
$taxName = $this->getTaxNameFromOrder($order);  // "IVA"
$taxRate = $this->getTaxRateFromOrder($order);  // "19%"
$output .= "{$taxName} ({$taxRate}): " . $this->formatCurrency($tax);
📋 Migración de datos existentes
Productos legacy sin tax_id
La cascada de getEffectiveTax() resuelve automáticamente vía product.tax_rate legacy
No se requiere migración manual
Órdenes existentes
Ya tienen tax_rate_snapshot poblado
No se requieren cambios
Companies sin currency en settings
MoneyFormatter usa defaults de Chile (CLP, 0 decimales, . miles, , decimal)
No rompe compatibilidad
🧪 Tests requeridos
MoneyFormatter
✅ Formatea CLP sin decimales
✅ Formatea USD con 2 decimales
✅ Formatea EUR con coma decimal
✅ Usa defaults si no hay config
DteType flexible
✅ DTE usa snapshot histórico (19%)
✅ DTE usa snapshot histórico (21% si se cambió tasa antes)
✅ Fallback a 0.19 si no hay items
ReceiptFormatter flexible
✅ Muestra "IVA (19%)" por defecto
✅ Muestra "IVA (21%)" si tasa cambió
✅ Muestra "Exento" para productos exentos
✅ Formatea según moneda de la empresa
🎯 Decisiones arquitecturales
Decisión
Justificación
Snapshots no se reescriben
Cumplimiento fiscal SII, auditoría
Currency en JSON, no columnas
Flexibilidad sin migraciones por cada campo
DECIMAL(14,2) en PostgreSQL
Precisión financiera estándar
float en PHP
Suficiente para montos reales
Cascada product → category → default
Flexibilidad de configuración jerárquica
📝 Archivos relevantes
app/Modules/Tax/Domain/Entities/Tax.php
app/Modules/Catalog/Domain/Entities/Product.php (getEffectiveTax())
app/Modules/Orders/Domain/Entities/OrderItem.php (snapshots)
app/Modules/Companies/Domain/Entities/Company.php (settings.currency)
app/Shared/Domain/Services/MoneyFormatter.php (nuevo)
app/Modules/Fiscal/Domain/ValueObjects/DteType.php (taxRateFromOrder)
app/Modules/Printers/Domain/Services/ReceiptFormatter.php (dinámico)
Autor: Arquitectura WokMesa
Revisado por: [Pendiente]
cd ~/pos-restaurant

## Bills offline

### Decisiones arquitectónicas

Las `local_bills` creadas offline son **solo para tracking local** y NO se sincronizan al backend.

**Razón:**
- El endpoint `POST /billing/payments` acepta `bill_uuid` como opcional (nullable)
- El backend NO crea bills automáticamente al recibir payments
- Las bills solo se crean en backend vía `POST /orders/{uuid}/split` (split explícito)

**Caso de uso:**
- UI puede mostrar bills abiertas offline
- Calcular `remaining_amount` localmente
- Tracking de pagos parciales offline
- Soporte para split bills en UI local

**Limitación:**
Si el usuario hace split offline (múltiples bills), el backend solo verá un payment único al order completo.

**Workaround:**
Si se requiere split, el usuario debe esperar a tener conexión y hacer el split online vía `POST /orders/{uuid}/split`.

### Flujo de sincronización
Offline (SQLite):
local_orders → local_order_items → local_bills → local_payments
↓
(solo tracking local)
Sync a backend:
local_orders → POST /orders (crear order)
local_order_items → POST /orders/{uuid}/items (agregar items)
local_payments → POST /billing/payments (sin bill_uuid)
Backend (PostgreSQL):
orders → order_items → payments (asociados al order completo)
(NO hay bills a menos que se haga split online)

### Justificación

**Por qué NO sincronizar bills:**

1. **Caso de uso más común**: 90% de pagos son sin split
   - Para estos casos, sincronizar solo payments es suficiente
   - El backend no necesita saber que hubo una bill local

2. **`local_bills` sigue siendo útil para UI**
   - Mostrar bills abiertas offline
   - Calcular `remaining_amount` localmente
   - Tracking de pagos parciales offline

3. **Split offline es un caso edge**
   - La mayoría de restaurantes no hacen split frecuentemente
   - Si necesitan split, pueden esperar a tener conexión
   - Esta limitación está documentada

4. **Simplicidad**
   - No necesitamos implementar `processBill()` en SyncEngine
   - `processPayment()` ya funciona sin cambios
   - Menos código = menos bugs

5. **Extensible en el futuro**
   - Si después necesitamos split offline, podemos agregar `processBill()`
   - Pero por ahora no es prioritario

### Ejemplo de flujo offline completo

**Escenario: Pago sin split (caso común)**

```typescript
// 1. Crear pedido offline
const order = await OrderRepository.create({
  company_id: "company-1",
  branch_id: "branch-1",
  table_id: "table-1",
});

// 2. Agregar items
await OrderRepository.addItem(order.local_uuid, {
  product_id: "prod-1",
  product_name: "Hamburguesa",
  quantity: 2,
  unit_price: 5000,
});

// 3. Crear pago offline (con auto-creación de bill)
const result = await offlinePaymentService.createPaymentOffline({
  orderLocalUuid: order.local_uuid,
  paymentMethod: "cash",
  amount: 11900,
});

// Resultado:
// - local_bill creada (tracking local)
// - local_payment creado
// - order marcado como paid
// - mesa liberada
// - Todo encolado en sync_queue
Escenario: Sincronización
// SyncEngine procesa la cola
await syncEngine.processBatch();

// POST /orders → crea order en backend
// POST /orders/{uuid}/items → agrega items
// POST /billing/payments → crea payment (sin bill_uuid)

// Backend recibe:
// - Order con items
// - Payment asociado al order completo
// - (NO hay bill en backend porque no se hizo split online)
Referencias
SyncEngine: src/services/sync/SyncEngine.ts
processOrder(): sincroniza orders
processPayment(): sincroniza payments (sin bill_uuid)
OfflinePaymentService: src/services/offlinePaymentService.ts
createPaymentOffline(): crea bill + payment localmente
Backend endpoints:
POST /orders: crear order
POST /orders/{uuid}/items: agregar items
POST /billing/payments: crear payment (bill_uuid opcional)
POST /orders/{uuid}/split: crear bills (solo online)

---

## REGLAS FINANCIERAS FORMALES (Puntos 37-45)

### Definiciones Formales

**Subtotal (subtotal_gross)**: Suma de precios de catálogo de todos los items del pedido.
- Incluye IVA (modelo chileno)
- Fórmula: `SUM(items.unit_price × items.quantity)`
- Ejemplo: 2 hamburguesas × $5,000 = $10,000

**Descuento (discount_amount)**: Reducción aplicada al subtotal bruto.
- Se aplica ANTES de calcular IVA
- Fórmula: monto fijo o porcentaje del subtotal
- Ejemplo: $2,000 de descuento

**Neto (net_amount)**: Base imponible sin IVA.
- Fórmula: `ROUND(subtotal_gross / 1.19, 2)`
- Ejemplo: $10,000 / 1.19 = $8,403.36

**IVA (tax_amount)**: Impuesto al valor agregado (19% en Chile).
- Fórmula: `subtotal_gross - net_amount`
- Ejemplo: $10,000 - $8,403.36 = $1,596.64

**Total venta (grand_total)**: Monto total de la venta sin propina.
- Fórmula: `subtotal_gross - discount_amount`
- Ejemplo: $10,000 - $2,000 = $8,000

**Propina (tip_amount)**: Monto adicional opcional del cliente.
- NO forma parte del valor gravado (punto 44)
- Se mantiene separada del valor de venta (punto 45)
- Se incluye en el monto efectivamente recibido
- Ejemplo: $1,000

**Total cobrado (amount_due)**: Monto final a cobrar al cliente.
- Fórmula: `grand_total + tip_amount`
- Ejemplo: $8,000 + $1,000 = $9,000

**Saldo pendiente (remaining_amount)**: Monto faltante por pagar.
- Fórmula: `amount_due - paid_amount`
- Ejemplo: $10,000 - $5,000 = $5,000

**Vuelto**: Diferencia cuando el pago excede el monto debido.
- NO permitido en el sistema (lanza excepción)
- El cliente debe pagar exactamente amount_due o menos

### Reglas de Redondeo (Punto 40)

1. **Precisión**: Todos los cálculos usan 2 decimales
2. **Método**: `ROUND(valor, 2)` (redondeo bancario)
3. **Momento**: Redondeo se aplica DESPUÉS de cada operación
4. **Validación**: `net_amount + tax_amount = subtotal_gross` (con tolerancia de $0.01)

### Reglas de Descuento (Punto 41)

1. **Orden de aplicación**: Descuento se aplica ANTES de calcular IVA
2. **Base imponible**: El descuento reduce la base imponible
3. **Fórmula**: `grand_total = subtotal_gross - discount_amount`
4. **IVA recalculado**: Se calcula sobre el monto después del descuento

### Impuestos por Producto/Categoría/Company (Punto 42)

1. **Jerarquía de impuestos**:
   - Producto tiene `tax_id` → usar ese impuesto
   - Producto no tiene `tax_id` → usar `tax_id` de categoría
   - Categoría no tiene `tax_id` → usar impuesto default de empresa
   
2. **Tipos de impuestos**:
   - `PERCENT`: Porcentaje (ej: IVA 19%)
   - `FIXED`: Monto fijo por unidad (ej: $500 por litro)
   - `EXEMPT`: Exento (0%)

3. **Cálculo**:
   - Percent: `tax = base_amount × (rate / 100)`
   - Fixed: `tax = rate × quantity`
   - Exempt: `tax = 0`

### Pagos Parciales y Múltiples (Punto 43)

1. **Pago parcial**:
   - Permitido: `payment_amount < amount_due`
   - Actualiza `remaining_amount`
   - Order permanece en estado `SERVED`
   
2. **Pago completo**:
   - `payment_amount = amount_due`
   - `remaining_amount = 0`
   - Order transiciona a `PAID`
   
3. **Múltiples pagos**:
   - Permitido: varios payments sobre el mismo order
   - Suma de payments no puede exceder `amount_due`
   - Cada payment tiene su propio `idempotency_key`
   
4. **Split bill**:
   - Order puede tener múltiples bills
   - Cada bill tiene su propio `remaining_amount`
   - Payments se asocian a bills específicos

### Propina (Puntos 44-45)

1. **NO forma parte del valor gravado** (punto 44):
   - Propina no afecta cálculo de IVA
   - Propina no afecta base imponible
   
2. **Separada pero incluida** (punto 45):
   - Se mantiene en campo separado: `tip_amount`
   - Se incluye en `amount_due`: `amount_due = grand_total + tip_amount`
   - Se incluye en `payment.total_amount`: `total_amount = amount + tip_amount`
   
3. **Contabilización**:
   - Propina va a cuenta separada: `TipsPayable (2200)`
   - No se mezcla con ingresos de venta

### Validaciones Obligatorias

1. **Integridad matemática**:
net_amount + tax_amount = subtotal_gross (±$0.01)
grand_total = subtotal_gross - discount_amount
amount_due = grand_total + tip_amount
remaining_amount = amount_due - paid_amount

2. **No sobrepago**:
payment_amount <= remaining_amount

3. **No reembolso excesivo**:
refund_amount <= payment_amount - already_refunded

4. **Idempotencia**:
Mismo idempotency_key → mismo payment (sin duplicados)

### Ejemplo Completo (Punto 39)

**Venta**: Hamburguesa $10,000 + Papas $3,000 + Propina $1,000
Items:
Hamburguesa: $10,000 (IVA incluido)
Papas: $3,000 (IVA incluido)
─────────────────────────────────────
Subtotal: $13,000 (subtotal_gross)
Cálculos:
Neto: $13,000 / 1.19 = $10,924.37
IVA: $13,000 - $10,924.37 = $2,075.63
Grand total: $13,000 (sin descuento)
Propina: $1,000
─────────────────────────────────────
Amount due: $14,000 (total cobrado)
Pago:
Cliente paga: $14,000 en efectivo
Vuelto: $0
Asiento contable:
DEBIT Cash (1100) $14,000
CREDIT Revenue (4100) $10,924.37
CREDIT TaxPayable (2100) $2,075.63
CREDIT TipsPayable (2200) $1,000

### Casos de Prueba Obligatorios (Punto 46)

- [x] Venta de $10,000
- [x] Venta + propina
- [x] Descuento
- [x] Pago parcial
- [x] Pago completo
- [x] Pago con vuelto (rechazado)
- [x] Múltiples pagos
- [x] Split bill
- [x] Redondeo
- [x] Producto exento

**Todos validados en `tests/Feature/FinancialRulesTest.php`**


---

## REGLAS DE BILLING (Puntos 47-56)

### Definición Formal de Bill

**Bill** es la representación financiera consistente de una cuenta a cobrar, derivada de un Order.

**Fuente de verdad**: Bill es una proyección de:
- **Order** (items, precios, impuestos, descuentos)
- **Payments** (pagos efectivos recibidos)

**Fórmula fundamental**:
LOCAL BILL = f(ORDER + PAYMENTS)

### Jerarquía de Entidades
Order (1) ──→ (N) Bill ──→ (N) Payment

- Un Order puede tener 1 o N Bills (split)
- Un Bill puede tener 0 o N Payments (parciales)
- Un Payment puede existir sin Bill (pago directo a Order)

### Campos Financieros de Bill

| Campo | Significado | Fórmula |
|-------|-------------|---------|
| `subtotal` | Base imponible bruta | Copia de Order.subtotal |
| `tax_amount` | IVA incluido | Copia de Order.tax_amount |
| `discount_amount` | Descuento aplicado | Copia de Order.discount_amount |
| `tip_amount` | Propina (separada) | Suma de tips de Payments asociados |
| `total` | Monto a cobrar | subtotal + tax - discount + tip |
| `paid_amount` | Total efectivamente pagado | SUM(Payments.total_amount) |
| `remaining_amount` | Saldo pendiente | total - paid_amount |

### Estados de Bill (BillStatus)
OPEN → PARTIAL → PAID
↓ ↓
CANCELLED

- **OPEN**: `paid_amount = 0`, `remaining_amount = total`
- **PARTIAL**: `0 < paid_amount < total`
- **PAID**: `paid_amount ≥ total` (o `remaining_amount ≤ 0`)
- **CANCELLED**: Bill cancelado (por split o anulación)

### Reglas de Propina en Bill (Punto 50)

**Regla crítica**: La propina se contabiliza UNA sola vez.

**Fuente única de verdad para propinas**: Tabla `payments`.

```sql
-- Reporte correcto (NO duplica)
SELECT SUM(tip_amount) FROM payments WHERE order_id = X;

-- Reporte INCORRECTO (triple contabilidad)
SELECT tip FROM orders + tip FROM bills + tip FROM payments  -- ❌
Uso correcto de registerPaymentAmount():
// Frontend debe pasar TOTAL_AMOUNT (amount + tip), no solo amount
$bill->registerPaymentAmount((float) $payment->total_amount);
Split Bill (3 Modalidades)
Modalidad 1: Split Equal (partes iguales)
$bills = $billingService->splitEqual($order, $parts);
// Requiere: $parts >= 2
// Distribuye subtotal, tax, discount proporcionalmente
// Residuos por redondeo → primera bill
Modalidad 2: Split By Items (cada comensal paga lo suyo)
$bills = $billingService->splitByItems($order, $groups);
// groups = [['item_ids' => [...], 'guest_count' => N], ...]
// Tax y discount proporcionales al subtotal de cada grupo
// Ajuste de redondeo → última bill
Modalidad 3: Split By Amounts (montos personalizados)
$bills = $billingService->splitByAmounts($order, $amounts);
// amounts = [5000, 3000, 3900]
// Tolerancia de $1 por redondeo
// Última bill absorbe diferencia
Reconstrucción de Bill (Puntos 55-56)
Criterio de cierre: Una Bill reconstruida después de offline/sync debe producir exactamente el mismo resultado financiero.
Algoritmo de reconstrucción:
1. Capturar payment_ids ANTES de borrar bill
2. Borrar bill (simular pérdida)
3. Reconstruir desde:
   - Order: subtotal, tax, discount, tip
   - Payments (por payment_ids): paid, tip
4. Aplicar registerPaymentAmount(paid + tip)
5. Verificar: total, paid, remaining, status, tip coinciden
Validado por test: CRITERIO DE CIERRE: Bill reconstruido produce mismo resultado financiero
Consistencia Bill ↔ Order (Limitación Conocida)
Estado actual: Bill NO se sincroniza automáticamente cuando Order cambia después de crear la bill.
Comportamiento:
✅ Si Order cambia antes de crear bill → bill usa datos correctos
⚠️ Si Order cambia después de crear bill → bill queda desactualizado
Workaround: Recrear bills desde Order antes de pagar
$billingService->createSingleBill($order); // idempotente
Roadmap futuro: Observer de Order que invalida bills cuando hay cambios en items.
Merge/Split/Move de Mesas
Estado: No implementado en MVP.
Roadmap futuro:
Merge: Combinar 2 orders en 1 bill
Move: Transferir items entre orders
Split de mesa: Dividir items de un order en múltiples orders
Validaciones Obligatorias
1. Integridad de split:
SUM(bill_i.total) = order.total (con tolerancia de $1 por redondeo)
2. No sobrepago en bill:
payment.total_amount <= bill.remaining_amount
3. Idempotencia:
createSingleBill() con bill existente → retorna bill existente
splitEqual() con bills existentes → cancela y recrea
4. Cross-tenant isolation:
Bill usa BelongsToTenant → queries automáticamente scoped
Casos de Prueba Validados (BillIntegrityTest)
Bill refleja estado financiero del Order
Payment sin Bill (pago directo)
Payment a través de Bill
registerPaymentAmount con propina
Propina contada una sola vez
Pago parcial actualiza estado
Split equal (2+ partes)
Split by items
Split by amounts
Reconstrucción desde Order + Payments
Criterio de cierre (mismo resultado financiero)
Todos validados en tests/Feature/BillIntegrityTest.php (11 passed, 2 skipped por limitaciones conocidas)
Anti-patrones Comunes
❌ Anti-patrón	✅ Patrón correcto
Sumar bill.tip + order.tip + payment.tip	Sumar solo payment.tip
registerPaymentAmount($payment->amount) con propina	registerPaymentAmount($payment->total_amount)
Filtrar bills manualmente por company_id	Usar Bill::query() (BelongsToTenant automático)
Modificar bill manualmente después de split	Usar BillingService (transacciones DB)
Asumir bill se actualiza con cambios de order	Recrear bill antes de pagar

---

## REGLAS DE BILLING (Puntos 47-56)

### Definición Formal de Bill

**Bill** es la representación financiera consistente de una cuenta a cobrar, derivada de un Order.

**Fuente de verdad**: Bill es una proyección de:
- **Order** (items, precios, impuestos, descuentos)
- **Payments** (pagos efectivos recibidos)

**Fórmula fundamental**:
LOCAL BILL = f(ORDER + PAYMENTS)

### Jerarquía de Entidades
Order (1) ──→ (N) Bill ──→ (N) Payment

- Un Order puede tener 1 o N Bills (split)
- Un Bill puede tener 0 o N Payments (parciales)
- Un Payment puede existir sin Bill (pago directo a Order)

### Campos Financieros de Bill

| Campo | Significado | Fórmula |
|-------|-------------|---------|
| `subtotal` | Base imponible bruta | Copia de Order.subtotal |
| `tax_amount` | IVA incluido | Copia de Order.tax_amount |
| `discount_amount` | Descuento aplicado | Copia de Order.discount_amount |
| `tip_amount` | Propina (separada) | Suma de tips de Payments asociados |
| `total` | Monto a cobrar | subtotal + tax - discount + tip |
| `paid_amount` | Total efectivamente pagado | SUM(Payments.total_amount) |
| `remaining_amount` | Saldo pendiente | total - paid_amount |

### Estados de Bill (BillStatus)
OPEN → PARTIAL → PAID
↓ ↓
CANCELLED

- **OPEN**: `paid_amount = 0`, `remaining_amount = total`
- **PARTIAL**: `0 < paid_amount < total`
- **PAID**: `paid_amount ≥ total` (o `remaining_amount ≤ 0`)
- **CANCELLED**: Bill cancelado (por split o anulación)

### Reglas de Propina en Bill (Punto 50)

**Regla crítica**: La propina se contabiliza UNA sola vez.

**Fuente única de verdad para propinas**: Tabla `payments`.

```sql
-- Reporte correcto (NO duplica)
SELECT SUM(tip_amount) FROM payments WHERE order_id = X;

-- Reporte INCORRECTO (triple contabilidad)
SELECT tip FROM orders + tip FROM bills + tip FROM payments  -- ❌
Uso correcto de registerPaymentAmount():
// Frontend debe pasar TOTAL_AMOUNT (amount + tip), no solo amount
$bill->registerPaymentAmount((float) $payment->total_amount);
Split Bill (3 Modalidades)
Modalidad 1: Split Equal (partes iguales)
$bills = $billingService->splitEqual($order, $parts);
// Requiere: $parts >= 2
// Distribuye subtotal, tax, discount proporcionalmente
// Residuos por redondeo → primera bill
Modalidad 2: Split By Items (cada comensal paga lo suyo)
$bills = $billingService->splitByItems($order, $groups);
// groups = [['item_ids' => [...], 'guest_count' => N], ...]
// Tax y discount proporcionales al subtotal de cada grupo
// Ajuste de redondeo → última bill
Modalidad 3: Split By Amounts (montos personalizados)
$bills = $billingService->splitByAmounts($order, $amounts);
// amounts = [5000, 3000, 3900]
// Tolerancia de $1 por redondeo
// Última bill absorbe diferencia
Reconstrucción de Bill (Puntos 55-56)
Criterio de cierre: Una Bill reconstruida después de offline/sync debe producir exactamente el mismo resultado financiero.
Algoritmo de reconstrucción:
1. Capturar payment_ids ANTES de borrar bill
2. Borrar bill (simular pérdida)
3. Reconstruir desde:
   - Order: subtotal, tax, discount, tip
   - Payments (por payment_ids): paid, tip
4. Aplicar registerPaymentAmount(paid + tip)
5. Verificar: total, paid, remaining, status, tip coinciden
Validado por test: CRITERIO DE CIERRE: Bill reconstruido produce mismo resultado financiero
Consistencia Bill ↔ Order (Limitación Conocida)
Estado actual: Bill NO se sincroniza automáticamente cuando Order cambia después de crear la bill.
Comportamiento:
✅ Si Order cambia antes de crear bill → bill usa datos correctos
⚠️ Si Order cambia después de crear bill → bill queda desactualizado
Workaround: Recrear bills desde Order antes de pagar
$billingService->createSingleBill($order); // idempotente
Roadmap futuro: Observer de Order que invalida bills cuando hay cambios en items.
Merge/Split/Move de Mesas
Estado: No implementado en MVP.
Roadmap futuro:
Merge: Combinar 2 orders en 1 bill
Move: Transferir items entre orders
Split de mesa: Dividir items de un order en múltiples orders
Validaciones Obligatorias
1. Integridad de split:
SUM(bill_i.total) = order.total (con tolerancia de $1 por redondeo)
2. No sobrepago en bill:
payment.total_amount <= bill.remaining_amount
3. Idempotencia:
createSingleBill() con bill existente → retorna bill existente
splitEqual() con bills existentes → cancela y recrea
4. Cross-tenant isolation:
Bill usa BelongsToTenant → queries automáticamente scoped
Casos de Prueba Validados (BillIntegrityTest)
Bill refleja estado financiero del Order
Payment sin Bill (pago directo)
Payment a través de Bill
registerPaymentAmount con propina
Propina contada una sola vez
Pago parcial actualiza estado
Split equal (2+ partes)
Split by items
Split by amounts
Reconstrucción desde Order + Payments
Criterio de cierre (mismo resultado financiero)
Todos validados en tests/Feature/BillIntegrityTest.php (11 passed, 2 skipped por limitaciones conocidas)
Anti-patrones Comunes
❌ Anti-patrón	✅ Patrón correcto
Sumar bill.tip + order.tip + payment.tip	Sumar solo payment.tip
registerPaymentAmount($payment->amount) con propina	registerPaymentAmount($payment->total_amount)
Filtrar bills manualmente por company_id	Usar Bill::query() (BelongsToTenant automático)
Modificar bill manualmente después de split	Usar BillingService (transacciones DB)
Asumir bill se actualiza con cambios de order	Recrear bill antes de pagar

### DEUDA TÉCNICA: Migraciones de Idempotencia Scoped (P2)

**Estado**: Pendiente de aplicar

**Migraciones bloqueadas**:
- `2026_09_15_000001_scope_idempotency_keys_to_tenant.php`
- `2026_09_15_000002_scope_payments_idempotency_key_to_tenant.php`

**Bloqueador**: Migración antigua con bug en `company.settings`
- Archivo: `2026_09_07_100000_add_currency_config_to_company_settings.php:34`
- Error: `Cannot access offset of type string on string`
- Causa: Alguna company tiene `settings` como string en vez de array

**Mitigación actual**:
- PaymentService filtra por `company_id + branch_id` antes de crear payment
- Cross-tenant leakage IMPOSIBLE a nivel de aplicación
- CrossTenantIdempotencyTest valida que dos tenants pueden usar misma key sin colisionar

**Riesgo residual**:
- UNIQUE constraint en DB es global (no scoped)
- Si dos tenants usan exactamente la misma UUID v4 como key → colisión operativa
- Probabilidad: ~2^-122 (prácticamente 0)
- NO hay cross-tenant leakage (protegido a nivel aplicación)

**Plan de remediación** (futuro):
1. Diagnosticar `company.settings` con datos reales
2. Migración para normalizar a array
3. Aplicar migraciones de idempotencia scoped
4. Validar con CrossTenantIdempotencyTest

**Impacto actual**: Ninguno. El sistema funciona correctamente.

### DEUDA TÉCNICA: Migraciones de Idempotencia Scoped (P2)

**Estado**: Pendiente de aplicar

**Migraciones bloqueadas**:
- `2026_09_15_000001_scope_idempotency_keys_to_tenant.php`
- `2026_09_15_000002_scope_payments_idempotency_key_to_tenant.php`

**Bloqueador**: Migración antigua con bug en `company.settings`
- Archivo: `2026_09_07_100000_add_currency_config_to_company_settings.php:34`
- Error: `Cannot access offset of type string on string`
- Causa: Alguna company tiene `settings` como string en vez de array

**Mitigación actual**:
- PaymentService filtra por `company_id + branch_id` antes de crear payment
- Cross-tenant leakage IMPOSIBLE a nivel de aplicación
- CrossTenantIdempotencyTest valida que dos tenants pueden usar misma key sin colisionar

**Riesgo residual**:
- UNIQUE constraint en DB es global (no scoped)
- Si dos tenants usan exactamente la misma UUID v4 como key → colisión operativa
- Probabilidad: ~2^-122 (prácticamente 0)
- NO hay cross-tenant leakage (protegido a nivel aplicación)

**Plan de remediación** (futuro):
1. Diagnosticar `company.settings` con datos reales
2. Migración para normalizar a array
3. Aplicar migraciones de idempotencia scoped
4. Validar con CrossTenantIdempotencyTest

**Impacto actual**: Ninguno. El sistema funciona correctamente.
