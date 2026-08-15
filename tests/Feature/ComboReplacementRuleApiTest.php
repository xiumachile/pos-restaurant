<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TEST-API-001',
        'legal_name' => 'API Test Company',
        'trade_name' => 'API Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'API-001',
        'name' => 'API Branch',
    ]);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->waiter = User::create([
        'name' => 'Waiter User',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $this->bebidasCategory = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Bebidas'],
        'is_active' => true,
    ]);

    $this->cocaCola = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'COCA-API-001',
        'name_translations' => ['es' => 'Coca-Cola'],
        'base_price' => 1500,
        'is_active' => true,
    ]);

    $this->comboProduct = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'COMBO-API-001',
        'name_translations' => ['es' => 'Combo API Test'],
        'base_price' => 0,
        'is_combo' => true,
        'is_active' => true,
    ]);

    $this->menuItem = MenuItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $this->comboProduct->id,
        'base_price' => 7000,
        'is_active' => true,
    ]);

    MenuItemProduct::create([
        'menu_item_id' => $this->menuItem->id,
        'product_id' => $this->cocaCola->id,
        'quantity' => 1,
        'is_substitutable' => false,
    ]);
});

// ============================================
// GET /substitution-policies
// ============================================

test('GET substitution-policies retorna políticas efectivas', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->getJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/substitution-policies");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'menu_item_id',
                'items' => [
                    '*' => ['product_id', 'product_name', 'quantity', 'mode', 'scope'],
                ],
            ],
        ]);

    // El producto debería estar como no_substitution (sin regla definida)
    $items = $response->json('data.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['mode'])->toBe('no_substitution');
});

// ============================================
// PUT /substitution-policy
// ============================================

test('PUT aplica política any_product correctamente', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'any_product',
            'max_price_delta' => 2000,
            'requires_authorization' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mode', 'any_product')
        ->assertJsonPath('data.is_substitutable', true);

    // Verificar que se creó la regla en BD
    $rule = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('target_product_id', $this->cocaCola->id)
        ->where('is_active', true)
        ->first();

    expect($rule)->not->toBeNull()
        ->and($rule->rule_type)->toBe(MenuItemReplacementRule::RULE_TYPE_ANY);
});

test('PUT aplica política allowed_category correctamente', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'allowed_category',
            'allowed_category_id' => $this->bebidasCategory->uuid,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mode', 'allowed_category');

    // Verificar la regla en BD
    $rule = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('is_active', true)
        ->first();

    expect($rule)->not->toBeNull()
        ->and($rule->rule_type)->toBe(MenuItemReplacementRule::RULE_TYPE_CATEGORY)
        ->and($rule->allowed_category_id)->toBe($this->bebidasCategory->id);
});

test('PUT aplica política no_substitution correctamente', function () {
    // Primero activar con any_product
    $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'any_product',
        ]);

    // Luego cambiar a no_substitution
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'no_substitution',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mode', 'no_substitution')
        ->assertJsonPath('data.is_substitutable', false);
});

// ============================================
// Validaciones
// ============================================

test('PUT con allowed_category sin category_id retorna 422', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'allowed_category',
            // ← Falta allowed_category_id
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('allowed_category_id');
});

test('PUT con modo inválido retorna 422', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'invalid_mode',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('mode');
});

// ============================================
// Autorización
// ============================================

test('PUT con rol waiter retorna 403', function () {
    $response = $this->actingAs($this->waiter, 'api')
        ->putJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'mode' => 'any_product',
        ]);

    $response->assertStatus(403);
});

// ============================================
// DELETE
// ============================================

test('DELETE elimina override de sucursal', function () {
    // Crear override de sucursal
    MenuItemReplacementRule::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'menu_item_id' => $this->menuItem->id,
        'target_product_id' => $this->cocaCola->id,
        'rule_type' => MenuItemReplacementRule::RULE_TYPE_ANY,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin, 'api')
        ->deleteJson("/api/v1/catalog/combos/{$this->menuItem->uuid}/items/{$this->cocaCola->uuid}/substitution-policy", [
            'branch_id' => $this->branch->uuid,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deactivated_rules', 1);

    // Verificar que la regla fue desactivada
    $activeRules = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('branch_id', $this->branch->id)
        ->where('is_active', true)
        ->count();
    expect($activeRules)->toBe(0);
});
