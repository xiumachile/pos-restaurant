
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
