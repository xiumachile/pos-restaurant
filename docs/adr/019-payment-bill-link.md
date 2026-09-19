# ADR-019: Vinculación de Payments a Bills

## Estado: Aceptada

## Contexto

El sistema permite dividir cuentas (split bill) en múltiples bills con sus
respectivos payments. Es necesario vincular cada payment a su bill específica
para preservar la estructura del split durante la sincronización.

## Decisión

### Estructura de Datos

**Frontend (local_payments)**:
- `bill_local_uuid`: UUID local de la bill vinculada (NULL si pago directo a order)
- `amount`: Total recibido (venta + propina)
- `sale_amount`: Porción de venta (sin propina)
- `tip_amount`: Propina

**Backend (payments)**:
- `bill_id`: ID de la bill vinculada (NULL si pago directo a order)
- `amount`: Porción de venta (sin propina) ← CRÍTICO
- `tip_amount`: Propina
- `total_amount`: amount + tip_amount (calculado por backend)

### Contrato de Sincronización

**SyncEngine debe enviar al backend**:
```json
{
  "order_uuid": "cloud-order-uuid",
  "bill_uuid": "cloud-bill-uuid",
  "amount": 10000,           // sale_amount (venta SIN propina)
  "tip_amount": 1000,        // Propina
  "payment_method_uuid": "...",
  "idempotency_key": "..."
}

Backend calcula:
$totalAmount = Payment::calculateTotal($amount, $tipAmount);
// total_amount = 10000 + 1000 = 11000

Flujo Completo
1. Frontend crea payment:
   PaymentRepository.create({
     amount: 11000,        // Total recibido
     tip_amount: 1000,     // Propina
     // Calcula: sale_amount = 11000 - 1000 = 10000
   })

2. SyncEngine procesa payment:
   paymentPayload = {
     amount: payload.sale_amount || (payload.amount - payload.tip_amount),
     tip_amount: payload.tip_amount
   }
   // Envía: { amount: 10000, tip_amount: 1000 }

3. Backend recibe y calcula:
   Payment::create([
     'amount' => 10000,
     'tip_amount' => 1000,
     'total_amount' => Payment::calculateTotal(10000, 1000) // 11000
   ])
   
Validación
Test E2E: src/tests/integration/tipFlowIntegration.test.ts
Valida que PaymentRepository calcula sale_amount correctamente
Valida que SyncEngine envía amount=sale_amount (no amount total)
Valida que el backend recibe los valores correctos
Consecuencias
Positivas
Estructura de split bill preservada en backend
Propinas contabilizadas correctamente en ledger
Flujo de propinas consistente en todo el sistema
Negativas
Complejidad adicional en SyncEngine (debe transformar amount → sale_amount)
Requiere que PaymentRepository siempre calcule sale_amount
Referencias
ADR-011: Modelo chileno (IVA incluido en precios)
ADR-020: Bills son sincronizables
Backend: app/Modules/Payments/Domain/Entities/Payment.php::calculateTotal()
Frontend: src/services/sync/SyncEngine.ts::processPayment()
