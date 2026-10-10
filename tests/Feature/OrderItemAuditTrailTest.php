<?php

use Illuminate\Support\Facades\Event;
use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Audit\Domain\Listeners\AuditOrderEvents;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\Events\OrderItemRemoved;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TEST',
        'legal_name' => 'Test Co',
        'trade_name' => 'Test'
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'T1',
        'name' => 'Test Branch'
    ]);

    $this->user = User::create([
        'name' => 'Waiter',
        'email' => 'waiter@test.test',
        'password' => bcrypt('password'),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter'
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Test Product'],
        'base_price' => 1500,
        'is_active' => true
    ]);

    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->user->id,
        'order_number' => 'ORD-TEST-001',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 4500,
        'tax_amount' => 0,
        'total' => 4500
    ]);

    $this->orderItem = OrderItem::create([
        'order_id' => $this->order->id,
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'name_snapshot' => 'Test Product',
        'unit_price_snapshot' => 1500,
        'quantity' => 3,
        'subtotal' => 4500,
        'tax_rate_snapshot' => 0.0
    ]);
});

test('M-04: Al eliminar un item se dispara OrderItemRemoved event', function () {
    Event::fake([OrderItemRemoved::class]);

    $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($this->user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/orders/{$this->order->uuid}/items/{$this->orderItem->uuid}");

    $response->assertStatus(200);

    Event::assertDispatched(OrderItemRemoved::class, function ($event) {
        return $event->order->id === $this->order->id
            && $event->item->id === $this->orderItem->id
            && $event->reason === 'manual_removal'
            && $event->userId === $this->user->id
            && !empty($event->event_uuid);
    });
});

test('M-04: El listener registra la eliminación en audit_logs con todos los datos requeridos', function () {
    // Autenticar usuario para que AuditService pueda obtener company_id y branch_id
    $this->actingAs($this->user);

    $listener = new AuditOrderEvents(app(\Modules\Audit\Domain\Services\AuditService::class));

    $event = new OrderItemRemoved(
        $this->order,
        $this->orderItem,
        'manual_removal',
        $this->user->id
    );

    $listener->handleOrderItemRemoved($event);

    $auditLog = AuditLog::where('action', 'order_item_removed')
        ->where('entity_uuid', $this->orderItem->uuid)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->company_id)->toBe($this->company->id)
        ->and($auditLog->branch_id)->toBe($this->branch->id)
        ->and($auditLog->user_id)->toBe($this->user->id)
        ->and($auditLog->entity_type)->toBe(OrderItem::class)
        ->and($auditLog->entity_id)->toBe($this->orderItem->id)
        ->and($auditLog->entity_uuid)->toBe($this->orderItem->uuid)
        ->and($auditLog->reason)->toBe('manual_removal')
        ->and($auditLog->occurred_at)->not->toBeNull()
        ->and($auditLog->payload)->toHaveKeys([
            'order_uuid',
            'order_number',
            'product_id',
            'product_name',
            'quantity',
            'unit_price',
            'subtotal_affected',
            'user_id'
        ])
        ->and($auditLog->payload['order_uuid'])->toBe($this->order->uuid)
        ->and($auditLog->payload['quantity'])->toBe(3)
        ->and($auditLog->payload['unit_price'])->toBe(1500)
        ->and($auditLog->payload['subtotal_affected'])->toBe(4500)
        ->and($auditLog->payload['user_id'])->toBe($this->user->id)
        ->and($auditLog->changes)->toBe([
            'quantity' => ['before' => 3, 'after' => 0],
            'status' => ['before' => 'active', 'after' => 'removed']
        ]);
});

test('M-04: La auditoría es inmutable y no puede ser modificada', function () {
    $this->actingAs($this->user);

    $listener = new AuditOrderEvents(app(\Modules\Audit\Domain\Services\AuditService::class));

    $event = new OrderItemRemoved(
        $this->order,
        $this->orderItem,
        'manual_removal',
        $this->user->id
    );

    $listener->handleOrderItemRemoved($event);

    $auditLog = AuditLog::where('action', 'order_item_removed')->first();

    expect(fn() => $auditLog->update(['reason' => 'modified']))
        ->toThrow(RuntimeException::class);

    expect(fn() => $auditLog->delete())
        ->toThrow(RuntimeException::class);
});
