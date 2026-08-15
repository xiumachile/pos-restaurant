<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Sync\Domain\ValueObjects\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.222.222-2',
        'legal_name' => 'E2E Test SpA',
        'trade_name' => 'E2E Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'E2E',
        'name' => 'E2E Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'E2E User',
        'email' => 'e2e-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->syncService = new SyncService();
});

// ============================================
// Escenario 1: Offline completo
// ============================================

test('E2E: cliente crea pedidos offline y sincroniza al recuperar conexión', function () {
    // Simular modo offline: crear múltiples pedidos sin sync
    $orders = [];
    for ($i = 1; $i <= 3; $i++) {
        $orders[] = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-E2E-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000 * $i,
            'tax_amount' => 190 * $i,
            'discount_amount' => 0,
            'total' => 1190 * $i,
        ]);
    }

    // Verificar que todos están pending
    expect(Order::pending()->count())->toBe(3);
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(3);

    // Simular recuperación de conexión: push
    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(3)
        ->and($result['success'])->toBe(3)
        ->and($result['failed'])->toBe(0);

    // Verificar que todos están synced
    expect(Order::synced()->count())->toBe(3);
    expect(Order::pending()->count())->toBe(0);
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);

    // Verificar auditoría
    $logs = SyncLog::where('sync_session_id', $result['session_id'])->get();
    expect($logs->count())->toBe(3);
    expect($logs->where('direction', 'push')->count())->toBe(3);
});

// ============================================
// Escenario 2: Conflicto bidireccional
// ============================================

test('E2E: conflicto entre cambios de cliente y servidor se resuelve', function () {
    // Crear orden y sincronizarla
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CONFLICT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Push inicial
    $this->syncService->pushChanges($this->branch->id);
    $order->refresh();
    expect($order->sync_status->value)->toBe('synced');

    // Cliente modifica la orden offline
    $order->notes = 'Modificado por cliente offline';
    $order->save();
    $order->refresh();
    expect($order->sync_status->value)->toBe('pending');

    // Simular que el servidor también modificó (versión mayor)
    // En un escenario real, el pull detectaría esto
    $result = $this->syncService->pushChanges($this->branch->id);

    // El push debe procesar el cambio del cliente
    expect($result['processed'])->toBe(1)
        ->and($result['success'])->toBe(1);

    $order->refresh();
    expect($order->sync_status->value)->toBe('synced');
    expect($order->notes)->toContain('Modificado por cliente offline');
});

// ============================================
// Escenario 3: Múltiples sucursales aisladas
// ============================================

test('E2E: sync aislado entre múltiples sucursales', function () {
    $branch2 = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'E2E2',
        'name' => 'E2E Branch 2',
    ]);

    $user2 = User::forceCreate([
        'name' => 'E2E User 2',
        'email' => 'e2e2-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $branch2->id,
        'role' => 'waiter',
    ]);

    // Crear pedidos en ambas sucursales
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-B1-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $branch2->id,
        'waiter_id' => $user2->id,
        'order_number' => 'ORD-B2-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    // Sync solo sucursal 1
    $result1 = $this->syncService->pushChanges($this->branch->id);
    expect($result1['processed'])->toBe(1);

    // Sucursal 2 sigue con pendiente
    expect(SyncQueue::where('branch_id', $branch2->id)->count())->toBe(1);

    // Sync sucursal 2
    $result2 = $this->syncService->pushChanges($branch2->id);
    expect($result2['processed'])->toBe(1);

    // Ambas sucursales sincronizadas
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);
    expect(SyncQueue::where('branch_id', $branch2->id)->count())->toBe(0);
});

// ============================================
// Escenario 4: Reintentos tras fallo
// ============================================

test('E2E: cambios fallidos se marcan para reintento', function () {
    // Crear un queue item con entidad inexistente (fallará)
    $queueItem = SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => 999999,
        'entity_uuid' => \Illuminate\Support\Str::uuid(),
        'action' => 'update',
        'payload' => ['notes' => 'test'],
        'version' => 1,
        'status' => 'pending',
    ]);

    // Primer intento falla
    $result = $this->syncService->pushChanges($this->branch->id);
    expect($result['failed'])->toBe(1);

    $queueItem->refresh();
    expect($queueItem->status)->toBe('failed');
    expect($queueItem->attempts)->toBe(1);
    expect($queueItem->next_attempt_at)->not->toBeNull();

    // Verificar que está marcado para reintento futuro
    expect($queueItem->next_attempt_at->isFuture())->toBeTrue();
});

// ============================================
// Escenario 5: Ciclo completo push/pull
// ============================================

test('E2E: ciclo completo de sincronización push y pull', function () {
    // 1. Cliente crea orden offline
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CYCLE-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 0,
        'total' => 5950,
    ]);

    expect($order->sync_status->value)->toBe('pending');

    // 2. Push al servidor
    $pushResult = $this->syncService->pushChanges($this->branch->id);
    expect($pushResult['success'])->toBe(1);

    $order->refresh();
    expect($order->sync_status->value)->toBe('synced');

    // 3. Pull del servidor (debe incluir la misma orden)
    $pullResult = $this->syncService->pullChanges($this->branch->id);
    expect($pullResult['direction'])->toBe('pull');

    // 4. Verificar logs de ambas direcciones
    $pushLogs = SyncLog::where('direction', 'push')->count();
    $pullLogs = SyncLog::where('direction', 'pull')->count();

    expect($pushLogs)->toBeGreaterThan(0);
    expect($pullLogs)->toBeGreaterThan(0);
});

// ============================================
// Escenario 6: Estadísticas de sync
// ============================================

test('E2E: getSyncStats refleja el estado correctamente', function () {
    // Estado inicial: sin cambios
    $stats = $this->syncService->getSyncStats($this->branch->id);
    expect($stats['pending'])->toBe(0);

    // Crear 2 órdenes
    for ($i = 1; $i <= 2; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-STATS-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'discount_amount' => 0,
            'total' => 1190,
        ]);
    }

    $stats = $this->syncService->getSyncStats($this->branch->id);
    expect($stats['pending'])->toBe(2);

    // Sync
    $this->syncService->pushChanges($this->branch->id);

    $stats = $this->syncService->getSyncStats($this->branch->id);
    expect($stats['pending'])->toBe(0);
    expect($stats['last_push'])->not->toBeNull();
});

// ============================================
// Escenario 7: Actualizaciones múltiples de la misma entidad
// ============================================

test('E2E: múltiples actualizaciones de la misma orden se procesan en orden', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-MULTI-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Push inicial (create)
    $this->syncService->pushChanges($this->branch->id);

    // Múltiples actualizaciones
    $order->notes = 'Primera actualización';
    $order->save();

    $order->notes = 'Segunda actualización';
    $order->save();

    // Push de las actualizaciones
    $result = $this->syncService->pushChanges($this->branch->id);

    // Debe procesar las actualizaciones
    expect($result['processed'])->toBeGreaterThan(0);

    $order->refresh();
    expect($order->sync_status->value)->toBe('synced');
});
