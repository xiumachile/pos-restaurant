<?php

use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->company = Company::create([
        'tax_id' => '76.111.222-3',
        'legal_name' => 'DTE Workflow SpA',
        'trade_name' => 'DTE Workflow Restaurant',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'DTE-WF',
        'name' => 'Branch DTE Workflow',
    ]);

    $this->manager = User::create([
        'name' => 'DTE Manager',
        'email' => 'dte-manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'manager',
    ]);

    // Tenant B (para pruebas de aislamiento)
    $this->companyB = Company::create([
        'tax_id' => '76.222.333-4',
        'legal_name' => 'Tenant B SpA',
        'trade_name' => 'Tenant B',
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'DTE-B',
        'name' => 'Branch Tenant B',
    ]);

    $this->managerB = User::create([
        'name' => 'Manager B',
        'email' => 'dte-manager-b-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'role' => 'manager',
    ]);

    $this->token = JWTAuth::fromUser($this->manager);
    $this->tokenB = JWTAuth::fromUser($this->managerB);
});

function dteWorkflowHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer ' . ($token ?? test()->token),
        'Accept' => 'application/json',
    ];
}

function createCertificate(Company $company, string $env = 'certification'): DteCertificate
{
    return DteCertificate::create([
        'company_id' => $company->id,
        'name' => 'Certificado Test',
        'certificate_content' => 'PKCS12_CONTENT_' . uniqid(),
        'holder_rut' => $company->tax_id,
        'holder_name' => $company->trade_name,
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
        'environment' => $env,
        'is_active' => true,
    ]);
}

function createFolioRange(Company $company, Branch $branch, DteType $type, int $initial = 1001, int $final = 2000): DteFolioRange
{
    return DteFolioRange::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'dte_type' => $type,
        'folio_initial' => $initial,
        'folio_final' => $final,
        'folio_current' => $initial - 1,
        'caf_xml' => '<CAF>test_xml_' . uniqid() . '</CAF>',
        'authorization_date' => now()->subDays(30),
        'authorized_rut' => $company->tax_id,
        'is_active' => true,
    ]);
}

function createPaidOrder(Company $company, Branch $branch, User $user, float $total = 1190.0): Order
{
    return Order::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'waiter_id' => $user->id,
        'order_number' => 'DTE-WF-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => $total,
    ]);
}

// ============================================
// POST /api/v1/fiscal/dtes - Emisión manual
// ============================================

test('emite DTE manualmente con RUT genera factura', function () {
    createCertificate($this->company);
    createFolioRange($this->company, $this->branch, DteType::FACTURA_ELECTRONICA);
    
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'receiver_rut' => '76543210-K',
            'receiver_business_name' => 'Empresa Cliente SpA',
            'environment' => 'certification',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.dte_type', 33)
        ->assertJsonPath('data.receiver_rut', '76543210-K')
        ->assertJsonPath('data.receiver_business_name', 'Empresa Cliente SpA');

    // Verificar en DB
    $this->assertDatabaseHas('dte_documents', [
        'company_id' => $this->company->id,
        'dte_type' => 33,
        'receiver_rut' => '76543210-K',
    ]);
});

test('emite DTE sin RUT genera boleta', function () {
    createCertificate($this->company);
    createFolioRange($this->company, $this->branch, DteType::BOLETA_AFECTA);
    
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.dte_type', 39);
});

test('rechaza emisión para pedido no pagado', function () {
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'waiter_id' => $this->manager->id,
        'order_number' => 'DTE-UNPAID-' . uniqid(),
        'type' => 'dine_in',
        'status' => OrderStatus::CONFIRMED,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Solo se pueden emitir DTEs para pedidos pagados.');
});

test('rechaza emisión si pedido ya tiene DTE activo', function () {
    createCertificate($this->company);
    createFolioRange($this->company, $this->branch, DteType::BOLETA_AFECTA);
    
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    // Crear DTE existente para el pedido
    DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'order_id' => $order->id,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(422);
    $data = $response->json();
    expect($data['message'])->toContain('ya tiene un DTE emitido');
});

test('rechaza emisión con RUT inválido', function () {
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'receiver_rut' => 'invalido',
            'receiver_business_name' => 'Empresa',
            'environment' => 'certification',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('receiver_rut');
});

test('rechaza RUT sin razón social', function () {
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'receiver_rut' => '76543210-K',
            'environment' => 'certification',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('receiver_business_name');
});

