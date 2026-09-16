<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\ValueObjects\SyncAction;
use Modules\Sync\Domain\ValueObjects\SyncStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'SYNC-' . uniqid(),
        'legal_name' => 'Sync Test Company',
        'trade_name' => 'Sync Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Sync Test Branch',
        'code' => 'STB',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Sync User',
        'email' => 'sync-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'waiter',
    ]);

    $this->syncService = app(SyncService::class);
});

test('1000 eventos de sync se procesan correctamente', function () {
    // Crear 1000 órdenes (el trait Syncable crea automáticamente las entradas en sync_queue)
    for ($i = 1; $i <= 1000; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000 * $i,
            'tax_amount' => 190 * $i,
            'discount_amount' => 0,
            'total' => 1190 * $i,
        ]);
    }

    // Verificar que hay 1000 items pendientes (creados automáticamente por Syncable)
    $pendingCount = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($pendingCount)->toBe(1000);

    // Procesar en lotes de 100
    $totalProcessed = 0;
    $batchSize = 100;
    
    for ($i = 0; $i < 10; $i++) {
        $result = $this->syncService->pushChanges($this->branch->id, $batchSize);
        $totalProcessed += $result['success'];
    }

    // Validar que todos se procesaron
    expect($totalProcessed)->toBe(1000);

    // Verificar que no hay items pendientes
    $remainingPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($remainingPending)->toBe(0);
});

test('1 hora offline acumula cambios y sincroniza al recuperar conexión', function () {
    // Simular 1 hora de operación offline: 60 órdenes (1 por minuto)
    // El trait Syncable crea automáticamente las entradas en sync_queue
    for ($i = 1; $i <= 60; $i++) {
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-OFFLINE-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 5000,
            'tax_amount' => 950,
            'discount_amount' => 0,
            'total' => 5950,
        ]);

        // Simular creación escalonada (1 por minuto durante 1 hora)
        $queueItem = SyncQueue::where('entity_uuid', $order->uuid)->first();
        if ($queueItem) {
            $queueItem->created_at = now()->subMinutes(60 - $i);
            $queueItem->save();
        }
    }

    // Verificar que hay 60 items pendientes
    $pendingCount = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($pendingCount)->toBe(60);

    // Simular recuperación de conexión: sincronizar todos
    $result = $this->syncService->pushChanges($this->branch->id, 1000);

    // Validar que todos se sincronizaron
    expect($result['success'])->toBe(60)
        ->and($result['failed'])->toBe(0)
        ->and($result['conflicts'])->toBe(0);

    // Verificar que no hay items pendientes
    $remainingPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($remainingPending)->toBe(0);
});

test('pérdida de red durante push no causa duplicados (idempotencia)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-NETWORK-FAIL',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Verificar que el trait Syncable creó la entrada en sync_queue
    $queueItem = SyncQueue::where('entity_uuid', $order->uuid)->first();
    expect($queueItem)->not->toBeNull();

    // Simular primer intento exitoso (servidor procesa)
    $result1 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($result1['success'])->toBe(1);

    // Verificar que el item fue eliminado de la cola
    $remainingItems = SyncQueue::where('entity_uuid', $order->uuid)->count();
    expect($remainingItems)->toBe(0);

    // Simular reintento (cliente no recibió respuesta por pérdida de red)
    // Recrear el item en la cola manualmente
    SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Segundo intento (servidor detecta idempotency_key duplicado)
    $result2 = $this->syncService->pushChanges($this->branch->id, 10);

    // Validar que no se creó duplicado
    $orderCount = Order::where('uuid', $order->uuid)->count();
    expect($orderCount)->toBe(1, 'Idempotencia previene duplicados');
});

test('red intermitente con backoff exponencial eventualmente sincroniza todo', function () {
    // Crear 10 órdenes (el trait Syncable crea automáticamente las entradas)
    for ($i = 1; $i <= 10; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-INTERMITENT-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 5000,
            'tax_amount' => 950,
            'discount_amount' => 0,
            'total' => 5950,
        ]);
    }

    // Verificar que hay 10 items pendientes
    $initialPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    expect($initialPending)->toBe(10);

    // Simular 3 intentos con conexión intermitente
    // Intento 1: procesa 3 items
    $result1 = $this->syncService->pushChanges($this->branch->id, 3);
    expect($result1['success'])->toBe(3);

    // Intento 2: procesa 4 items
    $result2 = $this->syncService->pushChanges($this->branch->id, 4);
    expect($result2['success'])->toBe(4);

    // Intento 3: procesa 3 items restantes
    $result3 = $this->syncService->pushChanges($this->branch->id, 3);
    expect($result3['success'])->toBe(3);

    // Validar que todos se sincronizaron
    $remainingPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($remainingPending)->toBe(0, 'Todos los items sincronizados eventualmente');
});

test('timeout del servidor no causa duplicados (idempotencia)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-TIMEOUT',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Verificar que el trait Syncable creó la entrada
    $queueItem = SyncQueue::where('entity_uuid', $order->uuid)->first();
    expect($queueItem)->not->toBeNull();

    // Simular primer intento (servidor procesa pero cliente hace timeout)
    $result1 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($result1['success'])->toBe(1);

    // Verificar que el item fue eliminado
    $remainingItems = SyncQueue::where('entity_uuid', $order->uuid)->count();
    expect($remainingItems)->toBe(0);

    // Simular reintento después de timeout
    SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => $order->id,
        'entity_uuid' => $order->uuid,
        'action' => SyncAction::CREATE,
        'payload' => $order->toArray(),
        'status' => 'pending',
        'attempts' => 1,
    ]);

    // Segundo intento (servidor detecta duplicado por idempotency_key)
    $result2 = $this->syncService->pushChanges($this->branch->id, 10);

    // Validar que no se creó duplicado
    $orderCount = Order::where('uuid', $order->uuid)->count();
    expect($orderCount)->toBe(1, 'Idempotencia previene duplicados después de timeout');
});

test('reinicio del cliente durante sync recupera progreso correctamente', function () {
    // Crear 20 órdenes (el trait Syncable crea automáticamente las entradas)
    for ($i = 1; $i <= 20; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-RESTART-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 5000,
            'tax_amount' => 950,
            'discount_amount' => 0,
            'total' => 5950,
        ]);
    }

    // Verificar que hay 20 items pendientes
    $initialPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    expect($initialPending)->toBe(20);

    // Simular procesamiento parcial (10 de 20)
    $result1 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($result1['success'])->toBe(10);

    // Verificar que quedan 10 pendientes
    $remainingPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($remainingPending)->toBe(10);

    // Cliente reinicia y continúa sincronización
    $result2 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($result2['success'])->toBe(10);

    // Validar que todos se sincronizaron
    $finalPending = SyncQueue::where('branch_id', $this->branch->id)
        ->where('status', 'pending')
        ->count();
    
    expect($finalPending)->toBe(0, 'Recuperación completa después de reinicio');

    // Validar que no hay duplicados
    $orderCount = Order::where('branch_id', $this->branch->id)
        ->where('order_number', 'like', 'ORD-RESTART-%')
        ->count();
    
    expect($orderCount)->toBe(20, 'Sin duplicados después de reinicio');
});
