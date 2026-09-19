# Contratos de Dominio — POS Restaurant

**Estado**: ✅ Aceptado  
**Fecha**: Septiembre 2026  
**Versión**: 1.0  
**Fase**: FASE 0 — Congelación de Arquitectura

---

## 📋 Índice

1. [Jerarquía de Tenant](#1-jerarquía-de-tenant)
2. [Máquinas de Estado](#2-máquinas-de-estado)
3. [Contratos Financieros](#3-contratos-financieros)
4. [Responsabilidades por Entidad](#4-responsabilidades-por-entidad)
5. [Reglas de Negocio Críticas](#5-reglas-de-negocio-críticas)
6. [Escenarios de Contingencia](#6-escenarios-de-contingencia)

---

## 1. Jerarquía de Tenant

### Estructura Formal
Company (raíz del tenant)
└─ Branch (sucursal)
└─ Terminal (dispositivo físico)
└─ User (operador autenticado)


### Fuente de Autoridad

**ÚNICA fuente válida**: `TenantContext` establecido por `TenantContextMiddleware` desde el JWT del usuario autenticado.

```php
// ✅ CORRECTO: TenantContext propagado automáticamente
$companyId = TenantContext::companyId();
$branchId = TenantContext::branchId();

// ❌ PROHIBIDO: Leer company_id del request
$companyId = $request->input('company_id');  // ANTIPATRÓN

Propagación del Contexto
HTTP Requests: TenantContextMiddleware extrae company_id, branch_id, user_id del JWT
Models: BelongsToTenant trait aplica CompanyScope y BranchScope automáticamente
Jobs/Listeners: Deben propagar tenant explícitamente via TenantContext::setCompany()
Aislamiento Multi-Tenant
Capa 1: Global Scopes

// Todas las queries filtran automáticamente por company_id del TenantContext
Order::all();  // SELECT * FROM orders WHERE company_id = ? AND branch_id = ?

Capa 2: Validación en Services

// Services validan ownership antes de operar
if ($order->company_id !== TenantContext::companyId()) {
    throw new CrossTenantAccessException();
}

Capa 3: BelongsToTenant Trait

class Order extends Model {
    use BelongsToTenant;  // Aplica scopes + auto-asigna company_id/branch_id
}

Tablas con Aislamiento Obligatorio

Tabla	company_id	branch_id	Estado
orders	 	✅	✅	Obligatorio
order_items	✅	✅	Obligatorio
bills		✅	✅	Obligatorio
payments	✅	✅	Obligatorio
cash_sessions	✅	✅	Obligatorio
cash_movements	✅	✅	Obligatorio
restaurant_tables✅	✅	Obligatorio
products	✅	✅	Obligatorio
categories	✅	✅	Obligatorio
payment_methods	✅	✅	Obligatorio
printer_configs	✅	✅	Obligatorio

Referencia: ADR-002 (Multi-tenant isolation), ADR-012 (Local multi-tenancy)
2. Máquinas de Estado
2.1 Order (Pedido)
Estados Posibles
enum OrderStatus: string {
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case READY = 'ready';
    
    // Específicos por canal
    case READY_FOR_PICKUP = 'ready_for_pickup';  // pickup
    case PICKED_UP = 'picked_up';                // pickup
    case DISPATCHED = 'dispatched';              // delivery
    case DELIVERED = 'delivered';                // delivery
    
    // Compartidos
    case SERVED = 'served';      // onsite
    case PAID = 'paid';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}

Transiciones Válidas por Canal
ONSITE (dine_in tradicional):
DRAFT → CONFIRMED → PREPARING → READY → READY_FOR_PICKUP → PICKED_UP → PAID → CLOSED

PICKUP (takeout):
DRAFT → CONFIRMED → PREPARING → READY → READY_FOR_PICKUP → PICKED_UP → PAID → CLOSED

DELIVERY:
DRAFT → CONFIRMED → PREPARING → READY → DISPATCHED → DELIVERED → PAID → CLOSED

CANCELLED: Terminal, alcanzable desde cualquier estado no-final (requiere cancellation_reason)
Reglas de Transición
DRAFT → CONFIRMED: Valida que tenga al menos 1 item
CONFIRMED → PREPARING: Asigna assigned_cook_id (opcional)
READY → SERVED/PICKED_UP/DISPATCHED: Depende de fulfillment_channel
PAID → CLOSED: Valida que total == sum(payments.amount)
→ CANCELLED: Requiere cancellation_reason no vacío
Timestamps Automáticos

Estado	Timestamp Actualizado
CONFIRMED	confirmed_at
SERVED	served_at
PICKED_UP	picked_up_at
DISPATCHED	dispatched_at
DELIVERED	delivered_at
PAID	paid_at
CLOSED	closed_at
CANCELLED	cancelled_at

Eventos de Dominio
Transición	Evento Despachado
→ CONFIRMED	OrderConfirmed
→ READY	OrderReady
→ PAID	OrderPaid
→ CLOSED	OrderClosed
→ CANCELLED	OrderCancelled

Referencia: OrderStateMachine, OrderStatus
2.2 Bill (Cuenta)
Estados Posibles
enum BillStatus: string {
    case OPEN = 'open';          // Abierta, sin pagos
    case PARTIAL = 'partial';    // Parcialmente pagada
    case PAID = 'paid';          // Completamente pagada
    case CANCELLED = 'cancelled'; // Cancelada
}

Transiciones Válidas
OPEN → PARTIAL (primer pago parcial)
OPEN → PAID (pago completo)
PARTIAL → PAID (pago restante)
OPEN/PARTIAL → CANCELLED (cancelación)

Reglas de Transición
OPEN → PARTIAL: paid_amount > 0 y remaining_amount > 0
PARTIAL → PAID: remaining_amount <= 0
→ CANCELLED: Solo si paid_amount == 0 (sin pagos aplicados)
Cálculo de Montos

$total = $subtotal + $tax_amount - $discount_amount + $tip_amount;
$paid_amount = sum(payments.where('bill_id', $this->id));
$remaining_amount = $total - $paid_amount;

Scope: Bills Pagables
Bill::payable()->get();  // status IN ('open', 'partial')

Referencia: ADR-020 (Bills sincronizables), BillStatus

2.3 Payment (Pago)
Estados Posibles

enum PaymentStatus: string {
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
}

Transiciones Válidas
PENDING → COMPLETED (pago exitoso)
PENDING → FAILED (error de procesamiento)
COMPLETED → REFUNDED (reembolso total)

Reglas de Transición
PENDING → COMPLETED: Valida amount > 0 y cash_session_id válido
COMPLETED → REFUNDED: Crea registro en refunds (append-only)
→ FAILED: No cambia bill.paid_amount
Ledger Append-Only
Regla: Los payments NUNCA se borran, solo se revierten vía Refund.

// ❌ PROHIBIDO
$payment->delete();

// ✅ CORRECTO
Refund::create([
    'payment_id' => $payment->id,
    'amount' => $payment->amount,
    'reason' => 'Devolución solicitada',
]);

Referencia: PaymentStatus, RefundStatus

2.4 CashSession (Sesión de Caja)
Estados Posibles
enum CashSessionStatus: string {
    case OPEN = 'open';
    case CLOSED = 'closed';
    case SUSPENDED = 'suspended';
}

Transiciones Válidas
OPEN → CLOSED (cierre de caja)
OPEN → SUSPENDED (suspensión temporal)
SUSPENDED → OPEN (reanudación)

Reglas de Transición
OPEN → CLOSED:
Calcula expected_amount = opening_amount + sum(payments.amount)
Cajero ingresa closing_amount
Sistema calcula difference = closing_amount - expected_amount
Requiere closing_notes
OPEN → SUSPENDED: Pausa temporal (ej: cambio de turno)
SUSPENDED → OPEN: Reanuda la sesión
Validaciones
Solo una sesión OPEN por (user_id, register_id, branch_id) simultáneamente
Payments solo pueden crearse si cash_session.status == OPEN
Referencia: CashSessionStatus
2.5 RestaurantTable (Mesa)

Estados Posibles
enum TableStatus: string {
    case Available = 'available';
    case Occupied = 'occupied';
    case Billing = 'billing';
    case Maintenance = 'maintenance';
}

Transiciones Válidas
Available → Occupied (cliente se sienta)
Available → Maintenance (fuera de servicio)
Occupied → Billing (cliente pide cuenta)
Occupied → Available (pago directo sin billing)
Billing → Available (pago completado)
Maintenance → Available (vuelve a servicio)

Reglas de Transición
Available → Occupied: Crea Order con table_id y status = DRAFT
Occupied → Billing: Cliente solicita cuenta (endpoint POST /tables/{id}/request-billing)
Billing → Available: Valida que order.status == CLOSED
Occupied → Available: Pago directo (legacy, se deprecará en favor de flujo completo)
TODO (post-demo)
Implementar flujo obligatorio Occupied → Billing → Available para mejorar trazabilidad.
Referencia: TableStateMachine, TableStatus
2.6 SyncQueue (Cola de Sincronización)
Estados Posibles
enum SyncStatus: string {
    case PENDING = 'pending';
    case SYNCING = 'syncing';
    case SYNCED = 'synced';
    case FAILED = 'failed';
}

Transiciones Válidas
PENDING → SYNCING (inicio de procesamiento)
SYNCING → SYNCED (éxito)
SYNCING → PENDING (timeout/crash, reintento)
SYNCING → FAILED (error permanente)
FAILED → PENDING (reintento manual)

Reglas de Transición
PENDING → SYNCING: SyncEngine.processItem() marca como syncing
SYNCING → SYNCED: API retorna 200/201, actualiza cloud_id
SYNCING → PENDING: Timeout o error temporal, aplica backoff exponencial
SYNCING → FAILED: Error permanente (422, 403, multi-tenant rejection)
Backoff Exponencial

const backoffSeconds = Math.pow(2, attempts) * 15;
const nextRetryAt = new Date(Date.now() + backoffSeconds * 1000);

Rechazo Permanente (ADR-014)
Si validateContext() detecta mismatch de tenant:
sync_status = 'failed'
attempts = max_attempts (sin reintentos)
last_error = '[MultiTenant] Rechazado por seguridad'
Referencia: ADR-006 (Event sourcing híbrido), SyncStatus
3. Contratos Financieros
3.1 Representación de Money

Capa	Tipo	Justificación
PostgreSQL	DECIMAL(14, 2)	Precisión exacta para montos financieros
SQLite (frontend)	INTEGER (ADR-010)	CLP sin centavos, evita errores de punto flotante
PHP	float	Suficiente para decimales financieros (< 2^53)
TypeScript	number	Nativo de JavaScript
Display	Formateado	Según Company.settings.currency

3.2 Cálculo de Impuestos (IVA Chileno)
Modelo ADR-011 (Precios IVA Incluido)
// ORDER
{
  subtotal_gross: number    // Suma de (unit_price × quantity), IVA incluido
  discount_amount: number   // Descuentos aplicados
  
  net_amount: number        // = gross / 1.19 (redondeado)
  tax_amount: number        // = gross - net
  
  grand_total: number       // = gross - discount (IVA incluido)
  
  tip_amount: number        // Propina (separada, no afecta IVA)
  amount_due: number        // = grand_total + tip
}

Ejemplo: Hamburguesa $10.000 CLP (IVA incluido) × 2 unidades
ORDER
─────────────────────────────────
subtotal_gross     $20.000
discount_amount    $     0

net_amount         $16.807  (20.000 / 1.19)
tax_amount         $ 3.193  (20.000 - 16.807)
─────────────────────────────
grand_total        $20.000

tip_amount         $ 2.000
─────────────────────────────
amount_due         $22.000

Validaciones de Integridad
assert(net + tax === gross);
assert(grandTotal === gross - discount);
assert(amountDue === grandTotal + tip);

Referencia: ADR-011 (Modelo montos Chile)
3.3 Propinas
Reglas
Separada del IVA: Propina NO afecta cálculo de IVA (SII: "Otros Montos")
Sugerida: Branch.tip_percentage_suggested (típicamente 10%)
Voluntaria: Cliente decide monto final
Payment: payment.tip_amount registra porción de propina
Flujo

Cliente paga $22.000 en efectivo
  ├─ $20.000 → sale_amount (venta)
  └─ $2.000  → tip_amount (propina)

Payment {
  amount: 22000,
  sale_amount: 20000,
  tip_amount: 2000
}

Reportes
Ventas: Suma de payment.sale_amount
Propinas: Suma de payment.tip_amount
Total caja: Suma de payment.amount
Referencia: ADR-011 (Modelo montos Chile)
3.4 Descuentos
Aplicación

OrderStateMachine::applyDiscount(Order $order, float $amount, string $reason)

Reglas
Antes de impuestos: Descuento se aplica sobre subtotal_gross
Razón obligatoria: reason para auditoría
Evento: OrderDiscountApplied despachado para tracking
Cálculo
grand_total = gross - discount_amount;
// IVA se recalcula sobre el nuevo gross
net = (gross - discount) / 1.19;
tax = (gross - discount) - net;

Referencia: OrderStateMachine::applyDiscount()
3.5 Reembolsos
Política Append-Only
Regla: Payments NUNCA se borran, solo se revierten vía Refund.

Refund::create([
    'payment_id' => $payment->id,
    'amount' => $refundAmount,  // Puede ser parcial
    'reason' => 'Producto defectuoso',
    'authorized_by' => $userId,
]);

Estados de Refund
enum RefundStatus: string {
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
}

Transiciones
PENDING → PROCESSED (reembolso exitoso)
PENDING → FAILED (error de procesamiento)

Impacto en Ledger
// Payment original
Payment { amount: 20000, status: COMPLETED }

// Refund creado
Refund { amount: 20000, status: PROCESSED }

// Net effect en reportes
net_sales = sum(payments.amount) - sum(refunds.amount)

Referencia: RefundStatus

4. Responsabilidades por Entidad
4.1 Company (Empresa)
Responsabilidades:
Raíz del tenant
Configuración global (moneda, idioma, impuestos por defecto)
Gestión de usuarios y roles
NO hace:
Operaciones transaccionales (orders, payments)
Gestión de sucursales (delegado a Branch)

4.2 Branch (Sucursal)
Responsabilidades:
Configuración local (propina sugerida, stock negativo)
Gestión de terminales y mesas
Scope de aislamiento para datos transaccionales
NO hace:
Cálculos financieros (delegado a Order/Payment)
Sincronización (delegado a SyncEngine)

4.3 Order (Pedido)
Responsabilidades:
Contenedor de items vendidos
Cálculo de totales (subtotal, tax, discount, total)
Máquina de estado (DRAFT → CLOSED)
Snapshot de precios al momento de creación
NO hace:
Procesar pagos (delegado a Payment)
Gestionar mesa (delegado a RestaurantTable)
Sincronizar (delegado a SyncQueue)

4.4 Bill (Cuenta)
Responsabilidades:
Tracking de pagos parciales
Cálculo de remaining_amount
Estado de pago (OPEN/PARTIAL/PAID)
NO hace:
Sincronizarse (ADR-020: Bills son entidades sincronizables)
Calcular impuestos (delegado a Order)
Procesar pagos (delegado a Payment)
Referencia: ADR-020 (Bills sincronizables)

4.5 Payment (Pago)
Responsabilidades:
Registrar método de pago y monto
Separar sale_amount y tip_amount
Idempotencia vía idempotency_key
Asociación con cash_session
NO hace:
Calcular totales (delegado a Order/Bill)
Gestionar reembolsos (delegado a Refund)
Actualizar estado de Order (delegado a OrderStateMachine)

4.6 CashSession (Sesión de Caja)
Responsabilidades:
Tracking de efectivo por cajero/turno
Cálculo de expected_amount vs closing_amount
Detección de diferencias (difference)
NO hace:
Procesar pagos (delegado a Payment)
Gestionar movimientos manuales (delegado a CashMovement)

4.7 CashMovement (Movimiento de Caja)
Responsabilidades:
Registrar retiros/depósitos manuales
Tracking de balance_after
Autorización vía authorized_by
NO hace:
Registrar pagos de ventas (delegado a Payment)
Calcular balance de caja (delegado a CashSession)

4.8 RestaurantTable (Mesa)
Responsabilidades:
Estado de ocupación (Available/Occupied/Billing)
Asociación con Order activo
NO hace:
Gestionar pedido (delegado a Order)
Calcular totales (delegado a Order)

4.9 SyncQueue (Cola de Sincronización)
Responsabilidades:
Encolar eventos para sincronización
Tracking de intentos y backoff
Detección de conflictos
NO hace:
Ejecutar sincronización (delegado a SyncEngine)
Validar datos (delegado a Services)
5. Reglas de Negocio Críticas
5.1 ¿Qué es una venta?
Definición: Una venta es un Order en estado PAID o CLOSED con al menos 1 Payment en estado COMPLETED.

Criterios:
$isSale = $order->status->in([PAID, CLOSED]) 
       && $order->payments()->where('status', COMPLETED)->exists();

Métricas:
Ingreso bruto: sum(order.total) de ventas
Ingreso neto: sum(order.net_amount) de ventas
IVA recaudado: sum(order.tax_amount) de ventas
5.2 ¿Qué es una Bill?
Definición: Una Bill es un artefacto de tracking de pagos parciales asociado a un Order.
Características:
Se sincroniza (ADR-020): Existe en SQLite y backend
Derivada: Se reconstruye desde Order + Payments en backend
Estados: OPEN → PARTIAL → PAID
Uso:
Frontend: Tracking de pagos parciales
Backend: Reconstrucción desde Order/Payments
Referencia: ADR-020 (Bills sincronizables)

5.3 ¿Qué es un Payment?
Definición: Un Payment es un registro de dinero recibido por un método específico.
Características:
Append-only: Nunca se borra, solo se revierte vía Refund
Idempotente: idempotency_key previene duplicados
Tenant-scoped: Filtrado automáticamente por company_id/branch_id
Estados:
PENDING: Procesando
COMPLETED: Exitoso
REFUNDED: Reembolsado
FAILED: Error

5.4 ¿Qué dinero entra a caja?
Definición: El dinero que entra a caja es la suma de payment.amount de todos los payments en estado COMPLETED asociados a una CashSession.
Cálculo:
$totalCash = $session->payments()
    ->where('status', PaymentStatus::COMPLETED)
    ->sum('amount');

Componentes:
Ventas: sum(payment.sale_amount)
Propinas: sum(payment.tip_amount)
Movimientos manuales: sum(cash_movements.amount) (retiros/depósitos)
Balance esperado:
$expectedAmount = $session->opening_amount 
                + $totalCash 
                + $manualMovements;

5.5 ¿Qué pasa con una propina?
Flujo:
Cliente decide monto de propina (sugerida: 10%)
Order.tip_amount registra propina total
Payment separa sale_amount y tip_amount
Reportes muestran propinas por separado
Ejemplo:
Order {
  grand_total: 20000,
  tip_amount: 2000,
  amount_due: 22000
}

Payment {
  amount: 22000,
  sale_amount: 20000,
  tip_amount: 2000
}

Reportes:
Ventas: $20.000
Propinas: $2.000
Total caja: $22.000
Referencia: ADR-011 (Modelo montos Chile)

5.6 ¿Cómo se calcula el IVA?
Modelo chileno (ADR-011): Precios del catálogo incluyen IVA.
Cálculo:
gross = sum(item.unit_price × item.quantity);  // IVA incluido
net = round(gross / 1.19);
tax = gross - net;

Ejemplo:
Producto: $10.000 (IVA incluido) × 2
Gross: $20.000
Net: $16.807 (20.000 / 1.19)
Tax: $3.193 (20.000 - 16.807)

Snapshot: OrderItem.tax_rate_snapshot preserva tasa al momento de venta.
Referencia: ADR-011 (Modelo montos Chile)

5.7 ¿Qué ocurre si pago dos veces?
Escenario: Cliente paga $10.000, luego paga otros $10.000 por error.
Flujo:
Primer payment: amount: 10000, status: COMPLETED
Segundo payment: amount: 10000, status: COMPLETED
Bill actualiza: paid_amount: 20000, remaining_amount: -10000 (sobrepago)
Resolución:
Opción A: Cajero crea Refund por $10.000
Opción B: Sistema detecta sobrepago y alerta5.7 ¿Qué ocurre si pago dos veces?
Escenario: Cliente paga $10.000, luego paga otros $10.000 por error.
Flujo:
Primer payment: amount: 10000, status: COMPLETED
Segundo payment: amount: 10000, status: COMPLETED
Bill actualiza: paid_amount: 20000, remaining_amount: -10000 (sobrepago)
Resolución:
Opción A: Cajero crea Refund por $10.000
Opción B: Sistema detecta sobrepago y alerta

Ledger
Payment 1: +$10.000
Payment 2: +$10.000
Refund 1:  -$10.000
─────────────────────
Net:       +$10.000 ✅
Referencia: Política append-only (Sección 3.5)

6. Escenarios de Contingencia
6.1 ¿Qué ocurre si estoy offline?
Flujo offline-first:
Frontend crea Order en SQLite con sync_status = PENDING
SyncQueueRepository.enqueue() agrega evento a sync_queue
Frontend continúa operando normalmente
Al recuperar conexión, SyncEngine.processBatch() sincroniza
Garantías:
✅ Datos persisten en SQLite
✅ Idempotencia vía idempotency_key
✅ Backoff exponencial en reintentos
✅ Multi-tenancy validado en sync (ADR-014)
Referencia: ADR-006 (Event sourcing híbrido)

6.2 ¿Qué ocurre si dos terminales pagan simultáneamente?
Escenario: Terminal A y Terminal B pagan el mismo order al mismo tiempo.
Resolución:
Idempotencia: payment.idempotency_key previene duplicados
Optimistic locking: order.version detecta conflictos
Conflict resolution: SERVER_WINS (backend es fuente de verdad)
Flujo:
Terminal A: POST /payments { idempotency_key: "abc123" }
Terminal B: POST /payments { idempotency_key: "xyz789" }

Backend:
  Payment A: Creado (idempotency_key: abc123)
  Payment B: Creado (idempotency_key: xyz789)
  
  Order.version: 1 → 2 (primer payment)
  Order.version: 2 → 3 (segundo payment)

Resultado: Ambos payments se registran, order tiene 2 payments.
Referencia: ADR-003 (Controller-service pattern)

6.3 ¿Qué ocurre si se corta la energía durante un pago?
Escenario: App se cierra mientras procesa payment.
Recuperación:
SyncQueue: Item queda en syncing (no completó transición)
Timeout detection: SyncQueueRepository.recoverAbandonedSyncing() detecta items en syncing por >2 minutos
Reset: Item vuelve a pending con attempts++
Reintento: SyncEngine reprocesa en siguiente batch
Garantías:
✅ No hay pérdida de datos (SQLite persiste)
✅ Idempotencia previene duplicados
✅ Backoff exponencial en reintentos
Referencia: ADR-006 (Event sourcing híbrido)

6.4 ¿Cómo queda exactamente PostgreSQL después de sincronizar?
Estado final de una order sincronizada:
-- orders
SELECT * FROM orders WHERE uuid = 'order-uuid';
┌─────────────────────────────────────────────────────────┐
│ uuid: 'order-uuid'                                      │
│ company_id: 1                                           │
│ branch_id: 2                                            │
│ status: 'closed'                                        │
│ subtotal: 20000.00                                      │
│ tax_amount: 3193.00                                     │
│ discount_amount: 0.00                                   │
│ total: 20000.00                                         │
│ sync_status: 'synced'                                   │
│ version: 3                                              │
│ last_synced_at: '2026-09-14 10:30:00'                   │
└─────────────────────────────────────────────────────────┘

-- order_items (2 items)
SELECT * FROM order_items WHERE order_id = 'order-uuid';
┌─────────────────────────────────────────────────────────┐
│ uuid: 'item-1'                                          │
│ product_id: 123                                         │
│ quantity: 2                                             │
│ unit_price_snapshot: 10000.00                           │
│ subtotal: 20000.00                                      │
│ tax_amount: 3193.00                                     │
│ tax_rate_snapshot: 0.1900                               │
└─────────────────────────────────────────────────────────┘

-- payments (1 payment)
SELECT * FROM payments WHERE order_id = 'order-uuid';
┌─────────────────────────────────────────────────────────┐
│ uuid: 'payment-uuid'                                    │
│ amount: 22000.00                                        │
│ sale_amount: 20000.00                                   │
│ tip_amount: 2000.00                                     │
│ status: 'completed'                                     │
│ idempotency_key: 'abc123'                               │
└─────────────────────────────────────────────────────────┘

-- sync_queue (limpio)
SELECT * FROM sync_queue WHERE entity_local_uuid = 'order-uuid';
(0 rows) -- Item eliminado tras sync exitoso

Garantías:
✅ Datos consistentes entre frontend y backend
✅ Snapshots históricos preservados (precios, impuestos)
✅ Audit trail completo (timestamps, versiones)

🎯 Criterio de Cierre
Este documento responde formalmente a las 12 preguntas críticas:
✅ ¿Qué es una venta? (Sección 5.1)
✅ ¿Qué es una Bill? (Sección 5.2)
✅ ¿Qué es un Payment? (Sección 5.3)
✅ ¿Qué dinero entra a caja? (Sección 5.4)
✅ ¿Qué pasa con una propina? (Sección 5.5)
✅ ¿Cómo se calcula el IVA? (Sección 5.6)
✅ ¿Qué ocurre si pago dos veces? (Sección 5.7)
✅ ¿Qué ocurre si estoy offline? (Sección 6.1)
✅ ¿Qué ocurre si dos terminales pagan simultáneamente? (Sección 6.2)
✅ ¿Qué ocurre si se corta la energía durante un pago? (Sección 6.3)
✅ ¿Cómo queda exactamente PostgreSQL después de sincronizar? (Sección 6.4)
✅ Responsabilidades de cada entidad (Sección 4)
Estado: 🟢 FASE 0 COMPLETADA

Las relaciones, estados y responsabilidades están definidos y no existen ambigüedades estructurales P0/P1.
📚 Referencias
ADR-002: Multi-tenant isolation
ADR-003: Controller-service pattern
ADR-006: Event sourcing híbrido offline
ADR-009: Bills no sincronizables
ADR-010: Money type integer CLP
ADR-011: Modelo montos Chile
ADR-012: Local multi-tenancy
ADR-014: Fail-secure auth

📝 Changelog
Fecha	Versión	Cambios
2026/9/14	1	Versión inicial (FASE 0)

