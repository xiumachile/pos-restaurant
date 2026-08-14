<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Entities\OrderItem;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException;
use Modules\Fiscal\Domain\Services\DteIssuingService;
use Modules\Fiscal\Domain\Services\DteXmlGenerator;
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
// DteXmlGenerator
// ============================================

test('DteXmlGenerator genera XML con estructura correcta', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1042,
        'net_amount' => 12000,
        'tax_amount' => 2280,
        'total_amount' => 14280,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => '2026-08-14',
    ]);

    $generator = new DteXmlGenerator();
    $xml = $generator->generateDocumentXml(
        $dte,
        [['name' => 'Carne Mongoliana', 'qty' => 2, 'unit_price' => 6000, 'amount' => 12000]],
        '76.123.456-7',
        'Restaurant Test SpA',
        1
    );

    // Verificar estructura básica
    expect($xml)->toContain('<?xml version="1.0"');
    expect($xml)->toContain('<DTE version="1.0"');
    expect($xml)->toContain('<Documento ID="F1042T39">');
    expect($xml)->toContain('<TipoDTE>39</TipoDTE>');
    expect($xml)->toContain('<Folio>1042</Folio>');
    expect($xml)->toContain('<FchEmis>2026-08-14</FchEmis>');
    expect($xml)->toContain('<RUTEmisor>76123456-7</RUTEmisor>');
    expect($xml)->toContain('<MntNeto>12000</MntNeto>');
    expect($xml)->toContain('<IVA>2280</IVA>');
    expect($xml)->toContain('<MntTotal>14280</MntTotal>');
    expect($xml)->toContain('Carne Mongoliana');
});

test('DteXmlGenerator genera TED con datos del documento', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1042,
        'net_amount' => 12000,
        'tax_amount' => 2280,
        'total_amount' => 14280,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => '2026-08-14',
    ]);

    $generator = new DteXmlGenerator();
    $ted = $generator->generateTimbre($dte, '76.123.456-7', 'Restaurant Test');

    expect($ted)->toContain('<TED version="1.0">');
    expect($ted)->toContain('<RE>76123456-7</RE>');
    expect($ted)->toContain('<TD>39</TD>');
    expect($ted)->toContain('<F>1042</F>');
    expect($ted)->toContain('<FE>2026-08-14</FE>');
    expect($ted)->toContain('<MNT>14280</MNT>');
    expect($ted)->toContain('<FRMT');
});

test('DteXmlGenerator limpia RUT correctamente', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::FACTURA_ELECTRONICA,
        'folio' => 100,
        'receiver_rut' => '99.888.777-K',
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => '2026-08-14',
    ]);

    $generator = new DteXmlGenerator();
    $xml = $generator->generateDocumentXml($dte, [], '76.123.456-7', 'Test');

    expect($xml)->toContain('<RUTEmisor>76123456-7</RUTEmisor>');
    expect($xml)->toContain('<RUTRecep>99888777-K</RUTRecep>');
});

test('DteXmlGenerator genera XML firmado con TmstFirma', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => '2026-08-14',
    ]);

    $generator = new DteXmlGenerator();
    $xml = $generator->generateSignedXml(
        $dte, [], '76.123.456-7', 'Test', 0, null
    );

    expect($xml)->toContain('<TED version="1.0">');
    expect($xml)->toContain('<TmstFirma>');
    expect($xml)->toContain('</DTE>');
});

// ============================================
// DteIssuingService
// ============================================

