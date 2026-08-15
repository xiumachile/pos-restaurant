<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Services\SyncAdapter;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Modules\Sync\Domain\Services\EntityMapper;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.999.888-7',
        'legal_name' => 'Full Integration SpA',
        'trade_name' => 'Full Integration',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'FULL',
        'name' => 'Full Integration Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Integration User',
        'email' => 'integration-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->testDbPath = sys_get_temp_dir() . '/full_integration_' . uniqid() . '.sqlite';
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
// Escenario 1: Flujo completo offline
// ============================================

test('INTEGRACIÓN: flujo completo de operación offline', function () {
    // 1. Cliente opera offline: crea pedidos localmente
    $this->localDb->initialize();
    $localConnection = DB::connection('sqlite_local');

    $localUuid = Str::uuid();
    $localConnection->table('local_orders')->insert([
        'uuid' => $localUuid,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-OFFLINE-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 5000,
        'tax_amount' => 950,
        'discount_amount' => 0,
        'total' => 5950,
        'sync_status' => 'pending',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verificar que está en SQLite
    expect($localConnection->table('local_orders')->count())->toBe(1);

    // 2. Cliente recupera conexión: importa al servidor
    $importResult = $this->adapter->importOrdersFromLocal($this->branch->id);
    
    // Debug: ver resultado
    // dump('Import result:', $importResult);
    
    // Verificar que la importación fue exitosa
    expect($importResult['imported_orders'])->toBe(1)
        ->and($importResult['errors'])->toBeEmpty();

    // 3. Verificar que está en Postgres
    $serverOrder = Order::withoutGlobalScopes()
        ->where('uuid', $localUuid)
        ->first();
    
    expect($serverOrder)->not->toBeNull()
        ->and($serverOrder->order_number)->toBe('ORD-OFFLINE-001')
        ->and((float) $serverOrder->subtotal)->toBe(5000.0);

    // 4. La orden local debe estar marcada como synced
    $localOrder = $localConnection->table('local_orders')
        ->where('uuid', $localUuid)
        ->first();
    expect($localOrder->sync_status)->toBe('synced');
});

// ============================================
// Escenario 2: Sincronización bidireccional
// ============================================

test('INTEGRACIÓN: sincronización bidireccional push y pull', function () {
    // 1. Crear orden en servidor
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-BIDIR-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 3000,
        'tax_amount' => 570,
        'discount_amount' => 0,
        'total' => 3570,
    ]);

    // 2. Push al servidor (ya está en servidor, pero genera queue)
    $pushResult = $this->syncService->pushChanges($this->branch->id);
    expect($pushResult['processed'])->toBe(1);

    // 3. Exportar a local (simula cliente descargando datos)
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(1);

    // 4. Verificar que está en SQLite
    $localConnection = DB::connection('sqlite_local');
    $localOrder = $localConnection->table('local_orders')
        ->where('uuid', $order->uuid)
        ->first();
    expect($localOrder)->not->toBeNull();

    // 5. Pull del servidor (verifica consistencia)
    $pullResult = $this->syncService->pullChanges($this->branch->id);
    expect($pullResult['direction'])->toBe('pull');
});

// ============================================
// Escenario 3: Múltiples ciclos de sync
// ============================================

test('INTEGRACIÓN: múltiples ciclos de sincronización', function () {
    // Ciclo 1: Crear y exportar
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CYCLE-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $this->adapter->exportOrdersToLocal($this->branch->id);
    $this->syncService->pushChanges($this->branch->id);

    // Ciclo 2: Modificar en servidor
    $order->notes = 'Modificado en ciclo 2';
    $order->save();
    $this->syncService->pushChanges($this->branch->id);

    // Re-exportar a local
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(1);

    // Verificar que la modificación está en local
    $localConnection = DB::connection('sqlite_local');
    $localOrder = $localConnection->table('local_orders')
        ->where('uuid', $order->uuid)
        ->first();
    expect($localOrder->notes)->toContain('Modificado en ciclo 2');

    // Ciclo 3: Modificar localmente e importar
    $localConnection->table('local_orders')
        ->where('uuid', $order->uuid)
        ->update([
            'notes' => 'Modificado localmente en ciclo 3',
            'sync_status' => 'pending',
            'version' => 5, // Versión mayor para forzar actualización
        ]);

    $importResult = $this->adapter->importOrdersFromLocal($this->branch->id);
    expect($importResult['imported_orders'])->toBe(1);

    // Verificar que el cambio está en el servidor
    $order->refresh();
    expect($order->notes)->toContain('Modificado localmente en ciclo 3');
});

