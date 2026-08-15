<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Catalog\Application\UseCases\ValidateComboSubstitution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear empresa y sucursal
    $this->company = Company::create([
        'tax_id' => 'TEST-001',
        'legal_name' => 'Test Company',
        'trade_name' => 'Test Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'TEST-001',
        'name' => 'Test Branch',
    ]);

    // Crear categorías
    $this->bebidasCategory = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Bebidas', 'zh' => '饮料'],
        'is_active' => true,
    ]);

    $this->postresCategory = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Postres', 'zh' => '甜点'],
        'is_active' => true,
    ]);

    // Crear productos (TODOS con is_active = true)
    $this->hamburguesa = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'HAMB-001',
        'name_translations' => ['es' => 'Hamburguesa', 'zh' => '汉堡'],
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $this->cocaCola = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'COCA-001',
        'name_translations' => ['es' => 'Coca-Cola', 'zh' => '可口可乐'],
        'base_price' => 1500,
        'is_active' => true,
    ]);

    $this->jugo = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'JUGO-001',
        'name_translations' => ['es' => 'Jugo Natural', 'zh' => '天然果汁'],
        'base_price' => 2000,
        'is_active' => true,
    ]);

    $this->agua = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'AGUA-001',
        'name_translations' => ['es' => 'Agua', 'zh' => '水'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $this->helado = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->postresCategory->id,
        'sku' => 'HELADO-001',
        'name_translations' => ['es' => 'Helado', 'zh' => '冰淇淋'],
        'base_price' => 2500,
        'is_active' => true,
    ]);

    // Crear combo (producto tipo combo)
    $this->comboProduct = Product::create([
        'company_id' => $this->company->id,
        'sku' => 'COMBO-001',
        'name_translations' => ['es' => 'Combo Hamburguesa', 'zh' => '汉堡套餐'],
        'base_price' => 0,
        'is_combo' => true,
        'is_active' => true,
    ]);

    // Crear MenuItem (definición del combo)
    $this->menuItem = MenuItem::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $this->comboProduct->id,
        'base_price' => 7000,
        'discount_amount' => 1000,
        'is_active' => true,
    ]);

    // Agregar componentes al combo
    MenuItemProduct::create([
        'menu_item_id' => $this->menuItem->id,
        'product_id' => $this->hamburguesa->id,
        'quantity' => 1,
        'is_substitutable' => false,
    ]);

    MenuItemProduct::create([
        'menu_item_id' => $this->menuItem->id,
        'product_id' => $this->cocaCola->id,
        'quantity' => 2,
        'is_substitutable' => true,
    ]);

    // Crear reglas de sustitución
    MenuItemReplacementRule::create([
        'company_id' => $this->company->id,
        'branch_id' => null,
        'menu_item_id' => $this->menuItem->id,
        'target_product_id' => $this->cocaCola->id,
        'rule_type' => 'allowed_category',
        'allowed_category_id' => $this->bebidasCategory->id,
        'max_price_delta' => 1000,
        'requires_authorization' => false,
        'priority' => 1,
        'is_active' => true,
    ]);

    $this->validator = new ValidateComboSubstitution();
});

test('deniega sustitucion de hamburguesa (no es sustituible)', function () {
    $result = $this->validator->execute(
        $this->menuItem,
        $this->hamburguesa,
        $this->helado,
        1
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('product_not_substitutable');
});

test('deniega sustitucion por producto de categoria diferente', function () {
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->helado, // Postre, no bebida
        1
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('replacement_not_allowed_by_rules');
});

test('deniega sustitucion si cantidad excede disponible', function () {
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo,
        5 // Solo hay 2 coca-colas en el combo
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('quantity_exceeds_available');
});

test('deniega sustitucion si excede max_price_delta', function () {
    // Crear producto muy caro
    $bebidaPremium = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->bebidasCategory->id,
        'sku' => 'PREMIUM-001',
        'name_translations' => ['es' => 'Bebida Premium'],
        'base_price' => 5000, // Delta sería 3500, pero max es 1000
        'is_active' => true,  // ← AGREGAR ESTA LÍNEA
    ]);

    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $bebidaPremium,
        1
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('exceeds_max_price_delta');
});

test('permite sustitucion parcial (1 de 2 coca-colas)', function () {
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo,
        1 // Solo cambiar 1 de las 2 coca-colas
    );

    expect($result->isAllowed())->toBeTrue();
    expect($result->totalExtraCharge)->toBe(500.0);
});

test('permite sustitucion total (2 de 2 coca-colas)', function () {
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo,
        2 // Cambiar las 2 coca-colas
    );

    expect($result->isAllowed())->toBeTrue();
    expect($result->totalExtraCharge)->toBe(1000.0); // 500 * 2
});

test('regla de sucursal sobrescribe regla de empresa', function () {
    // Crear regla específica de sucursal más restrictiva
    MenuItemReplacementRule::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id, // Específica de sucursal
        'menu_item_id' => $this->menuItem->id,
        'target_product_id' => $this->cocaCola->id,
        'rule_type' => 'allowed_product',
        'allowed_product_id' => $this->agua->id, // Solo permite agua
        'max_price_delta' => null,
        'requires_authorization' => true, // Requiere autorización
        'priority' => 1,
    ]);

    // Intentar sustituir por jugo (permitido por regla de empresa, pero no por sucursal)
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo,
        1
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('replacement_not_allowed_by_rules');

    // Intentar sustituir por agua (permitido por regla de sucursal)
    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->agua,
        1
    );

    expect($result->isAllowed())->toBeTrue();
    expect($result->needsAuthorization())->toBeTrue();
});

test('requiere autorizacion cuando la regla lo indica', function () {
    // Crear regla que requiere autorización
    MenuItemReplacementRule::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'menu_item_id' => $this->menuItem->id,
        'target_product_id' => $this->cocaCola->id,
        'rule_type' => 'any_product',
        'requires_authorization' => true,
        'priority' => 1,
    ]);

    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo,
        1
    );

    expect($result->isAllowed())->toBeTrue();
    expect($result->needsAuthorization())->toBeTrue();
});

test('deniega sustitución si la categoría de la regla fue desactivada', function () {
    // Desactivar la categoría de bebidas
    $this->bebidasCategory->is_active = false;
    $this->bebidasCategory->save();

    // Recargar el validator con la categoría actualizada
    $this->menuItem->refresh();

    $result = $this->validator->execute(
        $this->menuItem,
        $this->cocaCola,
        $this->jugo, // Jugo es de la categoría bebidas (ahora desactivada)
        1
    );

    expect($result->isAllowed())->toBeFalse();
    expect($result->errorCode)->toBe('category_no_longer_available');
});
