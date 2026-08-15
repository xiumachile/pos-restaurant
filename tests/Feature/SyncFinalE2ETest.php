<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Entities\SyncQueue;
use Modules\Sync\Domain\Entities\SyncLog;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\SyncAdapter;
use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Modules\Sync\Domain\Services\EntityMapper;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.555.444-3',
        'legal_name' => 'Final E2E SpA',
        'trade_name' => 'Final E2E',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'FINAL',
        'name' => 'Final E2E Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Final User',
        'email' => 'final-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->testDbPath = sys_get_temp_dir() . '/final_e2e_' . uniqid() . '.sqlite';
    $this->localDb = new LocalDatabaseManager($this->testDbPath);
    $this->adapter = new SyncAdapter($this->localDb, new EntityMapper());
    $this->syncService = new SyncService();
});

afterEach(function () {
    if (file_exists($this->testDbPath)) {
        unlink($this->testDbPath);
    }
});

// ============================================
// Escenario 1: API completa con SQLite
// ============================================

test('FINAL E2E: flujo completo API → SyncService → SQLite', function () {
    // 1. Crear órdenes en servidor
    for ($i = 1; $i <= 5; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-FINAL-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000 * $i,
            'tax_amount' => 190 * $i,
            'discount_amount' => 0,
            'total' => 1190 * $i,
        ]);
    }

    // 2. Push via API (simulado con servicio)
    $pushResult = $this->syncService->pushChanges($this->branch->id);
    expect($pushResult['success'])->toBe(5);

    // 3. Exportar a SQLite
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(5);

    // 4. Verificar en SQLite
    $localConnection = DB::connection('sqlite_local');
    $localOrders = $localConnection->table('local_orders')->get();
    expect($localOrders->count())->toBe(5);

    // 5. Modificar localmente
    $localConnection->table('local_orders')
        ->where('order_number', 'ORD-FINAL-1')
        ->update([
            'notes' => 'Modificado offline',
            'sync_status' => 'pending',
            'version' => 10,
        ]);

    // 6. Importar de vuelta al servidor
    $importResult = $this->adapter->importOrdersFromLocal($this->branch->id);
    expect($importResult['imported_orders'])->toBe(1);

    // 7. Verificar que el cambio está en Postgres
    $updatedOrder = Order::withoutGlobalScopes()
        ->where('order_number', 'ORD-FINAL-1')
        ->first();
    expect($updatedOrder->notes)->toContain('Modificado offline');
});

// ============================================
// Escenario 2: Estrés con múltiples cambios
// ============================================

test('FINAL E2E: procesamiento de 50 cambios simultáneos', function () {
    // Crear 50 órdenes
    for ($i = 1; $i <= 50; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => sprintf('ORD-STRESS-%03d', $i),
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'discount_amount' => 0,
            'total' => 1190,
        ]);
    }

    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(50);

    // Procesar todos
    $result = $this->syncService->pushChanges($this->branch->id, 100);

    expect($result['processed'])->toBe(50)
        ->and($result['success'])->toBe(50)
        ->and($result['failed'])->toBe(0);

    // Verificar que la cola quedó vacía
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);

    // Verificar auditoría
    $logs = SyncLog::where('sync_session_id', $result['session_id'])->count();
    expect($logs)->toBe(50);
});

// ============================================
// Escenario 3: Seguridad multi-tenant
// ============================================

