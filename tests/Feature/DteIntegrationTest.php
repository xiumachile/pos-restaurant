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
use Modules\Fiscal\Domain\Services\DteIssuingService;

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

// ============================================
// Concurrencia en Consumo de Folios
// ============================================

test('consumeFolio evita duplicación con lockForUpdate', function () {
    $company = Company::create([
        'tax_id' => '76.999.888-7',
        'legal_name' => 'Concurrencia Test SpA',
        'trade_name' => 'Concurrencia Test',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'CONC',
        'name' => 'Branch Concurrencia',
    ]);

    // Crear rango con 10 folios disponibles
    $range = DteFolioRange::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 1010,
        'folio_current' => 1000,
        'is_active' => true,
        'caf_xml' => '<CAF>test</CAF>',
        'authorization_date' => now()->subDays(10),
        'authorized_rut' => '76.999.888-7',
    ]);

    $folios = [];
    
    // Simular 5 consumos "simultáneos"
    for ($i = 0; $i < 5; $i++) {
        $freshRange = DteFolioRange::find($range->id);
        $folio = $freshRange->consumeFolio();
        $folios[] = $folio;
    }

    // Validar que todos los folios sean únicos
    expect($folios)->toHaveCount(5);
    expect(array_unique($folios))->toHaveCount(5);
    
    // Validar secuencia correcta
    expect($folios)->toBe([1001, 1002, 1003, 1004, 1005]);
    
    // Validar que el rango se actualizó correctamente
    $range->refresh();
    expect($range->folio_current)->toBe(1005);
    expect($range->is_active)->toBeTrue();
});

test('consumeFolio cierra rango cuando se agotan folios', function () {
    $company = Company::create([
        'tax_id' => '76.888.777-6',
        'legal_name' => 'Agotamiento Test SpA',
        'trade_name' => 'Agotamiento Test',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'AGOT',
        'name' => 'Branch Agotamiento',
    ]);

    // Crear rango con solo 3 folios
    $range = DteFolioRange::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 2001,
        'folio_final' => 2003,
        'folio_current' => 2000,
        'is_active' => true,
        'caf_xml' => '<CAF>test</CAF>',
        'authorization_date' => now()->subDays(5),
        'authorized_rut' => '76.888.777-6',
    ]);

    // Consumir los 3 folios
    $range->consumeFolio(); // 2001
    $range->consumeFolio(); // 2002
    $range->consumeFolio(); // 2003

    $range->refresh();
    expect($range->is_active)->toBeFalse();
    expect($range->closed_at)->not->toBeNull();
    
    // Intentar consumir un 4to folio debe fallar
    expect(fn() => $range->consumeFolio())
        ->toThrow(\Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException::class);
});

test('emisión concurrente de DTEs usa folios únicos', function () {
    $company = Company::create([
        'tax_id' => '76.777.666-5',
        'legal_name' => 'DTE Concurrente SpA',
        'trade_name' => 'DTE Concurrente',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'DTEC',
        'name' => 'Branch DTE Concurrente',
    ]);

    $manager = User::create([
        'name' => 'Manager Concurrente',
        'email' => 'manager-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => 'manager',
    ]);

    // Crear certificado válido
    $certificate = DteCertificate::create([
        'company_id' => $company->id,
        'name' => 'Certificado Concurrente',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.777.666-5',
        'holder_name' => 'DTE Concurrente SpA',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
        'environment' => 'certification',
        'is_active' => true,
    ]);

    // Crear rango con 5 folios
    DteFolioRange::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 3001,
        'folio_final' => 3005,
        'folio_current' => 3000,
        'is_active' => true,
        'caf_xml' => '<CAF>test</CAF>',
        'authorization_date' => now()->subDays(3),
        'authorized_rut' => '76.777.666-5',
    ]);

    // Crear 3 pedidos pagados
    $orders = [];
    for ($i = 0; $i < 3; $i++) {
        $order = Order::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'order_number' => 'ORD-CONC-' . uniqid(),
            'type' => 'dine_in',
            'status' => OrderStatus::PAID,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'total' => 1190,
        ]);
        $orders[] = $order;
    }

    $dtes = [];
    
    // Emitir DTEs para los 3 pedidos
    foreach ($orders as $order) {
        $issuingService = app(DteIssuingService::class);
        $dte = $issuingService->issueForOrder($order, null, null, 'certification');
        $dtes[] = $dte;
    }

    // Validar que cada DTE tenga un folio único
    $folios = array_map(fn($dte) => $dte->folio, $dtes);
    expect($folios)->toHaveCount(3);
    expect(array_unique($folios))->toHaveCount(3);
    
    // Validar secuencia
    expect($folios)->toBe([3001, 3002, 3003]);
    
    // Validar que todos los DTEs estén en estado PENDING o SENT
    foreach ($dtes as $dte) {
        expect($dte->sii_status->value)->toBeIn(['pending', 'sent']);
    }
});
