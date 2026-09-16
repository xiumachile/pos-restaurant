<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Services\PrintService;
use Modules\Printers\Domain\Services\PrintJobManagementService;
use Modules\Printers\Domain\Listeners\PrintReceiptOnOrderPaid;
use Modules\Printers\Domain\Listeners\PrintKitchenOnOrderConfirm;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Domain\ValueObjects\ConnectionType;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PRINT-' . uniqid(),
        'legal_name' => 'Print Test Company',
        'trade_name' => 'Print Test',
    ]);

    enableAllCapabilities($this->company);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Print Test Branch',
        'code' => 'PTB',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Print User',
        'email' => 'print-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'role' => 'cashier',
    ]);

    // Crear impresora de recibos
    $this->receiptPrinter = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Receipt Printer',
        'type' => PrinterType::RECEIPT,
        'connection_type' => ConnectionType::USB,
        'device_path' => '/dev/usb/lp0',
        'is_active' => true,
    ]);

    // Crear impresora de cocina
    $this->kitchenPrinter = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Kitchen Printer',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::USB,
        'device_path' => '/dev/usb/lp1',
        'is_active' => true,
    ]);

    $this->printService = app(PrintService::class);
    $this->printJobService = app(PrintJobManagementService::class);
});

test('OrderPaid crea PrintJob de recibo', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PRINT-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'quantity' => 2,
        'unit_price_snapshot' => 5000,
        'subtotal' => 10000,
    ]);

    // Llamar listener directamente (no depender de event dispatcher)
    $listener = app(PrintReceiptOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    // Verificar que se creó PrintJob
    $printJob = PrintJob::where('order_id', $order->id)
        ->where('job_type', PrintJob::TYPE_RECEIPT)
        ->first();

    expect($printJob)->not->toBeNull()
        ->and($printJob->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($printJob->printer_id)->toBe($this->receiptPrinter->id);
});

test('OrderConfirmed crea PrintJob de cocina', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PRINT-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'quantity' => 2,
        'unit_price_snapshot' => 5000,
        'subtotal' => 10000,
    ]);

    // Llamar listener directamente
    $listener = app(PrintKitchenOnOrderConfirm::class);
    $listener->handle(new OrderConfirmed($order));

    // Verificar que se creó PrintJob de cocina
    $printJob = PrintJob::where('order_id', $order->id)
        ->where('job_type', PrintJob::TYPE_KITCHEN_COMMAND)
        ->first();

    expect($printJob)->not->toBeNull()
        ->and($printJob->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($printJob->printer_id)->toBe($this->kitchenPrinter->id);
});

test('impresión NO compromete integridad financiera (PrintJob es best-effort)', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-PRINT-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'paid_at' => now(),
    ]);

    // Capturar estado financiero antes de impresión
    $initialTotal = $order->total;
    $initialStatus = $order->status;

    // Llamar listener directamente
    $listener = app(PrintReceiptOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    // Recargar orden
    $order->refresh();

    // Verificar que la impresión NO afectó el estado financiero
    expect((float) $order->total)->toBe((float) $initialTotal)
        ->and($order->status)->toBe($initialStatus)
        ->and($order->paid_at)->not->toBeNull();
});

test('PrintJob puede reintentarse si falla', function () {
    // FIX: Crear Order válido primero
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-RETRY-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    $printJob = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->receiptPrinter->id,
        'job_type' => PrintJob::TYPE_RECEIPT,
        'order_id' => $order->id,  // FIX: usar order real
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_FAILED,
        'attempts' => 1,
        'max_attempts' => 3,
        'error_message' => 'Error de conexión',
    ]);

    expect($printJob->canRetry())->toBeTrue();

    // Simular reintento
    $printJob->status = PrintJob::STATUS_PENDING;
    $printJob->error_message = null;
    $printJob->save();

    expect($printJob->status)->toBe(PrintJob::STATUS_PENDING)
        ->and($printJob->error_message)->toBeNull();
});

test('PrintJob NO puede reintentarse después de max_attempts', function () {
    // FIX: Crear Order válido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-MAX-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    $printJob = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->receiptPrinter->id,
        'job_type' => PrintJob::TYPE_RECEIPT,
        'order_id' => $order->id,  // FIX: usar order real
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_FAILED,
        'attempts' => 3,
        'max_attempts' => 3,
        'error_message' => 'Error persistente',
    ]);

    expect($printJob->canRetry())->toBeFalse();
});

test('PrintJob puede ser reclamado por cliente local', function () {
    // FIX: Crear Order válido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-CLAIM-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    $printJob = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->receiptPrinter->id,
        'job_type' => PrintJob::TYPE_RECEIPT,
        'order_id' => $order->id,  // FIX: usar order real
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 3,
    ]);

    expect($printJob->isAvailableForClaim())->toBeTrue();

    // Reclamar job
    $claimed = $printJob->claim('client-123');

    expect($claimed)->toBeTrue()
        ->and($printJob->status)->toBe(PrintJob::STATUS_PRINTING)
        ->and($printJob->claimed_by)->toBe('client-123')
        ->and($printJob->attempts)->toBe(1);
});

test('PrintJob con claim expirado puede ser reclamado de nuevo', function () {
    // FIX: Crear Order válido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-EXPIRED-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    $printJob = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->receiptPrinter->id,
        'job_type' => PrintJob::TYPE_RECEIPT,
        'order_id' => $order->id,  // FIX: usar order real
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 1,
        'max_attempts' => 3,
        'claimed_by' => 'client-old',
        'claimed_at' => now()->subMinutes(10), // Expirado (> 5 min)
    ]);

    expect($printJob->isAvailableForClaim())->toBeTrue();

    // Nuevo cliente puede reclamar
    $claimed = $printJob->claim('client-new');

    expect($claimed)->toBeTrue()
        ->and($printJob->claimed_by)->toBe('client-new');
});

test('impresora desconectada marca job como failed', function () {
    // FIX: Crear Order válido
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-DISCONN-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    $printJob = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->receiptPrinter->id,
        'job_type' => PrintJob::TYPE_RECEIPT,
        'order_id' => $order->id,  // FIX: usar order real
        'escpos_bytes' => 'test bytes',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
        'max_attempts' => 3,
    ]);

    // Simular fallo de impresión
    $printJob->markAsFailed('Impresora desconectada');

    expect($printJob->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($printJob->error_message)->toBe('Impresora desconectada')
        ->and($printJob->canRetry())->toBeTrue(); // Aún puede reintentar
});

test('criterio de cierre: impresión nunca compromete integridad financiera', function () {
    // Crear orden pagada
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-INTEGRITY-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'paid_at' => now(),
    ]);

    OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $order->id,
        'name_snapshot' => 'Hamburguesa',
        'quantity' => 2,
        'unit_price_snapshot' => 5000,
        'subtotal' => 10000,
    ]);

    // Capturar estado financiero
    $initialTotal = (float) $order->total;
    $initialTax = (float) $order->tax_amount;
    $initialStatus = $order->status;

    // Llamar listener directamente (crea PrintJob)
    $listener = app(PrintReceiptOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    // Crear PrintJob manualmente y marcarlo como failed
    $printJob = PrintJob::where('order_id', $order->id)->first();
    if ($printJob) {
        $printJob->markAsFailed('Error crítico');
    }

    // Recargar orden
    $order->refresh();

    // Verificar que la impresión (exitosa o fallida) NO afectó integridad financiera
    expect((float) $order->total)->toBe($initialTotal)
        ->and((float) $order->tax_amount)->toBe($initialTax)
        ->and($order->status)->toBe($initialStatus)
        ->and($order->paid_at)->not->toBeNull();
});
