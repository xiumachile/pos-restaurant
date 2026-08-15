<?php

use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Usar un archivo temporal para tests
    $this->testDbPath = sys_get_temp_dir() . '/test_local_' . uniqid() . '.sqlite';
    $this->manager = new LocalDatabaseManager($this->testDbPath);
});

afterEach(function () {
    // Limpiar archivo temporal
    if (file_exists($this->testDbPath)) {
        unlink($this->testDbPath);
    }
});

test('LocalDatabaseManager inicializa BD local correctamente', function () {
    $result = $this->manager->initialize();

    expect($result)->toBeTrue()
        ->and(file_exists($this->testDbPath))->toBeTrue();
});

test('LocalDatabaseManager crea tablas locales', function () {
    $this->manager->initialize();

    // Verificar que las tablas existen en SQLite
    $connection = DB::connection('sqlite_local');
    $schema = $connection->getSchemaBuilder();

    expect($schema->hasTable('local_orders'))->toBeTrue()
        ->and($schema->hasTable('local_order_items'))->toBeTrue()
        ->and($schema->hasTable('local_sync_metadata'))->toBeTrue();
});

test('LocalDatabaseManager verifica disponibilidad', function () {
    expect($this->manager->isAvailable())->toBeFalse();

    $this->manager->initialize();

    expect($this->manager->isAvailable())->toBeTrue();
});

test('LocalDatabaseManager retorna tamaño de BD', function () {
    expect($this->manager->getDatabaseSize())->toBe(0);

    $this->manager->initialize();

    expect($this->manager->getDatabaseSize())->toBeGreaterThan(0);
});

test('LocalDatabaseManager limpia BD correctamente', function () {
    $this->manager->initialize();
    expect($this->manager->isAvailable())->toBeTrue();

    $result = $this->manager->clear();

    expect($result)->toBeTrue()
        ->and($this->manager->isAvailable())->toBeTrue();
});

test('LocalDatabaseManager permite insertar datos locales', function () {
    $this->manager->initialize();

    $connection = DB::connection('sqlite_local');

    // Insertar una orden local
    $connection->table('local_orders')->insert([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'branch_id' => 1,
        'order_number' => 'ORD-LOCAL-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 1000,
        'tax_amount' => 190,
        'discount_amount' => 0,
        'total' => 1190,
        'sync_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $count = $connection->table('local_orders')->count();
    expect($count)->toBe(1);
});

test('LocalDatabaseManager aísla datos de la BD principal', function () {
    $this->manager->initialize();

    // Insertar en SQLite local
    $localConnection = DB::connection('sqlite_local');
    $localConnection->table('local_orders')->insert([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'branch_id' => 1,
        'order_number' => 'ORD-ISOLATED-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
        'sync_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verificar que NO está en la BD principal (Postgres)
    $mainCount = DB::connection('pgsql')->table('orders')
        ->where('order_number', 'ORD-ISOLATED-001')
        ->count();

    expect($mainCount)->toBe(0);
});
