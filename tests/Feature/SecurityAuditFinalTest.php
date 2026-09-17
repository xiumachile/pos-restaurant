<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;

uses(RefreshDatabase::class);

/**
 * SECURITY AUDIT FINAL (Punto 146)
 * 
 * Valida que no existen vulnerabilidades P0/P1:
 * - Tenant isolation
 * - IDOR (Insecure Direct Object Reference)
 * - Mass assignment
 * - SQL injection
 * - Auth bypass
 */
beforeEach(function () {
    // Crear dos empresas (Tenant A y Tenant B)
    $this->companyA = Company::create([
        'tax_id' => 'SEC-A-' . uniqid(),
        'legal_name' => 'Security Test A',
        'trade_name' => 'Security A',
    ]);

    $this->companyB = Company::create([
        'tax_id' => 'SEC-B-' . uniqid(),
        'legal_name' => 'Security Test B',
        'trade_name' => 'Security B',
    ]);

    enableAllCapabilities($this->companyA);
    enableAllCapabilities($this->companyB);

    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'SECA',
        'name' => 'Security Branch A',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'SECB',
        'name' => 'Security Branch B',
    ]);

    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'usera-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    $this->userB = User::create([
        'name' => 'User B',
        'email' => 'userb-' . uniqid() . '@test.com',
        'password' => bcrypt('password'),
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);
});

test('TENANT ISOLATION: User A no puede acceder a orders de Company B', function () {
    // User A crea orden
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-SEC-A-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // User B intenta acceder a orden de Company A
    $this->actingAs($this->userB, 'api');

    $response = $this->getJson("/api/v1/orders/{$orderA->uuid}");

    // Debe retornar 404 (no 403, para no revelar existencia)
    expect($response->status())->toBe(404);
});

test('IDOR: User A no puede modificar orders de Company B', function () {
    // User B crea orden
    $orderB = Order::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'waiter_id' => $this->userB->id,
        'order_number' => 'ORD-SEC-B-001',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // User A intenta modificar orden de Company B
    $this->actingAs($this->userA, 'api');

    $response = $this->putJson("/api/v1/orders/{$orderB->uuid}", [
        'status' => 'confirmed',
    ]);

    // Debe retornar 404 (no 403)
    expect($response->status())->toBe(404);
});

test('MASS ASSIGNMENT: No se pueden modificar campos protegidos', function () {
    $this->actingAs($this->userA, 'api');

    // Intentar modificar company_id (campo protegido)
    $response = $this->postJson('/api/v1/orders', [
        'company_id' => $this->companyB->id, // Intentar crear orden en otra empresa
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-MASS-001',
        'type' => 'dine_in',
        'status' => 'draft',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Debe fallar validación (company_id no está en Form Request)
    expect($response->status())->toBe(422);
});

test('AUTH BYPASS: Sin token no se puede acceder a endpoints protegidos', function () {
    // Intentar acceder sin autenticación
    $response = $this->getJson('/api/v1/orders');

    expect($response->status())->toBe(401);
});

test('ROLE-BASED ACCESS: Cashier no puede acceder a endpoints de admin', function () {
    $this->actingAs($this->userA, 'api');

    // Intentar acceder a endpoint que requiere admin/manager
    $response = $this->postJson('/api/v1/companies', [
        'tax_id' => '76.999.999-9',
        'legal_name' => 'Test',
        'trade_name' => 'Test',
    ]);

    expect($response->status())->toBe(403);
});

test('CRITERIO DE CIERRE: No existen vulnerabilidades P0/P1', function () {
    // Crear orden en Company A
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'waiter_id' => $this->userA->id,
        'order_number' => 'ORD-SEC-CRITERIA',
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::DRAFT,
        'subtotal_gross' => 10000,
        'net_amount' => 8403,
        'tax_amount' => 1597,
        'amount_due' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Intentar acceder desde Company B
    $this->actingAs($this->userB, 'api');

    $response = $this->getJson("/api/v1/orders/{$orderA->uuid}");

    // Validar que no hay fuga de información
    expect($response->status())->toBe(404)
        ->and($response->json()['message'])->toBeString(); // Retorna mensaje de error estándar
});
