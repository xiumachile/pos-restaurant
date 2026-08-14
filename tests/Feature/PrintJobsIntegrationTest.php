<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrinterStationMapping;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PRN-' . uniqid(),
        'legal_name' => 'Print Jobs Test',
        'trade_name' => 'Print Jobs Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PRJ',
        'name' => 'Print Jobs Branch',
    ]);

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->kitchenPrinter = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina Principal',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
        'paper_width' => 80,
        'auto_cut' => true,
        'is_active' => true,
    ]);

    $this->receiptPrinter = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Caja Principal',
        'type' => PrinterType::RECEIPT,
        'connection_type' => ConnectionType::USB,
        'device_path' => '/dev/usb/lp0',
        'paper_width' => 80,
        'auto_cut' => true,
        'open_drawer_on_print' => true,
        'is_active' => true,
    ]);
});

test('se puede crear un PrintJob con bytes ESC/POS', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->kitchenPrinter->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'order_id' => $order->id,
        'escpos_bytes' => "\x1B@Comanda de prueba\x0A",
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
    ]);

    expect($job->id)->not->toBeNull();
    expect($job->status)->toBe(PrintJob::STATUS_PENDING);
    expect($job->attempts)->toBe(0);
});

test('markAsPrinting incrementa contador de intentos', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->kitchenPrinter->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_PENDING,
        'attempts' => 0,
    ]);

    expect($job->attempts)->toBe(0);
    expect($job->status)->toBe(PrintJob::STATUS_PENDING);

    $job->markAsPrinting();

    $job->refresh();
    expect($job->attempts)->toBe(1);
    expect($job->status)->toBe(PrintJob::STATUS_PRINTING);
});

test('markAsCompleted actualiza estado y contador de impresora', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->kitchenPrinter->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'status' => PrintJob::STATUS_PRINTING,
        'attempts' => 1,
    ]);

    $initialCount = $this->kitchenPrinter->print_count;

    $job->markAsCompleted();

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_COMPLETED);
    expect($job->printed_at)->not->toBeNull();

    $this->kitchenPrinter->refresh();
    expect($this->kitchenPrinter->print_count)->toBe($initialCount + 1);
});

test('canRetry verifica limite de intentos', function () {
    $job = PrintJob::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->kitchenPrinter->id,
        'job_type' => PrintJob::TYPE_KITCHEN_COMMAND,
        'escpos_bytes' => 'test',
        'max_attempts' => 3,
        'attempts' => 1,
    ]);

    expect($job->canRetry())->toBeTrue();

    $job->attempts = 3;
    $job->save();
    expect($job->fresh()->canRetry())->toBeFalse();
});

test('OrderConfirmed crea PrintJob para impresora de cocina', function () {
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos Fuertes'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    PrinterStationMapping::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $this->kitchenPrinter->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-PRINT-1',
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 2,
        'unit_price_snapshot' => 12000,
        'subtotal' => 24000,
        'notes' => 'Sin cebolla',
    ]);

    expect(PrintJob::count())->toBe(0);

    // Llamar directamente al listener (más confiable en testing)
    $listener = app(\Modules\Printers\Domain\Listeners\PrintKitchenOnOrderConfirm::class);
    $listener->handle(new OrderConfirmed($order));

    expect(PrintJob::count())->toBe(1);

    $job = PrintJob::first();
    expect($job->printer_id)->toBe($this->kitchenPrinter->id);
    expect($job->job_type)->toBe(PrintJob::TYPE_KITCHEN_COMMAND);
    expect($job->status)->toBe(PrintJob::STATUS_PENDING);
    expect($job->order_id)->toBe($order->id);
});

test('OrderPaid crea PrintJob de ticket de cliente', function () {
    // Crear categoría y producto válidos
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-PAID-1',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 24000,
        'tax_amount' => 4560,
        'total' => 28560,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 2,
        'unit_price_snapshot' => 12000,
        'subtotal' => 24000,
    ]);

    expect(PrintJob::count())->toBe(0);

    // Llamar directamente al listener
    $listener = app(\Modules\Printers\Domain\Listeners\PrintReceiptOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    expect(PrintJob::count())->toBe(1);

    $job = PrintJob::first();
    expect($job->printer_id)->toBe($this->receiptPrinter->id);
    expect($job->job_type)->toBe(PrintJob::TYPE_RECEIPT);
    expect($job->status)->toBe(PrintJob::STATUS_PENDING);
});
