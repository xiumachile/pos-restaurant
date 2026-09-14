<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Accounting\Domain\Entities\Account;

uses(RefreshDatabase::class);

/**
 * TEST DE INTEGRIDAD P0: Idempotencia scoping a tenant
 * 
 * Valida que dos tenants diferentes pueden usar el mismo idempotency_key
 * sin colisionar. Previene cross-tenant data leakage vía idempotencia.
 * 
 * ADR-002: Multi-tenant isolation
 * ADR-015: Idempotencia scoping a tenant
 * Migraciones: 2026_09_15_000001, 2026_09_15_000002
 */
beforeEach(function () {
    // Tenant A
    $this->companyA = Company::forceCreate([
        'tax_id' => '76.111.111-1',
        'legal_name' => 'Tenant A SpA',
        'trade_name' => 'Tenant A',
    ]);
    // NO usar enableAllCapabilities() para evitar requires_cashier_session
    Account::seedDefaultsFor($this->companyA->id);
    
    $this->branchA = Branch::forceCreate([
        'company_id' => $this->companyA->id,
        'code' => 'A1',
        'name' => 'Branch A',
    ]);
    $this->userA = User::forceCreate([
        'name' => 'User A',
        'email' => 'user-a-' . uniqid() . '@test.com',
        'password' => 'password',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    // Tenant B
    $this->companyB = Company::forceCreate([
        'tax_id' => '76.222.222-2',
        'legal_name' => 'Tenant B SpA',
        'trade_name' => 'Tenant B',
    ]);
    // NO usar enableAllCapabilities() para evitar requires_cashier_session
    Account::seedDefaultsFor($this->companyB->id);
    
    $this->branchB = Branch::forceCreate([
        'company_id' => $this->companyB->id,
        'code' => 'B1',
        'name' => 'Branch B',
    ]);
    $this->userB = User::forceCreate([
        'name' => 'User B',
        'email' => 'user-b-' . uniqid() . '@test.com',
        'password' => 'password',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    // Payment method para Tenant A
    $this->pmA = PaymentMethod::forceCreate([
        'company_id' => $this->companyA->id,
        'branch_id' => null,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo', 'en' => 'Cash'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    // Payment method para Tenant B
    $this->pmB = PaymentMethod::forceCreate([
        'company_id' => $this->companyB->id,
        'branch_id' => null,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo', 'en' => 'Cash'],
        'type' => 'cash',
        'is_active' => true,
    ]);
});

test('dos tenants pueden usar el mismo idempotency_key sin colisionar en payments', function () {
    $sharedKey = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

    // Tenant A crea order (autenticado)
    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-A001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED, // Necesita estar SERVED para pagar
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Tenant A hace payment con key compartida
    $responseA = $this->actingAs($this->userA, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->pmA->uuid,
            'amount' => 11900,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    

    // Tenant B crea order (autenticado)
    $orderB = Order::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'waiter_id' => $this->userB->id,
        'order_number' => 'ORD-B001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED, // Necesita estar SERVED para pagar
        'subtotal' => 20000,
        'tax_amount' => 3800,
        'discount_amount' => 0,
        'total' => 23800,
    ]);

    // Tenant B hace payment con la MISMA key — NO debe colisionar
    $responseB = $this->actingAs($this->userB, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderB->uuid,
            'payment_method_uuid' => $this->pmB->uuid,
            'amount' => 23800,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    
    // CRÍTICO: B debe poder crear SU payment, no recibir respuesta cacheada de A
    $responseB->assertStatus(201);
    expect($responseB->json('data.uuid'))->not->toBe($responseA->json('data.uuid'));
});

test('mismo tenant usando la misma key dos veces retorna respuesta cacheada', function () {
    $sharedKey = 'cccccccc-dddd-4eee-8fff-aaaaaaaaaaaa';

    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-A002',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Primer request
    $response1 = $this->actingAs($this->userA, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->pmA->uuid,
            'amount' => 11900,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    

    // Segundo request con MISMA key y mismo body — debe retornar respuesta cacheada
    $response2 = $this->actingAs($this->userA, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->pmA->uuid,
            'amount' => 11900,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    
    // CRÍTICO: Debe retornar el MISMO payment (idempotencia funciona)
    $response2->assertStatus(201);
    expect($response2->json('data.uuid'))->toBe($response1->json('data.uuid'));
});

test('mismo tenant con key reutilizada pero body diferente retorna 409', function () {
    $sharedKey = 'eeeeeeee-ffff-4aaa-8bbb-cccccccccccc';

    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-A003',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::SERVED,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'discount_amount' => 0,
        'total' => 11900,
    ]);

    // Primer request
    $response1 = $this->actingAs($this->userA, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->pmA->uuid,
            'amount' => 11900,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    

    // Segundo request con MISMA key pero DIFERENTE amount — debe retornar 409 conflict
    $response2 = $this->actingAs($this->userA, 'api')
        ->postJson("/api/v1/billing/payments", [
            'order_uuid' => $orderA->uuid,
            'payment_method_uuid' => $this->pmA->uuid,
            'amount' => 5000,
            'tip_amount' => 0,
            'idempotency_key' => $sharedKey,
        ], ['Idempotency-Key' => $sharedKey]);
    
    $response2->assertStatus(409);
});
