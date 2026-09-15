<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Payments\Domain\Entities\Payment;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Application\Services\CatalogExportService;

uses(RefreshDatabase::class);

/**
 * AUDITORÍA DE SEGURIDAD: Tests cross-tenant REALES
 * 
 * Validan que Company A NO puede acceder a datos de Company B
 * incluso cuando consulta directamente sin filtros.
 */
beforeEach(function () {
    // Tenant A
    $this->companyA = Company::create([
        'tax_id' => 'SEC-A-' . uniqid(),
        'legal_name' => 'Security Test A',
        'trade_name' => 'Test A',
    ]);
    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'SECA',
        'name' => 'Branch A',
    ]);
    $this->userA = User::create([
        'name' => 'User A',
        'email' => 'user-a-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'role' => 'cashier',
    ]);

    // Tenant B
    $this->companyB = Company::create([
        'tax_id' => 'SEC-B-' . uniqid(),
        'legal_name' => 'Security Test B',
        'trade_name' => 'Test B',
    ]);
    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'SECB',
        'name' => 'Branch B',
    ]);
    $this->userB = User::create([
        'name' => 'User B',
        'email' => 'user-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'cashier',
    ]);
});

// ═══════════════════════════════════════════════════
// TEST 1: Order aislamiento (debería pasar, Order tiene BelongsToTenant)
// ═══════════════════════════════════════════════════
test('Company A NO puede ver orders de Company B vía Eloquent', function () {
    // Tenant A crea orden
    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'order_number' => 'SEC-A-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->userA->id,
        'subtotal' => 10000,
        'total' => 11900,
    ]);

    // Tenant B crea orden
    $this->actingAs($this->userB, 'api');
    $orderB = Order::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'order_number' => 'SEC-B-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->userB->id,
        'subtotal' => 20000,
        'total' => 23800,
    ]);

    // Tenant B NO debería ver la orden de A
    $this->actingAs($this->userB, 'api');
    $ordersVisible = Order::all();
    
    expect($ordersVisible->count())->toBe(1)
        ->and($ordersVisible->first()->id)->toBe($orderB->id)
        ->and($ordersVisible->contains('id', $orderA->id))->toBeFalse();
});

// ═══════════════════════════════════════════════════
// TEST 2: Product aislamiento (Product tiene BelongsToTenant)
// ═══════════════════════════════════════════════════
test('Company A NO puede ver products de Company B vía Eloquent', function () {
    // Tenant A crea producto
    $productA = Product::create([
        'company_id' => $this->companyA->id,
        'name_translations' => ['es' => 'Producto A'],
        'base_price' => 10000,
        'is_active' => true,
    ]);

    // Tenant B crea producto
    $productB = Product::create([
        'company_id' => $this->companyB->id,
        'name_translations' => ['es' => 'Producto B'],
        'base_price' => 20000,
        'is_active' => true,
    ]);

    // Tenant B NO debería ver producto de A
    $this->actingAs($this->userB, 'api');
    $productsVisible = Product::all();
    
    expect($productsVisible->count())->toBe(1)
        ->and($productsVisible->first()->id)->toBe($productB->id);
});

// ═══════════════════════════════════════════════════
// TEST 3: Category sin scope (riesgo confirmado)
// ═══════════════════════════════════════════════════
test('AUDITORÍA: Category filtra datos de otros tenants', function () {
    $catA = Category::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'name_translations' => ['es' => 'Categoría A'],
    ]);

    $catB = Category::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'name_translations' => ['es' => 'Categoría B'],
    ]);

    // Tenant B NO debería ver categoría de A
    $this->actingAs($this->userB, 'api');
    $categories = Category::all();
    
    // Si falla este test, Category NO filtra por tenant (BUG P0)
    expect($categories->count())->toBe(1, 'Category filtra correctamente por tenant')
        ->and($categories->contains('id', $catA->id))->toBeFalse();
});

