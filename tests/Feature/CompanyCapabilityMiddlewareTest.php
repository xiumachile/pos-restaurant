<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\CompanyCapability;
use Modules\Identity\Domain\Entities\User;
use Modules\Inventory\Domain\Entities\InventoryItem;
use Modules\Orders\Domain\Entities\Order;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa con capability de inventory habilitado
    $this->companyWithInventory = Company::create([
        'tax_id' => 'INV-' . uniqid(),
        'legal_name' => 'Company With Inventory',
        'trade_name' => 'Inventory Co',
    ]);

    $this->branchInv = Branch::create([
        'company_id' => $this->companyWithInventory->id,
        'code' => 'BR-INV',
        'name' => 'Branch Inventory',
    ]);

    CompanyCapability::create([
        'company_id' => $this->companyWithInventory->id,
        'capability_key' => 'can_manage_inventory',
        'is_enabled' => true,
    ]);

    $this->adminWithInventory = User::create([
        'name' => 'Admin Inventory',
        'email' => 'admin-inv-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyWithInventory->id,
        'branch_id' => $this->branchInv->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Empresa SIN capability de inventory
    $this->companyWithoutInventory = Company::create([
        'tax_id' => 'NO-INV-' . uniqid(),
        'legal_name' => 'Company Without Inventory',
        'trade_name' => 'No Inventory Co',
    ]);

    $this->branchNoInv = Branch::create([
        'company_id' => $this->companyWithoutInventory->id,
        'code' => 'BR-NOINV',
        'name' => 'Branch No Inventory',
    ]);

    // NO creamos capability de inventory para esta empresa

    $this->adminWithoutInventory = User::create([
        'name' => 'Admin No Inventory',
        'email' => 'admin-noinv-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyWithoutInventory->id,
        'branch_id' => $this->branchNoInv->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);
});

// ============================================
// Middleware: can_manage_inventory
// ============================================

test('usuario con capability puede acceder a inventory', function () {
    $token = loginAs($this->adminWithInventory);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/inventory');

    $response->assertStatus(200);
});

test('usuario SIN capability NO puede acceder a inventory', function () {
    $token = loginAs($this->adminWithoutInventory);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/inventory');

    $response->assertStatus(403)
        ->assertJsonPath('error', 'capability_not_enabled')
        ->assertJsonPath('required_capability', 'can_manage_inventory');
});

test('super_admin siempre puede acceder a inventory (sin capability)', function () {
    $superCompany = Company::create([
        'tax_id' => 'SUPER-' . uniqid(),
        'legal_name' => 'Super Admin Corp',
        'trade_name' => 'Super Admin',
    ]);

    $superAdmin = User::withoutGlobalScopes()->create([
        'name' => 'Super Admin',
        'email' => 'super-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $superCompany->id,
        'branch_id' => null,
        'role' => 'super_admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    $token = loginAs($superAdmin);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/inventory');

    $response->assertStatus(200);
});

test('deshabilitar capability bloquea acceso a inventory', function () {
    // Deshabilitar capability
    $capability = CompanyCapability::where('company_id', $this->companyWithInventory->id)
        ->where('capability_key', 'can_manage_inventory')
        ->first();
    
    $capability->update(['is_enabled' => false]);

    $token = loginAs($this->adminWithInventory);

    $response = $this->withHeaders(authHeaders($token))
        ->getJson('/api/v1/inventory');

    $response->assertStatus(403);
});

test('middleware protege todos los endpoints de inventory', function () {
    // Crear item de inventario para testear endpoints con UUID
    $item = InventoryItem::create([
        'company_id' => $this->companyWithInventory->id,
        'sku' => 'TEST-001',
        'name_translations' => ['es' => 'Test Item'],
        'unit' => 'kg',
        'cost_price' => 10.00,
    ]);

    $endpoints = [
        ['GET', '/api/v1/inventory'],
        ['GET', '/api/v1/inventory/alerts'],
        ['GET', "/api/v1/inventory/{$item->uuid}"],
        ['GET', "/api/v1/inventory/{$item->uuid}/movements"],
    ];

    foreach ($endpoints as [$method, $url]) {
        // Usar actingAs() en lugar de loginAs() para evitar problemas de token
        $response = $this->actingAs($this->adminWithoutInventory, 'api')
            ->call($method, $url);

        $response->assertStatus(403, "Endpoint {$method} {$url} debería retornar 403");
    }
});
