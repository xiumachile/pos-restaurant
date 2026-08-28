<?php

use App\Shared\Application\TenantContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Kitchen\Domain\Events\BroadcastOrderCancelled;
use Modules\Kitchen\Domain\Events\BroadcastOrderConfirmed;
use Modules\Kitchen\Domain\Events\BroadcastOrderPaid;
use Modules\Kitchen\Domain\Events\BroadcastOrderReady;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear empresa y sucursal
    $this->company = Company::create([
        'tax_id' => 'BROADCAST-EVENTS-' . uniqid(),
        'legal_name' => 'Broadcast Events Company',
        'trade_name' => 'BE Test',
    ]);
    
    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'BR-EV',
        'name' => 'Branch Events',
    ]);
    
    // Crear usuarios
    $this->waiter = User::create([
        'name' => 'Waiter Test',
        'email' => 'waiter-' . uniqid() . '@broadcast-events.test',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
    
    $this->cashier = User::create([
        'name' => 'Cashier Test',
        'email' => 'cashier-' . uniqid() . '@broadcast-events.test',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);
    
    // Crear mesa
    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal', 'zh' => '主厅'],
        'table_number' => 'T-01',
        'capacity' => 4,
    ]);
    
    // Configurar TenantContext para evitar bloqueo de BelongsToTenant
    $tenantContext = app(TenantContext::class);
    $tenantContext->setCompany($this->company->id, $this->branch->id, $this->waiter->id);
    
    // Crear orden base (siguiendo patrón de AuditIntegrationTest)
    $this->order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->waiter->id,
        'cashier_id' => $this->cashier->id,
        'order_number' => 'ORD-BE-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
        'confirmed_at' => now(),
        'paid_at' => now(),
    ]);
});

describe('BroadcastOrderConfirmed', function () {
    test('emite al canal kitchen de la sucursal', function () {
        $event = new BroadcastOrderConfirmed($this->order);
        $channels = $event->broadcastOn();
        
        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-kitchen.' . $this->branch->id);
    });
    
    test('retorna nombre de evento correcto', function () {
        $event = new BroadcastOrderConfirmed($this->order);
        
        expect($event->broadcastAs())->toBe('order.confirmed');
    });
    
    test('incluye datos completos del pedido confirmado', function () {
        $event = new BroadcastOrderConfirmed($this->order);
        $data = $event->broadcastWith();
        
        expect($data)->toHaveKeys([
            'event',
            'order_uuid',
            'order_number',
            'type',
            'status',
            'table',
            'waiter',
            'items_count',
            'notes',
            'confirmed_at',
        ])
        ->and($data['event'])->toBe('order.confirmed')
        ->and($data['order_uuid'])->toBe($this->order->uuid)
        ->and($data['order_number'])->toBe($this->order->order_number)
        ->and($data['type'])->toBe('dine_in')
        ->and($data['status'])->toBe('confirmed')
        ->and($data['table'])->toBe([
            'uuid' => $this->table->uuid,
            'table_number' => $this->table->table_number,
            'area_code' => $this->table->area_code,
        ])
        ->and($data['waiter'])->toBe([
            'uuid' => $this->waiter->uuid,
            'name' => $this->waiter->name,
        ])
        ->and($data['items_count'])->toBe(0)
        ->and($data['confirmed_at'])->toBe($this->order->confirmed_at->toIso8601String());
    });
});

