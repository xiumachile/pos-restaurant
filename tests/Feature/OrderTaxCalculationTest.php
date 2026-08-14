<?php

use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Domain\ValueObjects\TaxType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Order Tax Test SpA',
        'trade_name' => 'Order Tax Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'OTAX',
        'name' => 'Order Tax Branch',
    ]);

    $this->waiter = User::forceCreate([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    // Crear IVA 19% como default
    $this->iva19 = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'IVA 19%',
        'code' => 'IVA',
        'type' => TaxType::PERCENT,
        'rate' => 19.00,
        'is_default' => true,
        'is_active' => true,
    ]);

    // Crear impuesto exento
    $this->exento = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Exento',
        'code' => 'EXENTO',
        'type' => TaxType::EXEMPT,
        'rate' => 0.00,
        'is_default' => false,
        'is_active' => true,
    ]);

    // Crear categorías
    $this->categoryAfecta = Category::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Platos Afectos'],
        'sort_order' => 1,
    ]);

    $this->categoryExenta = Category::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Productos Exentos'],
        'sort_order' => 2,
        'tax_id' => $this->exento->id,
    ]);

    // Crear productos
    $this->productAfecto = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryAfecta->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    $this->productExento = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryExenta->id,
        'name_translations' => ['es' => 'Pan Artesanal'],
        'base_price' => 3000,
        'is_active' => true,
    ]);
});

test('OrderItem calcula tax_amount automáticamente al guardar', function () {
    $order = Order::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TAX-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productAfecto->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Carne Mongoliana',
        'unit_price_snapshot' => 12000,
        'quantity' => 2,
        'notes' => null,
        'subtotal' => 0,
    ]);

    $item->refresh();

    // subtotal = 12000 * 2 = 24000
    expect((float) $item->subtotal)->toBe(24000.00);
    
    // tax_amount = 24000 * 0.19 = 4560
    expect((float) $item->tax_amount)->toBe(4560.00);
    
    // tax_rate_snapshot = 19.00
    expect((float) $item->tax_rate_snapshot)->toBe(19.00);
    
    // tax_name_snapshot = 'IVA 19%'
    expect($item->tax_name_snapshot)->toBe('IVA 19%');
});

test('OrderItem exento tiene tax_amount 0', function () {
    $order = Order::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TAX-002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productExento->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Pan Artesanal',
        'unit_price_snapshot' => 3000,
        'quantity' => 3,
        'notes' => null,
        'subtotal' => 0,
    ]);

    $item->refresh();

    // subtotal = 3000 * 3 = 9000
    expect((float) $item->subtotal)->toBe(9000.00);
    
    // tax_amount = 0 (exento)
    expect((float) $item->tax_amount)->toBe(0.00);
});

test('Order::recalculateTotals suma tax_amount de items', function () {
    $order = Order::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TAX-003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    // Item 1: Producto afecto (IVA 19%)
    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productAfecto->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Carne Mongoliana',
        'unit_price_snapshot' => 12000,
        'quantity' => 2,
        'notes' => null,
        'subtotal' => 0,
    ]);

    // Item 2: Producto exento
    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productExento->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Pan Artesanal',
        'unit_price_snapshot' => 3000,
        'quantity' => 3,
        'notes' => null,
        'subtotal' => 0,
    ]);

    $order->recalculateTotals();
    $order->save();
    $order->refresh();

    // subtotal = 24000 + 9000 = 33000
    expect((float) $order->subtotal)->toBe(33000.00);
    
    // tax_amount = 4560 (solo del item afecto) + 0 (exento) = 4560
    expect((float) $order->tax_amount)->toBe(4560.00);
    
    // total = 33000 + 4560 - 0 = 37560
    expect((float) $order->total)->toBe(37560.00);
});

test('Order con todos los items exentos tiene tax_amount 0', function () {
    $order = Order::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TAX-004',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 0,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productExento->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Pan Artesanal',
        'unit_price_snapshot' => 3000,
        'quantity' => 5,
        'notes' => null,
        'subtotal' => 0,
    ]);

    $order->recalculateTotals();
    $order->save();
    $order->refresh();

    expect((float) $order->subtotal)->toBe(15000.00);
    expect((float) $order->tax_amount)->toBe(0.00);
    expect((float) $order->total)->toBe(15000.00);
});

test('Order con descuento calcula total correctamente', function () {
    $order = Order::forceCreate([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TAX-005',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 5000,
        'total' => 0,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->productAfecto->id,
        'menu_item_id' => null,
        'name_snapshot' => 'Carne Mongoliana',
        'unit_price_snapshot' => 12000,
        'quantity' => 1,
        'notes' => null,
        'subtotal' => 0,
    ]);

    $order->recalculateTotals();
    $order->save();
    $order->refresh();

    // subtotal = 12000
    expect((float) $order->subtotal)->toBe(12000.00);
    
    // tax_amount = 12000 * 0.19 = 2280
    expect((float) $order->tax_amount)->toBe(2280.00);
    
    // total = 12000 + 2280 - 5000 = 9280
    expect((float) $order->total)->toBe(9280.00);
});
