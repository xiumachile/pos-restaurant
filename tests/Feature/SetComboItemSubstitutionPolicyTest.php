<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Catalog\Application\UseCases\SetComboItemSubstitutionPolicy;
use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Audit\Domain\Services\AuditService;
use Modules\Identity\Domain\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => 'TEST-POLICY-001',
        'legal_name' => 'Policy Test Company',
        'trade_name' => 'Policy Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'POLICY-001',
        'name' => 'Policy Branch',
    ]);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->bebidasCategory = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Bebidas'],
        'is_active' => true,
    ]);

    $this->postresCategory = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Postres'],
        'is_active' => true,
    ]);

    $this->hamburguesa = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'HAMB-POLICY-001',
        'name_translations' => ['es' => 'Hamburguesa'],
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $this->cocaCola = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'COCA-POLICY-001',
        'name_translations' => ['es' => 'Coca-Cola'],
        'base_price' => 1500,
        'is_active' => true,
    ]);

    $this->comboProduct = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'COMBO-POLICY-001',
        'name_translations' => ['es' => 'Combo Test'],
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

    $this->useCase = new SetComboItemSubstitutionPolicy(new AuditService());
});

// ============================================
// Test 1: Modo no_substitution
// ============================================

test('modo no_substitution desactiva sustitución sin crear regla', function () {
    MenuItemProduct::where('menu_item_id', $this->menuItem->id)
        ->where('product_id', $this->cocaCola->id)
        ->update(['is_substitutable' => true]);

    MenuItemReplacementRule::create([
        'company_id' => $this->company->id,
        'branch_id' => null,
        'menu_item_id' => $this->menuItem->id,
        'target_product_id' => $this->cocaCola->id,
        'rule_type' => MenuItemReplacementRule::RULE_TYPE_ANY,
        'is_active' => true,
    ]);

    $result = $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_NO_SUBSTITUTION,
    );

    expect($result)->toBeNull();

    $component = MenuItemProduct::where('menu_item_id', $this->menuItem->id)
        ->where('product_id', $this->cocaCola->id)
        ->first();
    expect($component->is_substitutable)->toBeFalse();

    $activeRules = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('target_product_id', $this->cocaCola->id)
        ->where('is_active', true)
        ->count();
    expect($activeRules)->toBe(0);
});

// ============================================
// Test 2: Modo any_product
// ============================================

test('modo any_product crea regla de cualquier producto', function () {
    $result = $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
        maxPriceDelta: 2000.0,
        requiresAuthorization: true,
    );

    expect($result)->not->toBeNull()
        ->and($result->rule_type)->toBe(MenuItemReplacementRule::RULE_TYPE_ANY)
        ->and($result->is_active)->toBeTrue()
        ->and((float) $result->max_price_delta)->toEqual(2000.0)
        ->and($result->requires_authorization)->toBeTrue();

    $component = MenuItemProduct::where('menu_item_id', $this->menuItem->id)
        ->where('product_id', $this->cocaCola->id)
        ->first();
    expect($component->is_substitutable)->toBeTrue();
});

// ============================================
// Test 3: Modo allowed_category
// ============================================

test('modo allowed_category crea regla con categoría específica', function () {
    $result = $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY,
        allowedCategoryId: $this->bebidasCategory->id,
    );

    expect($result)->not->toBeNull()
        ->and($result->rule_type)->toBe(MenuItemReplacementRule::RULE_TYPE_CATEGORY)
        ->and($result->allowed_category_id)->toBe($this->bebidasCategory->id)
        ->and($result->is_active)->toBeTrue();
});

// ============================================
// Test 4: Cambio de modo desactiva regla anterior
// ============================================

test('cambio de any_product a allowed_category desactiva regla anterior', function () {
    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
    );

    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY,
        allowedCategoryId: $this->bebidasCategory->id,
    );

    $activeRules = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('target_product_id', $this->cocaCola->id)
        ->where('is_active', true)
        ->get();

    expect($activeRules)->toHaveCount(1)
        ->and($activeRules->first()->rule_type)->toBe(MenuItemReplacementRule::RULE_TYPE_CATEGORY);

    $totalRules = MenuItemReplacementRule::where('menu_item_id', $this->menuItem->id)
        ->where('target_product_id', $this->cocaCola->id)
        ->count();
    expect($totalRules)->toBe(2);
});

// ============================================
// Test 5: Override de sucursal no afecta empresa
// ============================================

test('override de sucursal no afecta regla de empresa', function () {
    $companyRule = $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
        branchId: null,
    );

    $branchRule = $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY,
        allowedCategoryId: $this->bebidasCategory->id,
        branchId: $this->branch->id,
    );

    expect($companyRule)->not->toBeNull()
        ->and($branchRule)->not->toBeNull()
        ->and($companyRule->branch_id)->toBeNull()
        ->and($branchRule->branch_id)->toBe($this->branch->id);

    $companyRuleFresh = MenuItemReplacementRule::find($companyRule->id);
    expect($companyRuleFresh->is_active)->toBeTrue();
});

// ============================================
// Test 6: allowed_category sin category_id lanza excepción
// ============================================

test('allowed_category sin allowed_category_id lanza excepción', function () {
    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY,
        allowedCategoryId: null,
    );
})->throws(\InvalidArgumentException::class, 'allowed_category_id');

// ============================================
// Test 7: Modo inválido lanza excepción
// ============================================

test('modo inválido lanza excepción', function () {
    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: 'invalid_mode',
    );
})->throws(\InvalidArgumentException::class, 'Modo inválido');

// ============================================
// Test 8: Producto no pertenece al combo lanza excepción
// ============================================

test('producto que no pertenece al combo lanza excepción', function () {
    $productoAislado = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'AISLADO-001',
        'name_translations' => ['es' => 'Producto Aislado'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $productoAislado,
        mode: SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
    );
})->throws(\InvalidArgumentException::class, 'no forma parte del combo');

// ============================================
// Test 9: Auditoría registra el cambio (CORREGIDO)
// ============================================

test('cambio de política queda registrado en audit_logs', function () {
    $this->actingAs($this->admin);

    $this->useCase->execute(
        menuItem: $this->menuItem,
        targetProduct: $this->cocaCola,
        mode: SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
    );

    $auditLog = AuditLog::where('action', 'combo_substitution_policy_changed')
        ->where('entity_type', MenuItem::class)
        ->where('entity_id', $this->menuItem->id)
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->payload['menu_item_id'])->toBe($this->menuItem->id)
        ->and($auditLog->payload['target_product_id'])->toBe($this->cocaCola->id)
        ->and($auditLog->changes['after']['rule_type'])->toBe(MenuItemReplacementRule::RULE_TYPE_ANY)
        ->and($auditLog->changes['after']['is_substitutable'])->toBeTrue();
});
