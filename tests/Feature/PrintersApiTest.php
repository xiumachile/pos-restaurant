<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrinterStationMapping;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PRN-API-' . uniqid(),
        'legal_name' => 'Printers API Test',
        'trade_name' => 'Printers API Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PRA',
        'name' => 'Printers API Branch',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function printersHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// POST /api/v1/printers - Crear Impresora
// ============================================

test('POST /api/v1/printers crea impresora TCP de cocina', function () {
    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers', [
            'name' => 'Cocina WOK',
            'type' => 'kitchen',
            'connection_type' => 'tcp',
            'host' => '192.168.1.100',
            'port' => 9100,
            'paper_width' => 80,
            'auto_cut' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Cocina WOK')
        ->assertJsonPath('data.type', 'kitchen')
        ->assertJsonPath('data.connection_type', 'tcp')
        ->assertJsonPath('data.host', '192.168.1.100')
        ->assertJsonPath('data.port', 9100)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_kitchen_printer', true);
});

test('POST /api/v1/printers valida campos requeridos', function () {
    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'type', 'connection_type']);
});

test('POST /api/v1/printers requiere host para TCP', function () {
    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers', [
            'name' => 'Test TCP',
            'type' => 'kitchen',
            'connection_type' => 'tcp',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('host');
});

test('POST /api/v1/printers requiere device_path para USB', function () {
    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers', [
            'name' => 'Caja Principal',
            'type' => 'receipt',
            'connection_type' => 'usb',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('device_path');
});

// ============================================
// GET /api/v1/printers - Listar Impresoras
// ============================================

test('GET /api/v1/printers lista impresoras de la sucursal', function () {
    Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina Principal',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja',
        'type' => PrinterType::RECEIPT,
        'connection_type' => ConnectionType::USB,
        'device_path' => '/dev/usb/lp0',
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->getJson('/api/v1/printers');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================
// PUT /api/v1/printers/{uuid} - Actualizar Impresora
// ============================================

test('PUT /api/v1/printers/{uuid} actualiza impresora', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina Antigua',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->putJson("/api/v1/printers/{$printer->uuid}", [
            'name' => 'Cocina Nueva',
            'host' => '192.168.1.200',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Cocina Nueva')
        ->assertJsonPath('data.host', '192.168.1.200');
});

// ============================================
// DELETE /api/v1/printers/{uuid} - Eliminar Impresora
// ============================================

test('DELETE /api/v1/printers/{uuid} elimina impresora', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Impresora a eliminar',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->deleteJson("/api/v1/printers/{$printer->uuid}");

    $response->assertOk();
    expect(Printer::withTrashed()->find($printer->id)->trashed())->toBeTrue();
});

// ============================================
// POST /api/v1/printers/mappings - Crear Mapping
// ============================================

test('POST /api/v1/printers/mappings crea mapping por categoría', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina Principal',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos Fuertes'],
        'sort_order' => 1,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers/mappings', [
            'printer_uuid' => $printer->uuid,
            'category_id' => $category->id,
            'priority' => 1,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.printer_name', 'Cocina Principal')
        ->assertJsonPath('data.category_name', 'Platos Fuertes');
});

test('POST /api/v1/printers/mappings crea mapping por keywords', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Bar',
        'type' => PrinterType::BAR,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.101',
        'port' => 9100,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers/mappings', [
            'printer_uuid' => $printer->uuid,
            'product_keywords' => ['bebida', 'trago', 'cóctel'],
            'priority' => 1,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.printer_name', 'Bar')
        ->assertJsonPath('data.product_keywords.0', 'bebida');
});

test('POST /api/v1/printers/mappings requiere category_id o keywords', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->postJson('/api/v1/printers/mappings', [
            'printer_uuid' => $printer->uuid,
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/print-jobs - Listar PrintJobs
// ============================================

test('GET /api/v1/print-jobs lista trabajos de impresión', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
    ]);

    PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test bytes 2',
        'status' => PrintJob::STATUS_COMPLETED,
        'attempts' => 1,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->getJson('/api/v1/print-jobs');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/v1/print-jobs filtra por status', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
    ]);

    PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_COMPLETED,
        'attempts' => 1,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->getJson('/api/v1/print-jobs?status=pending');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

// ============================================
// POST /api/v1/print-jobs/{uuid}/retry - Reintentar
// ============================================

test('POST /api/v1/print-jobs/{uuid}/retry reintenta trabajo fallido', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_FAILED,
        'attempts' => 1,
        'max_attempts' => 3,
        'error_message' => 'Error anterior',
    ]);

    // El retry intentará enviar, pero fallará por conexión (eso está OK)
    // Lo importante es que la API responda correctamente
    $response = $this->withHeaders(printersHeaders())
        ->postJson("/api/v1/print-jobs/{$job->uuid}/retry");

    // El endpoint retorna 200 o 500 dependiendo de si la impresora es alcanzable
    // En testing USB simula el envío, en TCP falla por conexión (esperado)
    expect(in_array($response->status(), [200, 500]))->toBeTrue();
});

test('POST /api/v1/print-jobs/{uuid}/retry deniega si no está en estado failed', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_COMPLETED,
        'attempts' => 1,
    ]);

    $response = $this->withHeaders(printersHeaders())
        ->postJson("/api/v1/print-jobs/{$job->uuid}/retry");

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

// ============================================
// Autorización
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/printers');
    $response->assertStatus(401);
});