test('rechaza emisión sin folios disponibles', function () {
    createCertificate($this->company);
    // NO crear rango de folios
    
    $order = createPaidOrder($this->company, $this->branch, $this->manager);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(422);
});

// ============================================
// POST /api/v1/fiscal/dtes/{uuid}/cancel
// ============================================

test('anula DTE aceptado emitiendo Nota de Crédito', function () {
    createCertificate($this->company);
    createFolioRange($this->company, $this->branch, DteType::BOLETA_AFECTA);
    createFolioRange($this->company, $this->branch, DteType::NOTA_CREDITO, 5001, 6000);

    // Crear DTE aceptado
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
        'sent_xml' => '<DTE>original</DTE>',
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dte->uuid}/cancel", [
            'reason' => 'Cliente solicitó anulación',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('original_dte', 'T39F1001');

    // Verificar que se creó la NC
    $this->assertDatabaseHas('dte_documents', [
        'company_id' => $this->company->id,
        'dte_type' => 61,
        'referenced_dte_id' => $dte->id,
    ]);

    // DTE original ahora cancelado
    $dte->refresh();
    expect($dte->sii_status->value)->toBe('cancelled');
});

test('rechaza anulación de DTE no aceptado', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dte->uuid}/cancel", [
            'reason' => 'Test',
        ]);

    $response->assertStatus(422);
});

// ============================================
// POST /api/v1/fiscal/dtes/{uuid}/resend
// ============================================

test('reenvía DTE en estado ERROR', function () {
    createCertificate($this->company);

    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ERROR,
        'issue_date' => now()->toDateString(),
        'sent_xml' => '<DTE>error_dte</DTE>',
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dte->uuid}/resend");

    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('reenvía DTE en estado REJECTED', function () {
    createCertificate($this->company);

    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::REJECTED,
        'issue_date' => now()->toDateString(),
        'sent_xml' => '<DTE>rejected</DTE>',
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dte->uuid}/resend");

    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('rechaza reenvío de DTE en estado ACCEPTED', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
        'sent_xml' => '<DTE>accepted</DTE>',
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dte->uuid}/resend");

    $response->assertStatus(422);
});

// ============================================
// POST /api/v1/fiscal/folios - Carga CAF
// ============================================

test('carga nuevo rango de folios', function () {
    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 39,
            'folio_initial' => 1001,
            'folio_final' => 2000,
            'caf_xml' => '<CAF>authorized_range</CAF>',
            'authorization_date' => now()->subDays(10)->toDateString(),
            'authorized_rut' => '76.111.222-3',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.dte_type', 39)
        ->assertJsonPath('data.folio_initial', 1001)
        ->assertJsonPath('data.folio_final', 2000);

    $this->assertDatabaseHas('dte_folio_ranges', [
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => 39,
        'folio_initial' => 1001,
    ]);
});

test('rechaza CAF con folio final menor al inicial', function () {
    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 39,
            'folio_initial' => 2000,
            'folio_final' => 1000,
            'caf_xml' => '<CAF>invalid</CAF>',
            'authorization_date' => now()->toDateString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('folio_final');
});

test('rechaza CAF con tipo de DTE inválido', function () {
    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 999,
            'folio_initial' => 1001,
            'folio_final' => 2000,
            'caf_xml' => '<CAF>invalid</CAF>',
            'authorization_date' => now()->toDateString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('dte_type');
});

test('rechaza CAF con folio inicial duplicado', function () {
    createFolioRange($this->company, $this->branch, DteType::BOLETA_AFECTA, 1001, 2000);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 39,
            'folio_initial' => 1001,
            'folio_final' => 1500,
            'caf_xml' => '<CAF>duplicate</CAF>',
            'authorization_date' => now()->toDateString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Ya existe un rango de folios con ese folio inicial.']);
});

// ============================================
// POST /api/v1/fiscal/certificates - Upload
// ============================================

test('sube certificado digital .pfx', function () {
    $file = UploadedFile::fake()->create('certificado.pfx', 1024);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/certificates', [
            'name' => 'Certificado Producción',
            'certificate_file' => $file,
            'password' => 'secret123',
            'environment' => 'production',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Certificado Producción')
        ->assertJsonPath('data.environment', 'production')
        ->assertJsonPath('data.is_valid', true);
});

test('rechaza certificado con extensión incorrecta', function () {
    $file = UploadedFile::fake()->create('documento.txt', 1024);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/certificates', [
            'name' => 'Certificado',
            'certificate_file' => $file,
            'password' => 'secret123',
            'environment' => 'certification',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('certificate_file');
});