// ============================================
// Escenario 4: Recuperación tras pérdida de datos
// ============================================

test('INTEGRACIÓN: recuperación tras pérdida de BD local', function () {
    // 1. Crear órdenes en servidor
    for ($i = 1; $i <= 3; $i++) {
        Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-RECOVERY-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 1000 * $i,
            'tax_amount' => 190 * $i,
            'discount_amount' => 0,
            'total' => 1190 * $i,
            'sync_status' => 'synced',
        ]);
    }

    // 2. Push para marcar como synced
    $this->syncService->pushChanges($this->branch->id);

    // 3. Simular pérdida de BD local
    $this->localDb->clear();
    expect($this->localDb->isAvailable())->toBeTrue();

    // 4. Recuperar datos desde el servidor
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(3);

    // 5. Verificar que todos los datos están recuperados
    $localConnection = DB::connection('sqlite_local');
    $localOrders = $localConnection->table('local_orders')->get();
    expect($localOrders->count())->toBe(3);
});

// ============================================
// Escenario 5: Consistencia Postgres ↔ SQLite
// ============================================

test('INTEGRACIÓN: consistencia de datos entre Postgres y SQLite', function () {
    // Crear órdenes con items en servidor
    $orders = [];
    for ($i = 1; $i <= 2; $i++) {
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'waiter_id' => $this->user->id,
            'order_number' => "ORD-CONSIST-{$i}",
            'type' => OrderType::DINE_IN,
            'status' => OrderStatus::DRAFT,
            'subtotal' => 2000 * $i,
            'tax_amount' => 380 * $i,
            'discount_amount' => 0,
            'total' => 2380 * $i,
        ]);

        OrderItem::create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'name_snapshot' => "Producto {$i}",
            'unit_price_snapshot' => 1000 * $i,
            'quantity' => 2,
            'subtotal' => 2000 * $i,
        ]);

        $orders[] = $order;
    }

    // Exportar a local
    $exportResult = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($exportResult['exported_orders'])->toBe(2);
    expect($exportResult['exported_items'])->toBe(2);

    // Verificar consistencia de órdenes
    $localConnection = DB::connection('sqlite_local');
    foreach ($orders as $order) {
        $localOrder = $localConnection->table('local_orders')
            ->where('uuid', $order->uuid)
            ->first();
        
        expect($localOrder)->not->toBeNull();
        expect((float) $localOrder->subtotal)->toBe((float) $order->subtotal);
        expect((float) $localOrder->total)->toBe((float) $order->total);
        expect($localOrder->status)->toBe($order->status->value);
    }

    // Verificar consistencia de items
    $localItems = $localConnection->table('local_order_items')->get();
    expect($localItems->count())->toBe(2);
});

// ============================================
// Escenario 6: Metadata de sync completa
// ============================================

test('INTEGRACIÓN: metadata de sync registra operaciones correctamente', function () {
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-METADATA-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Exportar
    $this->adapter->exportOrdersToLocal($this->branch->id);

    // Verificar metadata de exportación
    expect($this->adapter->getMetadata('last_export_at'))->not->toBeNull();
    expect($this->adapter->getMetadata('last_export_branch_id'))->toBe((string) $this->branch->id);

    // Importar
    $localConnection = DB::connection('sqlite_local');
    $localConnection->table('local_orders')->update([
        'sync_status' => 'pending',
        'notes' => 'Cambiado localmente',
    ]);

    $this->adapter->importOrdersFromLocal($this->branch->id);

    // Verificar metadata de importación
    expect($this->adapter->getMetadata('last_import_at'))->not->toBeNull();
});

// ============================================
// Escenario 7: Operación con múltiples sucursales
// ============================================

test('INTEGRACIÓN: operación offline aislada entre múltiples sucursales', function () {
    $branch2 = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'FULL2',
        'name' => 'Full Branch 2',
    ]);

    // Crear órdenes en ambas sucursales
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
        'sync_status' => 'synced',
    ]);

    $user2 = User::forceCreate([
        'name' => 'User 2',
        'email' => 'user2-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $branch2->id,
        'role' => 'waiter',
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
        'sync_status' => 'synced',
    ]);

    // Exportar solo sucursal 1
    $result = $this->adapter->exportOrdersToLocal($this->branch->id);
    expect($result['exported_orders'])->toBe(1);

    // Verificar que solo está la orden de la sucursal 1 en local
    $localConnection = DB::connection('sqlite_local');
    $localOrders = $localConnection->table('local_orders')->get();
    
    expect($localOrders->count())->toBe(1);
    expect($localOrders->first()->order_number)->toBe('ORD-B1-001');
});
