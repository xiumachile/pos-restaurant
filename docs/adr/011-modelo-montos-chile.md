# ADR-011: Modelo de montos para POS chileno

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Contexto**: Sistema POS para restaurantes en Chile

## Contexto

El sistema maneja dinero en múltiples capas (frontend, SQLite, backend) y debe cumplir con normativa chilena:

1. **Ley de IVA**: Boletas deben mostrar precio con IVA incluido
2. **SII**: Propina es "Otros Montos" separada del monto afecta a IVA
3. **Dirección del Trabajo**: Propina sugerida 10% sobre consumo

## Problema identificado

El sistema actual asume que `subtotal` es **neto** (sin IVA) y calcula:

```typescript
grand_total = subtotal + tax
Resultado incorrecto:
Producto: $10.000 (IVA incluido)
Subtotal: $10.000
Tax: $1.900 (19% de 10.000)
Grand total: $11.900 ❌ Cobra IVA dos veces
Resultado correcto:
Producto: $10.000 (IVA incluido)
Gross: $10.000
Net: $8.403
Tax: $1.597
Grand total: $10.000 ✅
Decisión
Modelo de datos corregido
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
  amount_due: number        // = grand_total + tip_amount
}

// ORDER ITEM
{
  unit_price: number        // Precio del producto (IVA incluido)
  quantity: number
  subtotal: number          // = unit_price × quantity (IVA incluido)
}

// PAYMENT
{
  amount: number            // Monto total recibido
  sale_amount: number       // Porción que va a la venta
  tip_amount: number        // Porción que es propina
  payment_method: string
}

// BILL
{
  grand_total: number       // = Order.grand_total
  tip_amount: number        // = Order.tip_amount
  amount_due: number        // = Order.amount_due
  paid_amount: number       // Suma de payments.amount
  remaining_amount: number  // = amount_due - paid_amount
}

Reglas de cálculo
// OrderRepository.recalculateOrderTotals()
const gross = items.reduce((sum, item) => sum + item.subtotal, 0);
const net = Math.round(gross / 1.19);
const tax = gross - net;
const grandTotal = gross - discount;
const amountDue = grandTotal + tip;

// Validaciones
assert(net + tax === gross)
assert(grandTotal === gross - discount)
assert(amountDue === grandTotal + tip)

Ejemplo completo

Producto: Hamburguesa $10.000 (IVA incluido)
Cantidad: 2

ORDER
─────────────────────────────────
subtotal_gross     $20.000
discount_amount    $     0

net_amount         $16.807
tax_amount         $ 3.193
─────────────────────────────
grand_total        $20.000

tip_amount         $ 2.000
─────────────────────────────
amount_due         $22.000

PAYMENTS
─────────────────────────────────
Payment 1 (tarjeta)
  amount:         $15.000
  sale_amount:    $15.000
  tip_amount:     $     0

Payment 2 (efectivo)
  amount:          $7.000
  sale_amount:     $5.000
  tip_amount:      $2.000

TOTAL PAGADO      $22.000 ✅

Migración de datos existentes
Estrategia: Migración no destructiva
Orders existentes: Asumir que subtotal actual es subtotal_gross
net_amount = subtotal / 1.19
tax_amount = subtotal - net_amount
grand_total permanece igual
amount_due = grand_total + tip_amount
Payments existentes: Asumir que amount actual es sale_amount
tip_amount = 0 (no había propina antes)
Bills existentes: Agregar campo amount_due
amount_due = grand_total + tip_amount
Consecuencias
Positivas
✅ Cumple normativa chilena (IVA incluido en precios)
✅ Propina separada del monto afecta a IVA
✅ Integridad financiera: no cobra IVA dos veces
✅ Consistencia entre frontend, SQLite y backend
Negativas
⚠️ Requiere migración de schema (nuevos campos)
⚠️ Requiere actualización de lógica de cálculo
⚠️ Requiere actualizar tests existentes
⚠️ Backend debe aceptar nueva semántica
Alternativas consideradas
Opción 1: Mantener modelo actual (rechazada)
❌ Cobra IVA dos veces
❌ No cumple normativa chilena
❌ Inconsistente con DTE
Opción 2: grand_total incluye propina (rechazada)
❌ Propina afectaría IVA (incorrecto según SII)
❌ Mezcla conceptos tributarios
❌ Complica reportes
Opción 3: Modelo propuesto (aceptada)
✅ Precios IVA incluido (normativa chilena)
✅ Propina separada (SII)
✅ Integridad financiera
✅ Consistencia entre capas
Plan de implementación
ADR-011 (este documento)
Migración de schema: agregar net_amount, amount_due
Fix OrderRepository: usar modelo correcto
Fix PaymentRepository: agregar sale_amount, tip_amount
Fix BillRepository: usar amount_due
Fix offlinePaymentService: validar integridad
Actualizar tests: validar nueva semántica
Métricas
Commits planeados: 7
Archivos modificados: ~10
Tests actualizados: ~15
Breaking changes: Sí (semántica de campos)