test('FINAL E2E: aislamiento estricto entre empresas', function () {
    // Crear otra empresa
    $otherCompany = Company::forceCreate([
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Other Company SpA',
        'trade_name' => 'Other Company',
    ]);

    $otherBranch = Branch::forceCreate([
        'company_id' => $otherCompany->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    $otherUser = User::forceCreate([
        'name' => 'Other User',
        'email' => 'other-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'role' => 'waiter',
    ]);

    // Crear órdenes en ambas empresas
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-COMPANY-A',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    Order::create([
        'company_id' => $otherCompany->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $otherUser->id,
        'order_number' => 'ORD-COMPANY-B',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    // Sync solo empresa A
    $result = $this->syncService->pushChanges($this->branch->id);
    expect($result['processed'])->toBe(1);

    // Exportar solo empresa A a SQLite
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(1);

    // Verificar que solo está la orden de empresa A en SQLite
    $localConnection = DB::connection('sqlite_local');
    $localOrders = $localConnection->table('local_orders')->get();
    
    expect($localOrders->count())->toBe(1);
    expect($localOrders->first()->order_number)->toBe('ORD-COMPANY-A');

    // La orden de empresa B NO debe estar en SQLite
    $otherOrderInLocal = $localConnection->table('local_orders')
        ->where('order_number', 'ORD-COMPANY-B')
        ->count();
    expect($otherOrderInLocal)->toBe(0);
});

// ============================================
// Escenario 4: Resiliencia ante fallos
// ============================================

test('FINAL E2E: recuperación de errores con reintentos', function () {
    // Crear un queue item con entidad inexistente (fallará)
    SyncQueue::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'entity_type' => Order::class,
        'entity_id' => 999999,
        'entity_uuid' => Str::uuid(),
        'action' => 'update',
        'payload' => ['notes' => 'test'],
        'version' => 1,
        'status' => 'pending',
    ]);

    // Crear un queue item válido (éxito)
    $validOrder = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-VALID-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Procesar ambos
    $result = $this->syncService->pushChanges($this->branch->id);

    expect($result['processed'])->toBe(2)
        ->and($result['success'])->toBe(1)
        ->and($result['failed'])->toBe(1);

    // El item fallido debe estar marcado para reintento
    $failedItem = SyncQueue::where('entity_id', 999999)->first();
    expect($failedItem->status)->toBe('failed');
    expect($failedItem->attempts)->toBe(1);
    expect($failedItem->next_attempt_at)->not->toBeNull();

    // El item válido debe haberse eliminado de la cola
    $validItem = SyncQueue::where('entity_id', $validOrder->id)->count();
    expect($validItem)->toBe(0);
});

// ============================================
// Escenario 5: Rendimiento con límites
// ============================================

test('FINAL E2E: batching con límites configurables', function () {
    // Crear 30 órdenes
    for ($i = 1; $i <= 30; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => sprintf('ORD-BATCH-%03d', $i),
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'discount_amount' => 0,
            'total' => 1190,
        ]);
    }

    // Procesar en lotes de 10
    $batch1 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($batch1['processed'])->toBe(10);

    $batch2 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($batch2['processed'])->toBe(10);

    $batch3 = $this->syncService->pushChanges($this->branch->id, 10);
    expect($batch3['processed'])->toBe(10);

    // Verificar que todo se procesó
    expect(SyncQueue::where('branch_id', $this->branch->id)->count())->toBe(0);
});

// ============================================
// Escenario 6: Auditoría completa
// ============================================

test('FINAL E2E: auditoría completa de operaciones de sync', function () {
    // Crear orden
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-AUDIT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Push
    $pushResult = $this->syncService->pushChanges($this->branch->id);

    // Pull
    $pullResult = $this->syncService->pullChanges($this->branch->id);

    // Verificar logs de push
    $pushLogs = SyncLog::where('direction', 'push')->get();
    expect($pushLogs->count())->toBeGreaterThan(0);

    // Verificar logs de pull
    $pullLogs = SyncLog::where('direction', 'pull')->get();
    expect($pullLogs->count())->toBeGreaterThan(0);

    // Verificar que los logs tienen la estructura correcta
    $firstLog = $pushLogs->first();
    expect($firstLog)->toHaveKeys([
        'uuid', 'sync_session_id', 'direction', 'entity_type',
        'entity_id', 'action', 'result', 'synced_at',
    ]);
});

// ============================================
// Escenario 7: Ciclo completo de vida de una orden
// ============================================

test('FINAL E2E: ciclo completo de vida de una orden offline', function () {
    // 1. Cliente crea orden offline (en SQLite)
    $this->localDb->initialize();
    $localConnection = DB::connection('sqlite_local');

    $localUuid = Str::uuid();
    $localConnection->table('local_orders')->insert([
        'uuid' => $localUuid,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-LIFECYCLE-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'sync_status' => 'pending',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Cliente recupera conexión e importa
    $importResult = $this->adapter->importOrdersFromLocal($this->branch->id);
    expect($importResult['imported_orders'])->toBe(1);

    // 3. Verificar en servidor
    $serverOrder = Order::withoutGlobalScopes()
        ->where('uuid', $localUuid)
        ->first();
    expect($serverOrder)->not->toBeNull();

    // 4. Servidor modifica la orden (cambio de estado)
    app()->instance('sync.is_syncing', true);
    try {
        $serverOrder->status = OrderStatus::CONFIRMED;
        $serverOrder->save();
    } finally {
        app()->instance('sync.is_syncing', false);
    }

    // 5. Push del cambio
    $this->syncService->pushChanges($this->branch->id);

    // 6. Exportar de vuelta a local
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(1);

    // 7. Verificar que el cambio está en SQLite
    $localOrder = $localConnection->table('local_orders')
        ->where('uuid', $localUuid)
        ->first();
    expect($localOrder->status)->toBe('confirmed');
});