describe('BroadcastOrderPaid', function () {
    test('emite a canales de waiters y dashboard', function () {
        $event = new BroadcastOrderPaid($this->order);
        $channels = $event->broadcastOn();
        
        expect($channels)->toHaveCount(2)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-waiters.' . $this->branch->id)
            ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[1]->name)->toBe('private-dashboard.' . $this->company->id);
    });
    
    test('retorna nombre de evento correcto', function () {
        $event = new BroadcastOrderPaid($this->order);
        
        expect($event->broadcastAs())->toBe('order.paid');
    });
    
    test('incluye datos completos del pedido pagado', function () {
        $event = new BroadcastOrderPaid($this->order);
        $data = $event->broadcastWith();
        
        expect($data)->toHaveKeys([
            'event',
            'order_uuid',
            'order_number',
            'total',
            'table',
            'cashier',
            'paid_at',
        ])
        ->and($data['event'])->toBe('order.paid')
        ->and($data['order_uuid'])->toBe($this->order->uuid)
        ->and($data['order_number'])->toBe($this->order->order_number)
        ->and($data['total'])->toBe(11900.0)
        ->and($data['table'])->toBe([
            'uuid' => $this->table->uuid,
            'table_number' => $this->table->table_number,
        ])
        ->and($data['cashier'])->toBe([
            'uuid' => $this->cashier->uuid,
            'name' => $this->cashier->name,
        ])
        ->and($data['paid_at'])->toBe($this->order->paid_at->toIso8601String());
    });
});

describe('BroadcastOrderReady', function () {
    test('emite al canal waiters de la sucursal', function () {
        $event = new BroadcastOrderReady($this->order);
        $channels = $event->broadcastOn();
        
        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-waiters.' . $this->branch->id);
    });
    
    test('retorna nombre de evento correcto', function () {
        $event = new BroadcastOrderReady($this->order);
        
        expect($event->broadcastAs())->toBe('order.ready');
    });
    
    test('incluye datos completos del pedido listo', function () {
        $event = new BroadcastOrderReady($this->order);
        $data = $event->broadcastWith();
        
        expect($data)->toHaveKeys([
            'event',
            'order_uuid',
            'order_number',
            'type',
            'status',
            'table',
            'waiter',
            'items_count',
            'ready_at',
        ])
        ->and($data['event'])->toBe('order.ready')
        ->and($data['order_uuid'])->toBe($this->order->uuid)
        ->and($data['order_number'])->toBe($this->order->order_number)
        ->and($data['type'])->toBe('dine_in')
        ->and($data['status'])->toBe('confirmed')
        ->and($data['table'])->toBe([
            'uuid' => $this->table->uuid,
            'table_number' => $this->table->table_number,
            'area_code' => $this->table->area_code,
        ])
        ->and($data['waiter'])->toBe([
            'uuid' => $this->waiter->uuid,
            'name' => $this->waiter->name,
        ])
        ->and($data['items_count'])->toBe(0)
        ->and($data['ready_at'])->not->toBeNull();
    });
});

describe('BroadcastOrderCancelled', function () {
    test('emite al canal kitchen de la sucursal', function () {
        $this->order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente cambió de opinión',
        ]);
        
        $event = new BroadcastOrderCancelled($this->order->fresh());
        $channels = $event->broadcastOn();
        
        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-kitchen.' . $this->branch->id);
    });
    
    test('retorna nombre de evento correcto', function () {
        $this->order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente cambió de opinión',
        ]);
        
        $event = new BroadcastOrderCancelled($this->order->fresh());
        
        expect($event->broadcastAs())->toBe('order.cancelled');
    });
    
    test('incluye datos completos del pedido cancelado', function () {
        $this->order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cliente cambió de opinión',
        ]);
        
        $order = $this->order->fresh();
        $event = new BroadcastOrderCancelled($order);
        $data = $event->broadcastWith();
        
        expect($data)->toHaveKeys([
            'event',
            'order_uuid',
            'order_number',
            'table',
            'cancellation_reason',
            'cancelled_at',
        ])
        ->and($data['event'])->toBe('order.cancelled')
        ->and($data['order_uuid'])->toBe($order->uuid)
        ->and($data['order_number'])->toBe($order->order_number)
        ->and($data['table'])->toBe([
            'uuid' => $this->table->uuid,
            'table_number' => $this->table->table_number,
        ])
        ->and($data['cancellation_reason'])->toBe('Cliente cambió de opinión')
        ->and($data['cancelled_at'])->toBe($order->cancelled_at->toIso8601String());
    });
});