test('rechaza certificado sin contraseña', function () {
    $file = UploadedFile::fake()->create('cert.pfx', 1024);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson('/api/v1/fiscal/certificates', [
            'name' => 'Certificado',
            'certificate_file' => $file,
            'environment' => 'certification',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

// ============================================
// DELETE /api/v1/fiscal/certificates/{uuid}
// ============================================

test('desactiva certificado sin eliminarlo físicamente', function () {
    $cert = createCertificate($this->company);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->deleteJson("/api/v1/fiscal/certificates/{$cert->uuid}");

    $response->assertOk()
        ->assertJsonPath('message', 'Certificado desactivado correctamente.');

    $cert->refresh();
    expect($cert->is_active)->toBeFalse();
});

test('no puede desactivar certificado de otra empresa', function () {
    $certB = createCertificate($this->companyB);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->deleteJson("/api/v1/fiscal/certificates/{$certB->uuid}");

    $response->assertStatus(404);
});

// ============================================
// Multi-tenant isolation
// ============================================

test('usuario A no ve DTEs de empresa B', function () {
    // Crear DTE en empresa B
    DteDocument::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 5001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->getJson('/api/v1/fiscal/dtes');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('usuario A no ve folios de empresa B', function () {
    createFolioRange($this->companyB, $this->branchB, DteType::BOLETA_AFECTA);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->getJson('/api/v1/fiscal/folios');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('usuario A no ve certificados de empresa B', function () {
    createCertificate($this->companyB);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->getJson('/api/v1/fiscal/certificates');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('usuario A no puede cancelar DTE de empresa B', function () {
    createCertificate($this->companyB);
    createFolioRange($this->companyB, $this->branchB, DteType::NOTA_CREDITO, 5001, 6000);

    $dteB = DteDocument::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 2001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED,
        'issue_date' => now()->toDateString(),
    ]);

    $response = $this->withHeaders(dteWorkflowHeaders($this->token))
        ->postJson("/api/v1/fiscal/dtes/{$dteB->uuid}/cancel", [
            'reason' => 'Intento cross-tenant',
        ]);

    // Debería fallar (404 porque no encuentra el DTE en su tenant)
    $response->assertStatus(404);
});

// ============================================
// Autorización por rol
// ============================================

test('waiter NO puede emitir DTE manualmente', function () {
    $waiter = User::create([
        'name' => 'Waiter Test',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $waiterToken = JWTAuth::fromUser($waiter);
    $order = createPaidOrder($this->company, $this->branch, $waiter);

    $response = $this->withHeaders(dteWorkflowHeaders($waiterToken))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(403);
});

test('waiter NO puede cargar CAF', function () {
    $waiter = User::create([
        'name' => 'Waiter Test',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);

    $waiterToken = JWTAuth::fromUser($waiter);

    $response = $this->withHeaders(dteWorkflowHeaders($waiterToken))
        ->postJson('/api/v1/fiscal/folios', [
            'dte_type' => 39,
            'folio_initial' => 1001,
            'folio_final' => 2000,
            'caf_xml' => '<CAF>test</CAF>',
            'authorization_date' => now()->toDateString(),
        ]);

    $response->assertStatus(403);
});

test('cashier SÍ puede emitir DTE manualmente', function () {
    $cashier = User::create([
        'name' => 'Cashier Test',
        'email' => 'cashier-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
    ]);

    createCertificate($this->company);
    createFolioRange($this->company, $this->branch, DteType::BOLETA_AFECTA);

    $cashierToken = JWTAuth::fromUser($cashier);
    $order = createPaidOrder($this->company, $this->branch, $cashier);

    $response = $this->withHeaders(dteWorkflowHeaders($cashierToken))
        ->postJson('/api/v1/fiscal/dtes', [
            'order_uuid' => $order->uuid,
            'environment' => 'certification',
        ]);

    $response->assertStatus(201);
});

// ============================================
// Validaciones de autenticación
// ============================================

test('sin autenticación retorna 401', function () {
    $this->getJson('/api/v1/fiscal/dtes')->assertStatus(401);
    $this->postJson('/api/v1/fiscal/dtes', [])->assertStatus(401);
    $this->getJson('/api/v1/fiscal/folios')->assertStatus(401);
    $this->postJson('/api/v1/fiscal/folios', [])->assertStatus(401);
    $this->getJson('/api/v1/fiscal/certificates')->assertStatus(401);
    $this->postJson('/api/v1/fiscal/certificates', [])->assertStatus(401);
});
