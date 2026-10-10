<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Tables\Domain\Entities\RestaurantTable;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->waiter = User::create(['name' => 'Waiter', 'email' => 'w@test.test', 'password' => bcrypt('pw'), 'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'role' => 'waiter']);

    $this->table = RestaurantTable::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'table_number' => '1', 'capacity' => 2, 'area_code' => 'MAIN', 'area_name_translations' => ['es' => 'Salón']]);
    $this->order = Order::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'waiter_id' => $this->waiter->id, 'order_number' => 'ORD-1', 'type' => 'dine_in', 'status' => 'draft', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0]);

    $this->stateMachine = app(OrderStateMachine::class);
});

test('M-03: OrderConfirmed tiene event_uuid único para deduplicación', function () {
    $event1 = new OrderConfirmed($this->order);
    $event2 = new OrderConfirmed($this->order);

    expect($event1->event_uuid)->toBeString()
        ->and($event1->event_uuid)->not->toBe($event2->event_uuid, 'Cada instancia del evento debe tener un UUID único')
        ->and(\Illuminate\Support\Str::isUuid($event1->event_uuid))->toBeTrue('El event_uuid debe ser un UUID válido');
});

test('M-03: Transición exitosa despacha evento correctamente', function () {
    Event::fake();

    $this->stateMachine->transition($this->order, OrderStatus::CONFIRMED);

    // Verificar que el evento se despachó
    Event::assertDispatched(OrderConfirmed::class, function ($event) {
        return $event->order->id === $this->order->id 
            && \Illuminate\Support\Str::isUuid($event->event_uuid);
    });

    // Verificar que el pedido se guardó con el nuevo estado
    $this->order->refresh();
    expect($this->order->status->value)->toBe('confirmed')
        ->and($this->order->confirmed_at)->not->toBeNull();
});

test('M-03: El código utiliza DB::transaction y DB::afterCommit para garantías de publicación', function () {
    // Verificación estática del código fuente para garantizar las protecciones arquitectónicas
    $stateMachinePath = base_path('app/Modules/Orders/Domain/Services/OrderStateMachine.php');
    
    expect(file_exists($stateMachinePath))->toBeTrue('El archivo OrderStateMachine.php debe existir');
    
    $code = file_get_contents($stateMachinePath);
    
    // Sintaxis correcta de Pest: evaluar la condición booleana y pasar el mensaje
    expect(str_contains($code, 'DB::transaction'))->toBeTrue('Debe usar DB::transaction para atomicidad');
    expect(str_contains($code, 'DB::afterCommit'))->toBeTrue('Debe usar DB::afterCommit para garantizar que el evento solo se despache si la transacción tiene éxito');
    expect(str_contains($code, 'event_uuid'))->toBeTrue('El evento debe incluir un UUID único para deduplicación en listeners');
});
