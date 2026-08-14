<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\Services\DteSendingService;
use Modules\Fiscal\Domain\Services\SiiWebServiceClient;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    $this->waiter = User::create([
        'name' => 'Test Waiter',
        'email' => 'waiter-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'role' => 'waiter',
    ]);
});

// ============================================
// SiiWebServiceClient
// ============================================

test('SiiWebServiceClient sendDte retorna track ID', function () {
    $certificate = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Test Cert',
        'serial_number' => 'ABC123',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    $client = new SiiWebServiceClient();
    $trackId = $client->sendDte('<DTE>...</DTE>', $certificate, 'certification');

    expect($trackId)->toBeInt();
    expect($trackId)->toBeGreaterThanOrEqual(100000000);
});

test('SiiWebServiceClient queryStatus retorna accepted', function () {
    $client = new SiiWebServiceClient();
    $status = $client->queryStatus(123456789, 'certification');

    expect($status['status'])->toBe('accepted');
    expect($status['description'])->toContain('aceptado');
    expect($status['timbre'])->toContain('<TED>');
});

// ============================================
// DteSendingService
// ============================================

test('DteSendingService send envía DTE y lo marca como aceptado', function () {
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
    $certificate = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Test Cert',
        'serial_number' => 'ABC123',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    // Crear DTE pendiente
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
        'sent_xml' => '<DTE>...</DTE>',
    ]);

    $service = new DteSendingService();
    $result = $service->send($dte, $certificate, 'certification');

    expect($result)->toBeTrue();

    $dte->refresh();
    expect($dte->sii_status)->toBe(DteStatus::ACCEPTED);
    expect($dte->track_id)->not->toBeNull();
    expect($dte->sent_at)->not->toBeNull();
    expect($dte->accepted_at)->not->toBeNull();
});

test('DteSendingService send rechaza si estado no permite envío', function () {
    $certificate = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Test Cert',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1001,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::ACCEPTED, // Ya aceptado, no puede reenviarse
        'issue_date' => now()->toDateString(),
        'sent_xml' => '<DTE>...</DTE>',
    ]);

    $service = new DteSendingService();
    $result = $service->send($dte, $certificate);

    expect($result)->toBeFalse();
});

// ============================================
// IssueDteOnOrderPaid Listener
// ============================================

test('IssueDteOnOrderPaid emite DTE automáticamente al pagar pedido', function () {
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
        'order_number' => 'ORD-AUTO-DTE',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 24000,
        'tax_amount' => 4560,
        'total' => 28560,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_id' => $product->id,
        'name_snapshot' => 'Carne Mongoliana',
        'quantity' => 2,
        'unit_price_snapshot' => 12000,
        'subtotal' => 24000,
    ]);

    // Verificar que no hay DTEs antes
    expect(DteDocument::count())->toBe(0);

    // Disparar evento OrderPaid (llamar listener directamente)
    $listener = app(\Modules\Fiscal\Domain\Listeners\IssueDteOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    // Verificar que se creó un DTE
    expect(DteDocument::count())->toBe(1);

    $dte = DteDocument::first();
    expect($dte->dte_type)->toBe(DteType::BOLETA_AFECTA);
    expect($dte->folio)->toBe(1001);
    expect($dte->order_id)->toBe($order->id);
    expect($dte->total_amount)->toBe('28560.00');
    expect($dte->sent_xml)->toContain('<DTE version="1.0"');
    expect($dte->sent_xml)->toContain('Carne Mongoliana');
    expect($dte->sii_status)->toBe(DteStatus::ACCEPTED);
    expect($dte->track_id)->not->toBeNull();
});

test('IssueDteOnOrderPaid no emite si no hay certificado', function () {
    // Crear rango de folios pero NO certificado
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

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-NO-CERT',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $listener = app(\Modules\Fiscal\Domain\Listeners\IssueDteOnOrderPaid::class);
    $listener->handle(new OrderPaid($order));

    // No debe crear DTE porque no hay certificado
    expect(DteDocument::count())->toBe(0);
});
