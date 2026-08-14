<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrinterStationMapping;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Catalog\Domain\Entities\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'PRN-' . uniqid(),
        'legal_name' => 'Printers Test Company',
        'trade_name' => 'Printers Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PRN',
        'name' => 'Printers Branch',
    ]);
});

// ============================================
// Value Objects
// ============================================

test('PrinterType identifica impresoras de cocina', function () {
    expect(PrinterType::KITCHEN->isKitchenPrinter())->toBeTrue();
    expect(PrinterType::BAR->isKitchenPrinter())->toBeTrue();
    expect(PrinterType::RECEIPT->isKitchenPrinter())->toBeFalse();
});

test('ConnectionType valida requisitos de configuración', function () {
    expect(ConnectionType::TCP->requiresHostAndPort())->toBeTrue();
    expect(ConnectionType::TCP->requiresDevicePath())->toBeFalse();

    expect(ConnectionType::USB->requiresHostAndPort())->toBeFalse();
    expect(ConnectionType::USB->requiresDevicePath())->toBeTrue();

    expect(ConnectionType::BLUETOOTH->requiresHostAndPort())->toBeFalse();
    expect(ConnectionType::BLUETOOTH->requiresDevicePath())->toBeTrue();
});

// ============================================
// Printer Entity
// ============================================

test('se puede crear una impresora TCP de cocina', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina WOK',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
        'paper_width' => 80,
        'auto_cut' => true,
        'is_active' => true,
    ]);

    expect($printer->id)->not->toBeNull();
    expect($printer->uuid)->not->toBeNull();
    expect($printer->type)->toBe(PrinterType::KITCHEN);
    expect($printer->connection_type)->toBe(ConnectionType::TCP);
    expect($printer->isKitchenPrinter())->toBeTrue();
});

test('se puede crear una impresora USB de recibos', function () {
    $printer = Printer::create([
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

    expect($printer->id)->not->toBeNull();
    expect($printer->type)->toBe(PrinterType::RECEIPT);
    expect($printer->isKitchenPrinter())->toBeFalse();
});

test('validateConnection verifica configuración TCP', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test TCP',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    expect($printer->validateConnection())->toBeTrue();

    // Sin host
    $printer->host = null;
    expect($printer->validateConnection())->toBeFalse();
});

test('validateConnection verifica configuración USB', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test USB',
        'type' => PrinterType::RECEIPT,
        'connection_type' => ConnectionType::USB,
        'device_path' => '/dev/usb/lp0',
    ]);

    expect($printer->validateConnection())->toBeTrue();

    // Sin device_path
    $printer->device_path = null;
    expect($printer->validateConnection())->toBeFalse();
});

test('recordPrint incrementa contador y actualiza timestamp', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test Counter',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
        'print_count' => 5,
    ]);

    expect($printer->print_count)->toBe(5);
    expect($printer->last_printed_at)->toBeNull();

    $printer->recordPrint();

    $printer->refresh();
    expect($printer->print_count)->toBe(6);
    expect($printer->last_printed_at)->not->toBeNull();
});

// ============================================
// PrinterStationMapping Entity
// ============================================

test('se puede crear un mapeo de categoría a impresora', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina WOK',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos al Wok'],
        'sort_order' => 1,
    ]);

    $mapping = PrinterStationMapping::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'category_id' => $category->id,
        'priority' => 1,
        'is_active' => true,
    ]);

    expect($mapping->id)->not->toBeNull();
    expect($mapping->printer->name)->toBe('Cocina WOK');
});

test('matchesProduct verifica por categoría', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina WOK',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos al Wok'],
        'sort_order' => 1,
    ]);

    $mapping = PrinterStationMapping::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'category_id' => $category->id,
    ]);

    expect($mapping->matchesProduct('Carne Mongoliana', $category->id))->toBeTrue();
    expect($mapping->matchesProduct('Coca Cola', 999))->toBeFalse();
});

test('matchesProduct verifica por keywords', function () {
    $printer = Printer::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cocina General',
        'type' => PrinterType::KITCHEN,
        'connection_type' => ConnectionType::TCP,
        'host' => '192.168.1.100',
        'port' => 9100,
    ]);

    $mapping = PrinterStationMapping::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'printer_id' => $printer->id,
        'category_id' => null,
        'product_keywords' => ['wok', 'salteado', 'frito'],
    ]);

    expect($mapping->matchesProduct('Carne Mongoliana al Wok', null))->toBeTrue();
    expect($mapping->matchesProduct('Arroz Salteado', null))->toBeTrue();
    expect($mapping->matchesProduct('Coca Cola', null))->toBeFalse();
});