// ═══════════════════════════════════════════════════
// TEST 4: Payment aislamiento
// ═══════════════════════════════════════════════════
test('Company A NO puede ver payments de Company B', function () {
    $pmA = PaymentMethod::create([
        'company_id' => $this->companyA->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Cash A'],
        'type' => 'cash',
        'is_active' => true,
    ]);
    $pmB = PaymentMethod::create([
        'company_id' => $this->companyB->id,
        'code' => 'cash',
        'name_translations' => ['es' => 'Cash B'],
        'type' => 'cash',
        'is_active' => true,
    ]);

    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'order_number' => 'SEC-A-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->userA->id,
        'subtotal' => 10000,
        'total' => 11900,
    ]);

    $this->actingAs($this->userB, 'api');
    $orderB = Order::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'order_number' => 'SEC-B-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->userB->id,
        'subtotal' => 20000,
        'total' => 23800,
    ]);

    // Payments de A
    $payA = Payment::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'order_id' => $orderA->id,
        'payment_method_id' => $pmA->id,
        'user_id' => $this->userA->id,
        'payment_number' => 'PAY-A-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 11900,
        'tip_amount' => 0,
        'total_amount' => 11900,
        'status' => 'completed',
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    $payB = Payment::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'order_id' => $orderB->id,
        'payment_method_id' => $pmB->id,
        'user_id' => $this->userB->id,
        'payment_number' => 'PAY-B-' . uniqid(),
        'method_code' => 'cash',
        'amount' => 23800,
        'tip_amount' => 0,
        'total_amount' => 23800,
        'status' => 'completed',
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    $this->actingAs($this->userB, 'api');
    $payments = Payment::all();
    
    expect($payments->count())->toBe(1, 'Payment filtra correctamente')
        ->and($payments->first()->id)->toBe($payB->id)
        ->and($payments->contains('id', $payA->id))->toBeFalse();
});

// ═══════════════════════════════════════════════════
// TEST 5: find() directo con UUID de otro tenant
// ═══════════════════════════════════════════════════
test('find() por UUID no retorna datos de otro tenant', function () {
    $this->actingAs($this->userA, 'api');
    $orderA = Order::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'order_number' => 'SEC-A-' . uniqid(),
        'type' => OrderType::DINE_IN,
        'status' => OrderStatus::CONFIRMED,
        'waiter_id' => $this->userA->id,
        'subtotal' => 10000,
        'total' => 11900,
    ]);

    // Tenant B intenta acceder por UUID directamente
    $this->actingAs($this->userB, 'api');
    $found = Order::where('uuid', $orderA->uuid)->first();
    
    expect($found)->toBeNull('find() no retorna datos cross-tenant');
});

// ═══════════════════════════════════════════════════
// TEST 6: DB::table sin filtro (riesgo real)
// ═══════════════════════════════════════════════════
test('DB::table sin company_id filtra todos los tenants', function () {
    // Crear categorías para ambos tenants
    Category::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'name_translations' => ['es' => 'Cat A'],
    ]);
    Category::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'name_translations' => ['es' => 'Cat B'],
    ]);

    // DB::table NO aplica global scopes (sin protección automática)
    $allCategories = \Illuminate\Support\Facades\DB::table('categories')->count();
    $filteredCategories = \Illuminate\Support\Facades\DB::table('categories')
        ->where('company_id', $this->companyB->id)
        ->count();

    // Esto confirma el riesgo: DB::table ve TODO
    expect($allCategories)->toBe(2, 'DB::table sin filtro ve todos los tenants')
        ->and($filteredCategories)->toBe(1, 'DB::table con filtro ve solo su tenant');
});

// ═══════════════════════════════════════════════════
// TEST 7: CatalogExportService sin filtro (CRÍTICO)
// ═══════════════════════════════════════════════════
test('CatalogExportService no filtra por tenant (P0)', function () {
    // Crear productos para ambos tenants
    Product::create([
        'company_id' => $this->companyA->id,
        'name_translations' => ['es' => 'Producto A'],
        'base_price' => 10000,
        'is_active' => true,
    ]);
    Product::create([
        'company_id' => $this->companyB->id,
        'name_translations' => ['es' => 'Producto B'],
        'base_price' => 20000,
        'is_active' => true,
    ]);

    $exportService = app(CatalogExportService::class);
    
    // Intentar exportar como Tenant B
    $this->actingAs($this->userB, 'api');
    
    try {
        $exported = $exportService->export($this->companyB->id);
        
        // Si el servicio filtra correctamente, solo debe exportar productos de B
        $productsCount = count($exported['products'] ?? []);
        expect($productsCount)->toBe(1, 'Export solo incluye productos del tenant actual');
    } catch (\Exception $e) {
        // Si falla, documentar el error
        $this->fail('CatalogExportService tiene bug cross-tenant: ' . $e->getMessage());
    }
});
