<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    // Habilitar la capacidad requerida por el middleware de rutas de impresoras
    enableCapabilities($this->company, ['can_print_receipts']);
    
    $this->user = User::create([
        'name' => 'Admin Cocina',
        'email' => 'admin-cocina@test.test',
        'password' => bcrypt('pw'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin'
    ]);

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-PRINT-001',
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->user->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $this->printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Impresora Cocina Principal',
        'type' => PrinterType::KITCHEN->value,
        'connection_type' => ConnectionType::TCP->value,
        'host' => '192.168.1.100',
        'port' => 9100,
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->user);
});

test('C-03: Flujo completo de trabajo de impresión: pendiente -> reclamado -> completado', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->printer->id,
        'order_id' => $this->order->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => "\x1B\x40\x1B\x61\x01PEDIDO #001\x0A",
        'status' => PrintJob::STATUS_PENDING,
        'max_attempts' => 3,
    ]);

    $clientId = 'tauri-client-123';
    $claimed = $job->claim($clientId);
    expect($claimed)->toBeTrue()
        ->and($job->status)->toBe(PrintJob::STATUS_PRINTING)
        ->and($job->claimed_by)->toBe($clientId);

    $job->markAsCompleted();
    
    expect($job->status)->toBe(PrintJob::STATUS_COMPLETED)
        ->and($job->printed_at)->not->toBeNull()
        ->and($job->error_message)->toBeNull();
});

test('C-03: Flujo de fallo y reintento de impresión', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->printer->id,
        'order_id' => $this->order->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => "\x1B\x40\x1B\x61\x01PEDIDO #002\x0A",
        'status' => PrintJob::STATUS_PENDING,
        'max_attempts' => 3,
        'attempts' => 1,
    ]);

    $job->markAsFailed('Error de conexión: Puerto 9100 no responde');
    
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->error_message)->toBe('Error de conexión: Puerto 9100 no responde')
        ->and($job->canRetry())->toBeTrue();

    $job->status = PrintJob::STATUS_PENDING;
    $job->error_message = null;
    $job->save();

    $job->claim('tauri-client-123');
    $job->markAsCompleted();

    expect($job->status)->toBe(PrintJob::STATUS_COMPLETED)
        ->and($job->attempts)->toBe(2);
});

test('C-03: API endpoint para reclamar y completar trabajos', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->printer->id,
        'order_id' => $this->order->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => "\x1B\x40\x1B\x61\x01PEDIDO #003\x0A",
        'status' => PrintJob::STATUS_PENDING,
    ]);

    // Reclamar
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/print-jobs/{$job->uuid}/claim", ['client_id' => 'tauri-1']);
    
    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    // Completar
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json',
    ])->postJson("/api/v1/print-jobs/{$job->uuid}/complete");
    
    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_COMPLETED);
});
