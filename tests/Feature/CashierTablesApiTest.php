<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Catalog\Domain\Entities\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TBL-' . uniqid(),
        'legal_name' => 'Tables Test Company',
        'trade_name' => 'Tables Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'TBL-BR',
        'name' => 'Tables Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-tbl-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter',
        'email' => 'waiter-tbl-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->table = RestaurantTable::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_number' => '1',
        'capacity' => 4,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón'],
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'PROD-TBL-' . uniqid(),
        'name_translations' => ['es' => 'Producto Test'],
        'base_price' => 10000,
        'is_active' => true,
    ]);

    $this->cashMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'CASH',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
});

function tablesHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
}

// ============================================
// CAPABILITY OFF: empresa NO controla caja
// ============================================

test('tables-with-bills funciona sin sesión cuando capability OFF', function () {
    // NO habilitar requires_cashier_session
    enableCapabilities($this->company, ['can_accept_tips', 'can_print_receipts']);

    $response = $this->withHeaders(tablesHeaders($this->token))
        ->getJson('/api/v1/cashier/tables-with-bills');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

test('charge table funciona sin sesión cuando capability OFF', function () {
    // NO habilitar requires_cashier_session
    enableCapabilities($this->company, ['can_accept_tips', 'can_print_receipts']);

    // Crear orden servida
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TBL-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // NO abrir sesión de caja
    $response = $this->withHeaders(tablesHeaders($this->token))
        ->postJson("/api/v1/cashier/tables/{$this->table->uuid}/charge", [
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    // Debe permitir cobrar sin sesión (capability OFF = sin control de caja)
    $response->assertOk();
});

// ============================================
// CAPABILITY ON: empresa SÍ controla caja
// ============================================

test('charge table bloquea sin sesión cuando capability ON', function () {
    // Habilitar requires_cashier_session
    enableCapabilities($this->company, ['requires_cashier_session', 'can_accept_tips']);

    // Crear orden servida
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TBL-2-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // NO abrir sesión de caja
    $response = $this->withHeaders(tablesHeaders($this->token))
        ->postJson("/api/v1/cashier/tables/{$this->table->uuid}/charge", [
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    // Debe bloquear con 403 (capability ON requiere sesión abierta)
    $response->assertStatus(403)
        ->assertJsonPath('error', 'cash_session_required')
        ->assertJsonPath('required_capability', 'requires_cashier_session');
});

test('charge table permite con sesión abierta cuando capability ON', function () {
    // Habilitar requires_cashier_session
    enableCapabilities($this->company, ['requires_cashier_session', 'can_accept_tips']);

    // Crear sesión de caja abierta
    CashSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'session_number' => 'CS-TBL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => CashSessionStatus::OPEN,
        'opening_amount' => 50000,
        'opened_at' => now(),
    ]);

    // Crear orden servida
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'table_id' => $this->table->id,
        'waiter_id' => $this->waiter->id,
        'order_number' => 'ORD-TBL-3-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    // Con sesión abierta, debe permitir cobrar
    $response = $this->withHeaders(tablesHeaders($this->token))
        ->postJson("/api/v1/cashier/tables/{$this->table->uuid}/charge", [
            'payment_method_uuid' => $this->cashMethod->uuid,
            'amount' => 11900,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

    $response->assertOk();
});
