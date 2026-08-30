<?php

use Modules\Branches\Domain\Entities\Branch;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\Bill;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'CAP-' . uniqid(),
        'legal_name' => 'Capability Test Company',
        'trade_name' => 'Capability Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CAP-BR',
        'name' => 'Capability Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '1',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón Principal'],
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->register = CashRegister::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'Test Register',
        'code' => 'REG-001',
        'is_active' => true,
    ]);
});

// ============================================
// TEST: BillController::split() con capability deshabilitado
// ============================================

test('split bill retorna error si can_split_bills no está habilitado', function () {
    // NO habilitar can_split_bills
    enableCapabilities($this->company, ['can_accept_tips', 'can_print_receipts']);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-CAP-001',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error', 'capability_not_enabled')
        ->assertJsonPath('required_capability', 'can_split_bills');
});

test('split bill funciona si can_split_bills está habilitado', function () {
    enableAllCapabilities($this->company);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-CAP-002',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson("/api/v1/orders/{$order->uuid}/split", [
            'type' => 'equal_split',
            'parts' => 2,
        ]);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

// ============================================
// TEST: OrderService con has_kitchen_display deshabilitado
// ============================================

test('orden se marca como ready si has_kitchen_display no está habilitado', function () {
    // Habilitar solo capabilities básicas, NO has_kitchen_display
    enableCapabilities($this->company, ['requires_cashier_session', 'can_accept_tips']);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-CAP-003',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    $token = loginAs($this->cashier);

    // Confirmar el pedido
    $response = $this->withHeaders(authHeaders($token))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => 'confirmed',
        ]);

    $response->assertStatus(200);

    // Verificar que el pedido pasó directamente a READY (saltó CONFIRMED)
    $order->refresh();
    expect($order->status->value)->toBe('ready');
});

test('orden se mantiene en confirmed si has_kitchen_display está habilitado', function () {
    enableAllCapabilities($this->company);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->cashier->id,
        'order_number' => 'ORD-CAP-004',
        'status' => OrderStatus::DRAFT,
        'subtotal' => 100,
        'tax_amount' => 19,
        'total' => 119,
    ]);

    $token = loginAs($this->cashier);

    // Confirmar el pedido
    $response = $this->withHeaders(authHeaders($token))
        ->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => 'confirmed',
        ]);

    $response->assertStatus(200);

    // Verificar que el pedido se mantuvo en CONFIRMED
    $order->refresh();
    expect($order->status->value)->toBe('confirmed');
});

// ============================================
// TEST: CashSessionController con requires_cashier_session deshabilitado
// ============================================

test('open cash session retorna error si requires_cashier_session no está habilitado', function () {
    // NO habilitar requires_cashier_session
    enableCapabilities($this->company, ['can_accept_tips', 'can_print_receipts']);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 100,
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error', 'capability_not_enabled')
        ->assertJsonPath('required_capability', 'requires_cashier_session');
});

test('open cash session funciona si requires_cashier_session está habilitado', function () {
    enableAllCapabilities($this->company);

    $token = loginAs($this->cashier);

    $response = $this->withHeaders(authHeaders($token))
        ->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 100,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'open');
});
