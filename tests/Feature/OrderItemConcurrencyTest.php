<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Tables\Domain\Entities\RestaurantTable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create(['tax_id' => 'TEST', 'legal_name' => 'Test Co', 'trade_name' => 'Test']);
    $this->branch = Branch::create(['company_id' => $this->company->id, 'code' => 'T1', 'name' => 'Test Branch']);
    
    $this->waiter = User::create(['name' => 'Waiter', 'email' => 'w@test.test', 'password' => bcrypt('pw'), 'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'role' => 'waiter']);

    $this->product = Product::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name_translations' => ['es' => 'Prod'], 'base_price' => 1000, 'is_active' => true]);
    $this->menuItem = MenuItem::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'base_price' => 1000, 'is_active' => true]);

    $this->table = RestaurantTable::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'table_number' => '1', 'capacity' => 2, 'area_code' => 'MAIN', 'area_name_translations' => ['es' => 'Salón']]);
    $this->order = Order::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'waiter_id' => $this->waiter->id, 'order_number' => 'ORD-1', 'type' => OrderType::DINE_IN, 'status' => 'draft', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0]);

    $this->token = JWTAuth::fromUser($this->waiter);
});

function m02Headers($token): array {
    return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json', 'Content-Type' => 'application/json'];
}

test('M-02: Modificaciones concurrentes (agregar y quitar) mantienen consistencia de totales', function () {
    $this->withHeaders(m02Headers($this->token))->postJson("/api/v1/orders/{$this->order->uuid}/items", [
        'menu_item_uuid' => $this->menuItem->uuid,
        'quantity' => 2,
    ])->assertStatus(201);

    $this->order->refresh();
    expect($this->order->items()->count())->toBe(1)
        ->and($this->order->total)->toBe(2000);

    $itemUuid = $this->order->items()->first()->uuid;

    $responseA = $this->withHeaders(m02Headers($this->token))->postJson("/api/v1/orders/{$this->order->uuid}/items", [
        'menu_item_uuid' => $this->menuItem->uuid,
        'quantity' => 1,
    ]);

    $responseB = $this->withHeaders(m02Headers($this->token))->deleteJson("/api/v1/orders/{$this->order->uuid}/items/{$itemUuid}");

    $responseA->assertStatus(201);
    $responseB->assertStatus(200);

    $this->order->refresh();
    
    expect($this->order->items()->count())->toBe(1, 'Debe quedar exactamente 1 item')
        ->and($this->order->total)->toBe(1000, 'El total debe reflejar solo el item restante')
        ->and($this->order->subtotal)->toBe(1000);
});

test('M-02: El controller utiliza lockForUpdate y transacciones (verificación de código)', function () {
    $controllerPath = base_path('app/Modules/Orders/Interfaces/Controllers/OrderItemController.php');
    
    expect(file_exists($controllerPath))->toBeTrue('El archivo OrderItemController.php debe existir');
    
    $controllerCode = file_get_contents($controllerPath);
    
    // Sintaxis correcta de Pest: evaluar la condición booleana y pasar el mensaje
    expect(str_contains($controllerCode, 'DB::transaction'))->toBeTrue('Debe usar transacciones DB');
    expect(str_contains($controllerCode, 'lockForUpdate'))->toBeTrue('Debe usar lockForUpdate para prevenir race conditions');
    expect(str_contains($controllerCode, "authorize('update'"))->toBeTrue('Debe autorizar update explícitamente');
});
