<?php

use Modules\Printers\Domain\Services\EscPosService;
use Modules\Printers\Domain\Services\KitchenCommandFormatter;
use Modules\Printers\Domain\Services\ReceiptFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================
// EscPosService - Comandos básicos
// ============================================

test('EscPosService genera comando de inicialización', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->initialize();
    
    expect($output)->toContain("\x1B@");
});

test('EscPosService genera texto simple con salto de línea', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->text("Hola Mundo");
    
    expect($output)->toContain("Hola Mundo");
    expect($output)->toContain("\x0A");
});

test('EscPosService genera texto en negrita', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->bold("Importante");
    
    expect($output)->toContain("\x1BE\x01"); // Bold ON
    expect($output)->toContain("Importante");
    expect($output)->toContain("\x1BE\x00"); // Bold OFF
});

test('EscPosService genera texto con tamaño doble', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->doubleSize("Grande");
    
    expect($output)->toContain("\x1D!\x11"); // Double size
    expect($output)->toContain("Grande");
    expect($output)->toContain("\x1D!\x00"); // Normal size
});

test('EscPosService genera texto centrado', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->alignCenter("Centrado");
    
    expect($output)->toContain("\x1Ba\x01"); // Align center
    expect($output)->toContain("Centrado");
});

test('EscPosService genera comando de corte de papel', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->cut();
    
    expect($output)->toContain("\x1DV\x00"); // Full cut
});

test('EscPosService genera comando de apertura de cajón', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->openDrawer();
    
    expect($output)->toContain("\x1Bp\x00"); // Open drawer
});

test('EscPosService genera código de barras CODE128', function () {
    $escPos = new EscPosService();
    
    $output = $escPos->barcode("123456");
    
    expect($output)->toContain("\x1Dk"); // Barcode command (GS k)
    expect($output)->toContain("123456");
});

// ============================================
// KitchenCommandFormatter - Comandas de cocina
// ============================================

test('KitchenCommandFormatter genera comanda completa', function () {
    $formatter = new KitchenCommandFormatter();
    
    $output = $formatter->format([
        'order_number' => '123',
        'table' => 'Mesa 5',
        'waiter' => 'Juan',
        'items' => [
            ['name' => 'Carne Mongoliana', 'qty' => 2, 'notes' => 'Sin cebolla'],
            ['name' => 'Arroz Chaufa', 'qty' => 1, 'notes' => ''],
        ],
        'timestamp' => '14:30',
    ]);
    
    // Verificar que contiene todos los elementos
    expect($output)->toContain("PEDIDO #123");
    expect($output)->toContain("Mesa 5");
    expect($output)->toContain("Juan");
    expect($output)->toContain("2x Carne Mongoliana");
    expect($output)->toContain("Sin cebolla");
    expect($output)->toContain("1x Arroz Chaufa");
});

test('KitchenCommandFormatter genera comanda compacta para bar', function () {
    $formatter = new KitchenCommandFormatter();
    
    $output = $formatter->formatCompact([
        'order_number' => '456',
        'table' => 'Bar 3',
        'items' => [
            ['name' => 'Pisco Sour', 'qty' => 2, 'notes' => ''],
            ['name' => 'Cerveza', 'qty' => 3, 'notes' => 'Helada'],
        ],
    ]);
    
    expect($output)->toContain("#456");
    expect($output)->toContain("Bar 3");
    expect($output)->toContain("2x Pisco Sour");
    expect($output)->toContain("3x Cerveza");
});

// ============================================
// ReceiptFormatter - Tickets de cliente
// ============================================

test('ReceiptFormatter genera ticket completo', function () {
    $formatter = new ReceiptFormatter();
    
    $output = $formatter->format([
        'company_name' => 'Restaurant Test',
        'branch_name' => 'Sucursal Centro',
        'order_number' => '789',
        'date' => '14/08/2026 14:30',
        'waiter' => 'María',
        'items' => [
            ['name' => 'Carne Mongoliana', 'qty' => 2, 'price' => 12000, 'subtotal' => 24000],
            ['name' => 'Arroz Chaufa', 'qty' => 1, 'price' => 8000, 'subtotal' => 8000],
        ],
        'subtotal' => 32000,
        'tax' => 6080,
        'discount' => 0,
        'total' => 38080,
        'payment_method' => 'Efectivo',
        'barcode' => 'ORD-789',
    ]);
    
    // Verificar elementos del ticket
    expect($output)->toContain("Restaurant Test");
    expect($output)->toContain("Sucursal Centro");
    expect($output)->toContain("#789");
    expect($output)->toContain("María");
    expect($output)->toContain("2x Carne Mongoliana");
    expect($output)->toContain("Subtotal");
    expect($output)->toContain("IVA");
    expect($output)->toContain("TOTAL");
    expect($output)->toContain("Efectivo");
    expect($output)->toContain("ORD-789");
    expect($output)->toContain("Gracias por su visita");
});

test('ReceiptFormatter incluye descuento si existe', function () {
    $formatter = new ReceiptFormatter();
    
    $output = $formatter->format([
        'company_name' => 'Restaurant Test',
        'branch_name' => 'Sucursal',
        'order_number' => '999',
        'items' => [
            ['name' => 'Producto', 'qty' => 1, 'price' => 10000, 'subtotal' => 10000],
        ],
        'subtotal' => 10000,
        'tax' => 1900,
        'discount' => 1000,
        'total' => 10900,
    ]);
    
    expect($output)->toContain("Descuento");
    expect($output)->toContain("-$1.000");
});
