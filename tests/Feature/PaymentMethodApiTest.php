<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\PaymentMethod;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->company = Company::create([
        'tax_id' => 'PM-API-' . uniqid(),
        'legal_name' => 'Payment Method Company',
        'trade_name' => 'Payment Method Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'PM-A',
        'name' => 'Payment Method Branch',
    ]);

    $this->cashier = User::create([
        'name' => 'Test Cashier',
        'email' => 'pm-cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'PM-API-B-' . uniqid(),
        'legal_name' => 'Payment Method Company B',
        'trade_name' => 'Payment Method Restaurant B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'PM-B',
        'name' => 'Payment Method Branch B',
    ]);

    $this->cashierB = User::create([
        'name' => 'Test Cashier B',
        'email' => 'pm-cashier-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);

    PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Efectivo'],
        'type' => 'cash',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'card',
        'name_translations' => ['es' => 'Tarjeta'],
        'type' => 'card',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    PaymentMethod::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'code' => 'inactive',
        'name_translations' => ['es' => 'Inactivo'],
        'type' => 'other',
        'is_active' => false,
        'sort_order' => 3,
    ]);

    PaymentMethod::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'code' => 'transfer-b',
        'name_translations' => ['es' => 'Transferencia B'],
        'type' => 'transfer',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->token = JWTAuth::fromUser($this->cashier);
    $this->tokenB = JWTAuth::fromUser($this->cashierB);
});

function paymentMethodApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer ' . ($token ?? test()->token),
        'Accept' => 'application/json',
    ];
}

test('GET /api/v1/payment-methods lista solo metodos activos de la branch', function () {
    $response = $this->withHeaders(paymentMethodApiHeaders())
        ->getJson('/api/v1/payment-methods');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $codes = collect($response->json('data'))->pluck('code')->all();

    expect($codes)->toContain('cash')
        ->and($codes)->toContain('card')
        ->and($codes)->not->toContain('inactive')
        ->and($codes)->not->toContain('transfer-b');
});

test('GET /api/v1/payment-methods usuario B no ve metodos de empresa A', function () {
    $response = $this->withHeaders(paymentMethodApiHeaders($this->tokenB))
        ->getJson('/api/v1/payment-methods');

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.code'))->toBe('transfer-b');
});

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/payment-methods');
    $response->assertStatus(401);
});
