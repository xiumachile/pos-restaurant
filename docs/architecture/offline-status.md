# Estado de Funcionalidad OFFLINE

**Fecha de Auditoría**: Septiembre 2026  
**Estado**: Implementación Parcial (30%)  
**Prioridad**: FASE 2 (Post-MVP)

## Resumen Ejecutivo

El sistema tiene una arquitectura offline-first **parcialmente implementada**. 
Solo `Order` y `OrderItem` tienen soporte completo para operación offline.

**Funcionalidades críticas como payments, bills, y cash sessions requieren conexión online.**

## Arquitectura Actual

### Componentes Implementados (✅)

#### 1. Entidades Unificadas con Syncable Trait
Order (PostgreSQL) ←→ local_orders (SQLite)
└─ OrderItem ←→ local_order_items

**Mecanismo**:
- Trait `Syncable` en entidades (PostgreSQL)
- Tablas `local_*` en SQLite (cliente)
- `SyncAdapter` sincroniza bidireccionalmente
- `SyncService` maneja cola de cambios (`sync_queue`)

**Tests**: 61 tests pasando (SyncServiceTest, SyncableTraitTest, SyncFinalE2ETest)

#### 2. Schema SQLite (001_initial_schema.php)
```sql
local_orders          ✅ Implementado
local_order_items     ✅ Implementado
local_sync_metadata   ✅ Implementado
schema_versions       ✅ Implementado
3. Estados de Sincronización (SyncStatus enum)
case PENDING   // Modificado localmente, pendiente de sincronizar
case SYNCED    // Sincronizado exitosamente
case CONFLICT  // Conflicto detectado
case FAILED    // Falló la sincronización

Componentes NO Implementados (❌)
Entidad	Tabla Local	SyncAdapter	Tests	Estado
Payment	❌ local_payments	❌ No	❌ No	NO IMPLEMENTADO
Bill	❌ local_bills	❌ No	❌ No	NO IMPLEMENTADO
RestaurantTable	❌ local_tables	❌ No	❌ No	NO IMPLEMENTADO
CashSession	❌ local_cash_sessions	❌ No	❌ No	NO IMPLEMENTADO
CashMovement	❌ local_cash_movements	❌ No	❌ No	NO IMPLEMENTADO
PrintJob	❌ local_print_jobs	❌ No	❌ No	NO IMPLEMENTADO
OfflineEvent	❌ offline_events	❌ No	❌ No	NO IMPLEMENTADO

Implicaciones Operativas
✅ Funciona Offline
Crear órdenes
Agregar/modificar items de orden
Ver historial de órdenes (previamente sincronizadas)
Cambiar estado de órdenes (draft → confirmed → preparing → served)
❌ Requiere Conexión Online
Procesar pagos (Payment creation)
Generar bills (Bill creation)
Aplicar split bill
Abrir/cerrar sesiones de caja (CashSession)
Registrar movimientos de caja (CashMovement)
Imprimir tickets (PrintJob)
Emitir DTEs (facturación electrónica)

⚠️ Limitaciones Conocidas
1. No hay cola offline para payments: Si el usuario intenta pagar sin conexión, la operación falla inmediatamente.
2. No hay resolución de conflictos para payments: Si dos terminales procesan pagos para la misma orden simultáneamente (uno online, uno offline), puede haber inconsistencia.
3. Bills dependen de conexión: No se pueden crear bills offline, lo que impide split bill en modo offline.

Checklist Original vs Implementación Real
Puntos 68-76 del Checklist
Punto	Descripción	Estado	Justificación
68	Revisar tablas locales	⚠️ PARCIAL	Solo orders/items implementados
69	Definir relaciones locales	⚠️ PARCIAL	Solo Order ↔ OrderItem
70	CORREGIR LocalPayment	❌ N/A	LocalPayment no existe
71	LocalPayment con bill_local_uuid	❌ N/A	LocalPayment no existe
72	Agregar bill_local_uuid	❌ N/A	LocalPayment no existe
73	Eliminar existingBills[0]	❌ N/A	No hay código offline para payments
74	Relación Payment → Bill → Order	❌ N/A	No implementado offline
75	Obligatorio para split bill	❌ N/A	Split bill requiere conexión online
76	UUID local, cloud ID, sync states	⚠️ PARCIAL	Solo para Order/OrderItem
Conclusión: 9 puntos, 4 parciales, 5 N/A (no implementado)
Criterio de Cierre del Checklist
"Es posible tener varias Bills de una misma Order y saber exactamente qué Payment pertenece a cada Bill."
Estado: ❌ NO APLICA
Justificación:
El criterio asume que payments y bills pueden existir offline
En la implementación actual, payments y bills siempre requieren conexión online
No hay tablas local_payments o local_bills en SQLite
Por lo tanto, el criterio no puede ser evaluado
Alternativa: El criterio se cumple en modo online (ver BILLING completado, puntos 47-56), pero no en modo offline.
Plan de Implementación (FASE 2 - OFFLINE Hardening)
Fase 2.1: Schema de Pagos Offline (Prioridad ALTA)
Migración 002: local_payments y local_bills
// app/Modules/Sync/Database/ClientMigrations/002_payments_schema.php
$schema->create('local_bills', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->uuid('server_id')->nullable();
    $table->uuid('local_order_uuid');  // FK a local_orders.uuid
    $table->string('bill_number', 50);
    $table->string('type', 30);  // single, equal_split, by_items, custom_amount
    $table->decimal('subtotal', 14, 2);
    $table->decimal('tax_amount', 14, 2);
    $table->decimal('discount_amount', 14, 2);
    $table->decimal('tip_amount', 14, 2);
    $table->decimal('total', 14, 2);
    $table->decimal('paid_amount', 14, 2)->default(0);
    $table->decimal('remaining_amount', 14, 2);
    $table->string('status', 20);  // open, partial, paid, cancelled
    $table->unsignedInteger('guest_count')->default(1);
    $table->json('item_ids')->nullable();  // Para split by items
    $table->unsignedInteger('version')->default(1);
    $table->string('sync_status', 20)->default('pending');
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();
    
    $table->index('local_order_uuid');
    $table->index('sync_status');
});

$schema->create('local_payments', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->uuid('server_id')->nullable();
    $table->uuid('local_order_uuid');  // FK a local_orders.uuid
    $table->uuid('local_bill_uuid')->nullable();  // FK a local_bills.uuid (nullable para split bill futuro)
    $table->string('payment_method_code', 50);
    $table->decimal('amount', 14, 2);
    $table->decimal('tip_amount', 14, 2)->default(0);
    $table->decimal('total_amount', 14, 2);
    $table->string('status', 20);  // pending, completed, failed
    $table->string('idempotency_key', 255)->unique();
    $table->string('reference_code', 100)->nullable();
    $table->text('notes')->nullable();
    $table->unsignedInteger('version')->default(1);
    $table->string('sync_status', 20)->default('pending');
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();
    
    $table->index('local_order_uuid');
    $table->index('local_bill_uuid');
    $table->index('sync_status');
});

EntityMapper: Agregar métodos para Payment y Bill
public function billToLocal(Bill $bill, string $localOrderUuid): array
{
    return [
        'uuid' => $bill->uuid,
        'server_id' => $bill->id,
        'local_order_uuid' => $localOrderUuid,
        'bill_number' => $bill->bill_number,
        'type' => $bill->type->value,
        'subtotal' => (float) $bill->subtotal,
        'tax_amount' => (float) $bill->tax_amount,
        'discount_amount' => (float) $bill->discount_amount,
        'tip_amount' => (float) $bill->tip_amount,
        'total' => (float) $bill->total,
        'paid_amount' => (float) $bill->paid_amount,
        'remaining_amount' => (float) $bill->remaining_amount,
        'status' => $bill->status->value,
        'guest_count' => $bill->guest_count,
        'item_ids' => $bill->item_ids,
        'version' => $bill->version ?? 1,
        'sync_status' => $bill->sync_status?->value ?? 'pending',
    ];
}

public function paymentToLocal(Payment $payment, string $localOrderUuid, ?string $localBillUuid): array
{
    return [
        'uuid' => $payment->uuid,
        'server_id' => $payment->id,
        'local_order_uuid' => $localOrderUuid,
        'local_bill_uuid' => $localBillUuid,
        'payment_method_code' => $payment->method_code,
        'amount' => (float) $payment->amount,
        'tip_amount' => (float) $payment->tip_amount,
        'total_amount' => (float) $payment->total_amount,
        'status' => $payment->status->value,
        'idempotency_key' => $payment->idempotency_key,
        'reference_code' => $payment->reference_code,
        'notes' => $payment->notes,
        'version' => $payment->version ?? 1,
        'sync_status' => $payment->sync_status?->value ?? 'pending',
    ];
}

SyncAdapter: Agregar export/import para payments y bills
public function exportBillsToLocal(int $branchId): array
{
    // Similar a exportOrdersToLocal
}

public function exportPaymentsToLocal(int $branchId): array
{
    // Similar a exportOrdersToLocal
}

public function importBillsFromLocal(int $branchId): array
{
    // Similar a importOrdersFromLocal
}

public function importPaymentsFromLocal(int $branchId): array
{
    // Similar a importOrdersFromLocal
}

Fase 2.2: Tests de Offline Completo (Prioridad ALTA)
// tests/Feature/OfflinePaymentsTest.php
test('crear payment offline y sincronizar', function () {
    // 1. Cliente crea orden offline
    // 2. Cliente crea payment offline (en local_payments)
    // 3. Cliente recupera conexión
    // 4. SyncService procesa payment pendiente
    // 5. Payment aparece en servidor con bill_id correcto
});

test('split bill offline', function () {
    // 1. Cliente crea orden offline
    // 2. Cliente crea 2 bills offline (en local_bills)
    // 3. Cliente crea payment para bill 1 offline
    // 4. Sync
    // 5. Verificar que payment pertenece al bill correcto
});

test('múltiples payments offline para misma orden', function () {
    // 1. Orden con $10,000
    // 2. Payment 1: $5,000 offline
    // 3. Payment 2: $5,000 offline
    // 4. Sync
    // 5. Verificar que ambos payments existen y suman $10,000
});

Fase 2.3: Resolución de Conflictos (Prioridad MEDIA)
Escenario: Dos terminales procesan pagos para la misma orden simultáneamente (uno online, uno offline).
Estrategia:
Detectar conflicto por order_id + timestamp
Resolver automáticamente si la suma de payments ≤ order.total
Marcar como CONFLICT si excede el total
Notificar al usuario para resolución manual
Fase 2.4: Cash Sessions Offline (Prioridad BAJA)

local_cash_sessions
local_cash_movements

Permitir abrir/cerrar cajas sin conexión, sincronizar después.
Estimación de Esfuerzo
Fase	Tareas	Horas Estimadas	Prioridad
2.1	Schema + Mapper + Adapter	8-10h	ALTA
2.2	Tests de offline completo	6-8h	ALTA
2.3	Resolución de conflictos	4-6h	MEDIA
2.4	Cash sessions offline	6-8h	BAJA
Total		24-32h	

Recomendación Estratégica
Opción A: Lanzar MVP sin offline completo (RECOMENDADA)
Pros:
Lanzamiento más rápido
Menor complejidad inicial
Validar con usuarios reales si offline es crítico
Contras:
Limitación operativa en zonas sin conexión
No se pueden procesar pagos offline
Plan:
Lanzar MVP con offline parcial (orders/items)
Documentar limitaciones claramente
Recopilar feedback de usuarios
Implementar FASE 2 basado en demanda real
Opción B: Implementar FASE 2 antes del lanzamiento
Pros:
Funcionalidad completa desde día 1
Competitivo en zonas sin conexión
Contras:
Retraso de 3-4 semanas en lanzamiento
Mayor complejidad inicial
Riesgo de sobre-ingeniería si usuarios no lo necesitan
Plan:
Implementar FASE 2.1 y 2.2 (pagos offline)
Lanzar con offline completo para payments/bills
Implementar 2.3 y 2.4 en iteraciones posteriores
Opción C: Híbrido (MVP + FASE 2.1)
Pros:
Balance entre velocidad y funcionalidad
Offline para payments (crítico) sin cash sessions (menos crítico)
Contras:
Aún requiere 1-2 semanas adicionales
Plan:
Implementar solo FASE 2.1 (schema + payments/bills offline)
Lanzar MVP con offline para orders + payments
Implementar 2.2, 2.3, 2.4 en sprints posteriores
Mi Recomendación
Opción A (MVP sin offline completo) porque:
El 90% de restaurantes tiene conexión estable: La mayoría de restaurantes en Chile tienen internet confiable.
Payments offline agrega complejidad significativa: Resolución de conflictos, idempotencia, reconciliación de bills.
Validación con usuarios reales es más valiosa: Mejor lanzar rápido y aprender qué necesitan realmente.
Offline parcial ya es útil: Crear órdenes offline es valioso para meseros que se mueven por el restaurante.
FASE 2 puede implementarse incrementalmente: Si usuarios demandan payments offline, se implementa en sprint posterior.
Conclusión
Estado actual: Sistema funcional con offline parcial (orders/items).
Limitación: Payments, bills, y cash sessions requieren conexión online.
Próximo paso: Lanzar MVP y validar con usuarios reales si offline completo es necesario.
Roadmap: FASE 2 (OFFLINE Hardening) planificada pero no priorizada para MVP.