test('issueForOrder emite boleta para pedido sin RUT', function () {
    // Crear categoría y producto válidos
    $category = \Modules\Catalog\Domain\Entities\Category::create([
        'company_id' => $this->company->id,
        'name_translations' => ['es' => 'Platos Fuertes'],
        'sort_order' => 1,
    ]);

    $product = \Modules\Catalog\Domain\Entities\Product::create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name_translations' => ['es' => 'Carne Mongoliana'],
        'base_price' => 6000,
        'is_active' => true,
    ]);

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

    // Crear pedido pagado
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-DTE-1',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
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
        'quantity' => 2,
        'unit_price_snapshot' => 6000,
        'subtotal' => 12000,
    ]);

    $service = new DteIssuingService();
    $dte = $service->issueForOrder($order);

    expect($dte)->toBeInstanceOf(DteDocument::class);
    expect($dte->dte_type)->toBe(DteType::BOLETA_AFECTA);
    expect($dte->folio)->toBe(1001); // Primer folio consumido
    expect($dte->identifier())->toBe('T39F1001');
    expect($dte->total_amount)->toBe('14280.00');
    expect($dte->sent_xml)->toContain('<DTE version="1.0"');
    expect($dte->sent_xml)->toContain('Carne Mongoliana');
});

test('issueForOrder lanza excepción cuando no hay folios', function () {
    // Crear rango de folios agotado
    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 1001,
        'folio_current' => 1001, // Agotado
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-DTE-2',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 1000,
        'tax_amount' => 190,
        'total' => 1190,
    ]);

    $service = new DteIssuingService();

    expect(fn() => $service->issueForOrder($order))
        ->toThrow(NoFoliosAvailableException::class);
});

test('issueForOrder consume folios secuencialmente', function () {
    // Crear rango de folios
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1000,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    $service = new DteIssuingService();

    // Emitir 3 DTEs
    for ($i = 1; $i <= 3; $i++) {
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-DTE-SEQ-' . $i,
            'type' => 'dine_in',
            'status' => OrderStatus::PAID,
            'waiter_id' => $this->waiter->id,
            'subtotal' => 1000,
            'tax_amount' => 190,
            'total' => 1190,
        ]);

        $dte = $service->issueForOrder($order);
        expect($dte->folio)->toBe(1000 + $i);
    }

    // Verificar rango actualizado
    $range->refresh();
    expect($range->folio_current)->toBe(1003);
    expect($range->availableFolios())->toBe(997);
});

test('issueCancellationNote emite NC y anula DTE original', function () {
    // Crear rangos para boletas y NC
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

    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::NOTA_CREDITO,
        'folio_initial' => 501,
        'folio_final' => 1000,
        'folio_current' => 500,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    // Emitir boleta original
    $order = Order::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'order_number' => 'ORD-DTE-NC',
        'type' => 'dine_in',
        'status' => OrderStatus::PAID,
        'waiter_id' => $this->waiter->id,
        'subtotal' => 10000,
        'tax_amount' => 1900,
        'total' => 11900,
    ]);

    $service = new DteIssuingService();
    $originalDte = $service->issueForOrder($order);
    $originalDte->markAsAccepted('<TED>...</TED>');

    expect($originalDte->sii_status)->toBe(DteStatus::ACCEPTED);

    // Emitir NC
    $nc = $service->issueCancellationNote($originalDte, 'Error de emisión');

    expect($nc->dte_type)->toBe(DteType::NOTA_CREDITO);
    expect($nc->folio)->toBe(501);
    expect($nc->referenced_dte_id)->toBe($originalDte->id);
    expect($nc->total_amount)->toBe($originalDte->total_amount);
    expect($nc->sent_xml)->toContain('Anulación: Error de emisión');

    // Original queda anulado
    $originalDte->refresh();
    expect($originalDte->sii_status)->toBe(DteStatus::CANCELLED);
});

test('issueCancellationNote rechaza si DTE no puede anularse', function () {
    // Crear rango NC
    DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::NOTA_CREDITO,
        'folio_initial' => 501,
        'folio_final' => 1000,
        'folio_current' => 500,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    // DTE en estado PENDING (no aceptado aún)
    $pendingDte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    $service = new DteIssuingService();

    expect(fn() => $service->issueCancellationNote($pendingDte, 'Test'))
        ->toThrow(RuntimeException::class);
});
