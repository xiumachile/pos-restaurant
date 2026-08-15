<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Domain\ValueObjects\TaxType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::forceCreate([
        'tax_id' => '76.888.888-8',
        'legal_name' => 'Tax Auth Test SpA',
        'trade_name' => 'Tax Auth Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'TAUTH',
        'name' => 'Tax Auth Branch',
    ]);

    $this->waiter = User::forceCreate([
        'name' => 'Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->kitchen = User::forceCreate([
        'name' => 'Kitchen',
        'email' => 'kitchen-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'kitchen',
    ]);

    $this->cashier = User::forceCreate([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    $this->manager = User::forceCreate([
        'name' => 'Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->iva19 = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'IVA 19%',
        'code' => 'IVA',
        'type' => TaxType::PERCENT,
        'rate' => 19.00,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('waiter no puede crear impuestos (403)', function () {
    $token = JWTAuth::fromUser($this->waiter);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/taxes', [
        'name' => 'Test',
        'code' => 'T1',
        'type' => 'percent',
        'rate' => 10,
    ]);

    $response->assertStatus(403);
});

test('kitchen no puede crear impuestos (403)', function () {
    $token = JWTAuth::fromUser($this->kitchen);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/taxes', [
        'name' => 'Test',
        'code' => 'T1',
        'type' => 'percent',
        'rate' => 10,
    ]);

    $response->assertStatus(403);
});

test('cashier no puede crear impuestos (403)', function () {
    $token = JWTAuth::fromUser($this->cashier);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/taxes', [
        'name' => 'Test',
        'code' => 'T1',
        'type' => 'percent',
        'rate' => 10,
    ]);

    $response->assertStatus(403);
});

test('waiter no puede actualizar impuestos (403)', function () {
    $token = JWTAuth::fromUser($this->waiter);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->patchJson("/api/v1/taxes/{$this->iva19->uuid}", [
        'name' => 'IVA Actualizado',
    ]);

    $response->assertStatus(403);
});

test('waiter no puede eliminar impuestos (403)', function () {
    $token = JWTAuth::fromUser($this->waiter);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/taxes/{$this->iva19->uuid}");

    $response->assertStatus(403);
});

test('manager puede crear impuestos (201)', function () {
    $token = JWTAuth::fromUser($this->manager);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/taxes', [
        'name' => 'IVA Reducido',
        'code' => 'IVA-R',
        'type' => 'percent',
        'rate' => 10,
    ]);

    $response->assertStatus(201);
});

test('waiter puede listar impuestos (200)', function () {
    $token = JWTAuth::fromUser($this->waiter);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/taxes');

    $response->assertStatus(200);
});
