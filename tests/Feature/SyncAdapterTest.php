<?php

use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Sync\Domain\Services\SyncAdapter;
use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Modules\Sync\Domain\Services\EntityMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.111.222-3',
        'legal_name' => 'Adapter Test SpA',
        'trade_name' => 'Adapter Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'ADAPT',
        'name' => 'Adapter Branch',
    ]);

    $this->user = User::forceCreate([
        'name' => 'Adapter User',
        'email' => 'adapter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Usar BD temporal para tests
    $this->testDbPath = sys_get_temp_dir() . '/adapter_test_' . uniqid() . '.sqlite';
    $this->localDb = new LocalDatabaseManager($this->testDbPath);
    $this->adapter = new SyncAdapter($this->localDb, new EntityMapper());
});

afterEach(function () {
    if (file_exists($this->testDbPath)) {
        unlink($this->testDbPath);
    }
});

test('EntityMapper convierte Order a formato local correctamente', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-MAP-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'notes' => 'Nota de prueba',
    ]);

    $mapper = new EntityMapper();
    $localData = $mapper->orderToLocal($order);

    expect($localData['uuid'])->toBe($order->uuid)
        ->and($localData['server_id'])->toBe($order->id)
        ->and($localData['branch_id'])->toBe($this->branch->id)
        ->and($localData['order_number'])->toBe('ORD-MAP-001')
        ->and($localData['type'])->toBe('dine_in')
        ->and($localData['status'])->toBe('draft')
        ->and($localData['subtotal'])->toBe(1000.0)
        ->and($localData['notes'])->toBe('Nota de prueba');
});

test('EntityMapper convierte datos locales a Order correctamente', function () {
    $localData = [
        'uuid' => 'test-uuid-123',
        'branch_id' => 1,
        'order_number' => 'ORD-LOCAL-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 2000,
        'tax_amount' => 380,
        'total' => 2380,
    ];

    $mapper = new EntityMapper();
    $serverData = $mapper->localToOrder($localData);

    expect($serverData['uuid'])->toBe('test-uuid-123')
        ->and($serverData['order_number'])->toBe('ORD-LOCAL-001')
        ->and($serverData['subtotal'])->toBe(2000)
        ->and($serverData['sync_status'])->toBe('pending');
});

test('SyncAdapter exporta órdenes a BD local', function () {
    // Crear orden en el servidor
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-EXPORT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Exportar a local
    $result = $this->adapter->exportOrdersToLocal($this->branch->id);

    expect($result['exported_orders'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();

    // Verificar que está en SQLite
    $localConnection = DB::connection('sqlite_local');
    $localOrder = $localConnection->table('local_orders')
        ->where('uuid', $order->uuid)
        ->first();

    expect($localOrder)->not->toBeNull()
        ->and($localOrder->order_number)->toBe('ORD-EXPORT-001');
});

test('SyncAdapter exporta órdenes con items', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-ITEMS-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    // Crear items
    OrderItem::create([
        'company_id' => $this->company->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Producto 1',
        'unit_price_snapshot' => 1000,
        'quantity' => 2,
        'subtotal' => 2000,
    ]);

    $result = $this->adapter->exportOrdersToLocal($this->branch->id);

    expect($result['exported_orders'])->toBe(1)
        ->and($result['exported_items'])->toBe(1);

    // Verificar items en SQLite
    $localConnection = DB::connection('sqlite_local');
    $localItems = $localConnection->table('local_order_items')->get();
    
    expect($localItems->count())->toBe(1)
        ->and($localItems->first()->name_snapshot)->toBe('Producto 1');
});

test('SyncAdapter importa órdenes locales al servidor', function () {
    // Inicializar BD local
    $this->localDb->initialize();

    // Insertar orden directamente en SQLite (simula creación offline)
    $localConnection = DB::connection('sqlite_local');
    $localConnection->table('local_orders')->insert([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-IMPORT-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 3000,
        'tax_amount' => 570,
        'discount_amount' => 0,
        'total' => 3570,
        'sync_status' => 'pending',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Importar al servidor
    $result = $this->adapter->importOrdersFromLocal($this->branch->id);

    expect($result['imported_orders'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();

    // Verificar que está en Postgres
    $serverOrder = Order::withoutGlobalScopes()
        ->where('order_number', 'ORD-IMPORT-001')
        ->first();

    expect($serverOrder)->not->toBeNull()
        ->and((float) $serverOrder->subtotal)->toBe(3000.0);
});

test('SyncAdapter actualiza metadata de exportación', function () {
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-META-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    $this->adapter->exportOrdersToLocal($this->branch->id);

    $lastExport = $this->adapter->getMetadata('last_export_at');
    expect($lastExport)->not->toBeNull();

    $lastBranch = $this->adapter->getMetadata('last_export_branch_id');
    expect($lastBranch)->toBe((string) $this->branch->id);
});

test('SyncAdapter aísla exportación por sucursal', function () {
    $otherBranch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'OTHER',
        'name' => 'Other Branch',
    ]);

    // Orden en otra sucursal
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-OTHER-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
    ]);

    // Orden en sucursal original
    Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-LOCAL-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 2000,
        'tax_amount' => 380,
        'discount_amount' => 0,
        'total' => 2380,
    ]);

    // Exportar solo la sucursal original
    $result = $this->adapter->exportOrdersToLocal($this->branch->id);

    expect($result['exported_orders'])->toBe(1);

    // Verificar que solo está la orden de la sucursal original
    $localConnection = DB::connection('sqlite_local');
    $localOrders = $localConnection->table('local_orders')->get();
    
    expect($localOrders->count())->toBe(1)
        ->and($localOrders->first()->order_number)->toBe('ORD-LOCAL-001');
});
