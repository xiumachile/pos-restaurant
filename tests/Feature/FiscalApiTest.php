<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Traits\InjectsIdempotencyKey;

uses(RefreshDatabase::class, InjectsIdempotencyKey::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Restaurant Test SpA',
        'trade_name' => 'Restaurant Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CASA',
        'name' => 'Casa Matriz',
    ]);

    $this->manager = User::create([
        'name' => 'Test Manager',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
});

function fiscalHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . test()->token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

// ============================================
// GET /api/v1/fiscal/dtes - Listar DTEs
// ============================================

test('GET /api/v1/fiscal/dtes lista DTEs de la sucursal', function () {
    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1002,
        'net_amount' => 2000,
        'tax_amount' => 380,
        'total_amount' => 2380,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/dtes');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/v1/fiscal/dtes filtra por tipo y estado', function () {
    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::FACTURA_ELECTRONICA,
        'folio' => 100,
        'net_amount' => 5000,
        'tax_amount' => 950,
        'total_amount' => 5950,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/dtes?dte_type=39&status=accepted');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dte_type', 39);
});

// ============================================
// POST /api/v1/fiscal/dtes - Emitir DTE manualmente
// ============================================

test('POST /api/v1/fiscal/dtes emite boleta para pedido pagado', function () {
    // Crear rango de folios
    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1000,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    // Crear certificado
    DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Test Cert',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    // Crear categoría y producto
    $category = Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos'],
        'sort_order' => 1,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 12000,
        'is_active' => true,
    ]);

    // Crear pedido pagado
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-MANUAL-DTE',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->manager->id,
        'subtotal' => 12000,
        'tax_amount' => 2280,
        'total' => 14280,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 1,
        'unit_price_snapshot' => 12000,
        'subtotal' => 12000,
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.dte_type', 39)
        ->assertJsonPath('data.folio', 1001)
        ->assertJsonPath('data.identifier', 'T39F1001');
});

test('POST /api/v1/fiscal/dtes rechaza pedido no pagado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-UNPAID',
        'type' => 'dine_in',
        'status' => OrderStatus::DRAFT,
        'waiter_id' => $this->manager->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
        ]);

    $response->assertStatus(422);
});

// ============================================
// GET /api/v1/fiscal/folios - Listar Folios
// ============================================

test('GET /api/v1/fiscal/folios lista rangos de folios', function () {
    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1050,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/folios');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dte_type', 39)
        ->assertJsonPath('data.0.total_folios', 1000)
        ->assertJsonPath('data.0.available_folios', 950);
});

test('POST /api/v1/fiscal/folios carga nuevo CAF', function () {
    $response = $this->withHeaders(fiscalHeaders())
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 39,
            'folio_initial' => 2001,
            'folio_final' => 3000,
            'caf_xml' => '<CAF>Nuevo</CAF>',
            'authorization_date' => now()->toDateString(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.dte_type', 39)
        ->assertJsonPath('data.folio_initial', 2001)
        ->assertJsonPath('data.folio_final', 3000)
        ->assertJsonPath('data.available_folios', 1000);
});

test('GET /api/v1/fiscal/folios/summary retorna resumen por tipo', function () {
    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1050,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/folios/summary');

    $response->assertOk()
        // El endpoint retorna TODOS los tipos de DTE (orden del enum),
        // verificamos que el tipo 39 (Boleta Afecta) esté presente con sus folios
        ->assertJsonFragment([
            'dte_type' => 39,
            'total_available' => 950,
            'ranges_count' => 1,
        ]);
});

// ============================================
// GET /api/v1/fiscal/certificates
// ============================================

test('GET /api/v1/fiscal/certificates lista certificados', function () {
    DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Certificado Test',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/certificates');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Certificado Test')
        ->assertJsonPath('data.0.is_valid', true);
});

// ============================================
// GET /api/v1/fiscal/sales-book - Libro de Ventas
// ============================================

test('GET /api/v1/fiscal/sales-book retorna resumen del período', function () {
    // Crear DTEs aceptados
    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 10000,
        'tax_amount' => 1900,
        'total_amount' => 11900,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1002,
        'net_amount' => 20000,
        'tax_amount' => 3800,
        'total_amount' => 23800,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->getJson('/api/v1/fiscal/sales-book');

    $response->assertOk()
        ->assertJsonPath('data.total_documents', 2)
        ->assertJsonPath('data.total_net', 30000)
        ->assertJsonPath('data.total_tax', 5700)
        ->assertJsonPath('data.total_amount', 35700)
        ->assertJsonPath('data.by_type.0.dte_type', 39)
        ->assertJsonPath('data.by_type.0.documents_count', 2);
});

test('GET /api/v1/fiscal/sales-book/csv descarga CSV', function () {
    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 10000,
        'tax_amount' => 1900,
        'total_amount' => 11900,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(fiscalHeaders())
        ->get('/api/v1/fiscal/sales-book/csv');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition');
});

// ============================================
// Autorización
// ============================================

test('sin autenticacion retorna 401', function () {
    $response = $this->getJson('/api/v1/fiscal/dtes');
    $response->assertStatus(401);
});
