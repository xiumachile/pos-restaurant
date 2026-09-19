<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Entities\Account;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Services\BillingService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'VALIDATION-' . uniqid(),
        'legal_name' => 'Validation Test Company',
        'trade_name' => 'Validation Test',
    ]);

    enableAllCapabilities($this->company);
    Account::seedDefaultsFor($this->company->id);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'VAL',
        'name' => 'Validation Branch',
    ]);

    $this->user = User::create([
        'name' => 'Validation User',
        'email' => 'validation-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'is_active' => true,
    ]);

    $this->product1 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto A'],
        'base_price' => 10000,
        'is_active' => true,
    ]);

    $this->product2 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto B'],
        'base_price' => 10000,
        'is_active' => true,
    ]);

    $this->product3 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Producto C'],
        'base_price' => 10000,
        'is_active' => true,
    ]);

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-VAL-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal_gross' => 30000,
        'net_amount' => 25210,
        'tax_amount' => 4790,
        'amount_due' => 30000,
        'subtotal' => 30000,
        'total' => 30000,
    ]);

    $this->itemA = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'product_id' => $this->product1->id,
        'name_snapshot' => 'Producto A',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1597,
    ]);

    $this->itemB = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'product_id' => $this->product2->id,
        'name_snapshot' => 'Producto B',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1597,
    ]);

    $this->itemC = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'product_id' => $this->product3->id,
        'name_snapshot' => 'Producto C',
        'quantity' => 1,
        'unit_price_snapshot' => 10000,
        'subtotal' => 10000,
        'tax_amount' => 1596,
    ]);

    $this->service = new BillingService();
});

test('splitByItems rechaza item duplicado en múltiples grupos', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Items duplicados en grupos');

    $this->service->splitByItems(
        $this->order,
        [
            [
                'item_ids' => [$this->itemA->id, $this->itemB->id],
                'guest_count' => 2,
            ],
            [
                'item_ids' => [$this->itemA->id, $this->itemC->id],
                'guest_count' => 1,
            ],
        ]
    );
});

test('splitByItems rechaza item del Order no incluido en ningún grupo', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Items del Order no incluidos en ningún grupo');

    $this->service->splitByItems(
        $this->order,
        [
            [
                'item_ids' => [$this->itemA->id],
                'guest_count' => 1,
            ],
            [
                'item_ids' => [$this->itemC->id],
                'guest_count' => 1,
            ],
        ]
    );
});

test('splitByItems rechaza item que no pertenece al Order', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('no pertenece al Order');

    $fakeItemId = 99999;

    $this->service->splitByItems(
        $this->order,
        [
            [
                'item_ids' => [$this->itemA->id, $this->itemB->id, $this->itemC->id],
                'guest_count' => 2,
            ],
            [
                'item_ids' => [$fakeItemId],
                'guest_count' => 1,
            ],
        ]
    );
});

test('splitByItems acepta grupos válidos con todos los items exactamente una vez', function () {
    $bills = $this->service->splitByItems(
        $this->order,
        [
            [
                'item_ids' => [$this->itemA->id, $this->itemB->id],
                'guest_count' => 2,
            ],
            [
                'item_ids' => [$this->itemC->id],
                'guest_count' => 1,
            ],
        ]
    );

    expect($bills)->toHaveCount(2);
    expect($bills[0]->item_ids)->toHaveCount(2);
    expect($bills[1]->item_ids)->toHaveCount(1);
});

test('splitByItems valida cobertura completa de items', function () {
    $product4 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->product1->category_id,
        'name_translations' => ['es' => 'Producto D'],
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $product5 = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->product1->category_id,
        'name_translations' => ['es' => 'Producto E'],
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $item4 = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'product_id' => $product4->id,
        'name_snapshot' => 'Producto D',
        'quantity' => 1,
        'unit_price_snapshot' => 5000,
        'subtotal' => 5000,
        'tax_amount' => 798,
    ]);

    $item5 = OrderItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_id' => $this->order->id,
        'product_id' => $product5->id,
        'name_snapshot' => 'Producto E',
        'quantity' => 1,
        'unit_price_snapshot' => 5000,
        'subtotal' => 5000,
        'tax_amount' => 798,
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Items del Order no incluidos en ningún grupo');

    $this->service->splitByItems(
        $this->order,
        [
            ['item_ids' => [$this->itemA->id, $this->itemB->id]],
            ['item_ids' => [$this->itemC->id, $item4->id]],
        ]
    );
});
