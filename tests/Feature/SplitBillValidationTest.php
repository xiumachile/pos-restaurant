<?php

use App\Models\User;
use Modules\Billing\Application\Services\BillingService;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\Branch;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);
    
    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'table_id' => 'table-1',
        'waiter_name' => 'Juan',
        'subtotal' => 30000,
        'tax_amount' => 5700,
        'total' => 35700,
        'status' => 'served',
    ]);
    
    // Crear 3 items en el Order
    $this->itemA = OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => 'prod-a',
        'name_snapshot' => 'Producto A',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
        'subtotal' => 10000,
    ]);
    
    $this->itemB = OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => 'prod-b',
        'name_snapshot' => 'Producto B',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
        'subtotal' => 10000,
    ]);
    
    $this->itemC = OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => 'prod-c',
        'name_snapshot' => 'Producto C',
        'unit_price_snapshot' => 10000,
        'quantity' => 1,
        'subtotal' => 10000,
    ]);
    
    $this->service = new BillingService();
});

test('splitByItems rechaza item duplicado en múltiples grupos', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Items duplicados en grupos');
    
    $this->service->splitByItems(
        $this->order->uuid,
        [
            [
                'item_ids' => [$this->itemA->id, $this->itemB->id],
                'guest_count' => 2,
            ],
            [
                'item_ids' => [$this->itemA->id, $this->itemC->id], // itemA duplicado
                'guest_count' => 1,
            ],
        ]
    );
});

test('splitByItems rechaza item del Order no incluido en ningún grupo', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Items del Order no incluidos en ningún grupo');
    
    $this->service->splitByItems(
        $this->order->uuid,
        [
            [
                'item_ids' => [$this->itemA->id],
                'guest_count' => 1,
            ],
            [
                'item_ids' => [$this->itemC->id], // itemB omitido
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
        $this->order->uuid,
        [
            [
                'item_ids' => [$this->itemA->id, $this->itemB->id, $this->itemC->id],
                'guest_count' => 2,
            ],
            [
                'item_ids' => [$fakeItemId], // Item que no existe
                'guest_count' => 1,
            ],
        ]
    );
});

test('splitByItems acepta grupos válidos con todos los items exactamente una vez', function () {
    $bills = $this->service->splitByItems(
        $this->order->uuid,
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
    expect($bills[0]->items)->toHaveCount(2);
    expect($bills[1]->items)->toHaveCount(1);
});

test('splitByItems valida que suma de items en grupos = items del Order', function () {
    // Crear Order con 5 items
    $items = [];
    for ($i = 0; $i < 5; $i++) {
        $items[] = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => "prod-{$i}",
            'name_snapshot' => "Producto {$i}",
            'unit_price_snapshot' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
        ]);
    }
    
    // Intentar split con solo 4 items (falta 1)
    $this->expectException(InvalidArgumentException::class);
    
    $this->service->splitByItems(
        $this->order->uuid,
        [
            ['item_ids' => [$items[0]->id, $items[1]->id]],
            ['item_ids' => [$items[2]->id, $items[3]->id]], // Falta items[4]
        ]
    );
});
