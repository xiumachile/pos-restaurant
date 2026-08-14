<?php

use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Restaurant SpA',
        'trade_name' => 'Restaurant Test',
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'code' => 'CASA',
        'name' => 'Casa Matriz',
    ]);
});

// ============================================
// DteType Value Object
// ============================================

test('DteType tiene tipos correctos según SII Chile', function () {
    expect(DteType::BOLETA_AFECTA->value)->toBe(39);
    expect(DteType::BOLETA_EXENTA->value)->toBe(41);
    expect(DteType::FACTURA_ELECTRONICA->value)->toBe(33);
    expect(DteType::NOTA_CREDITO->value)->toBe(61);
});

test('DteType identifica correctamente tipos afectos a IVA', function () {
    expect(DteType::BOLETA_AFECTA->isTaxable())->toBeTrue();
    expect(DteType::FACTURA_ELECTRONICA->isTaxable())->toBeTrue();
    expect(DteType::BOLETA_EXENTA->isTaxable())->toBeFalse();
    expect(DteType::FACTURA_EXENTA->isTaxable())->toBeFalse();
});

test('DteType identifica boletas como documentos de consumidor final', function () {
    expect(DteType::BOLETA_AFECTA->isConsumerDocument())->toBeTrue();
    expect(DteType::BOLETA_EXENTA->isConsumerDocument())->toBeTrue();
    expect(DteType::FACTURA_ELECTRONICA->isConsumerDocument())->toBeFalse();
});

test('DteType tiene tasa de IVA correcta (19% para afectos)', function () {
    expect(DteType::BOLETA_AFECTA->taxRate())->toBe(0.19);
    expect(DteType::BOLETA_EXENTA->taxRate())->toBe(0.0);
});

test('DteType defaultForOrder retorna boleta cuando no hay RUT', function () {
    expect(DteType::defaultForOrder(null))->toBe(DteType::BOLETA_AFECTA);
    expect(DteType::defaultForOrder(null, true))->toBe(DteType::BOLETA_EXENTA);
});

test('DteType defaultForOrder retorna factura cuando hay RUT', function () {
    expect(DteType::defaultForOrder('76.543.210-K'))->toBe(DteType::FACTURA_ELECTRONICA);
    expect(DteType::defaultForOrder('76.543.210-K', true))->toBe(DteType::FACTURA_EXENTA);
});

// ============================================
// DteStatus Value Object
// ============================================

test('DteStatus identifica correctamente estados terminales', function () {
    expect(DteStatus::ACCEPTED->isTerminal())->toBeTrue();
    expect(DteStatus::CANCELLED->isTerminal())->toBeTrue();
    expect(DteStatus::PENDING->isTerminal())->toBeFalse();
    expect(DteStatus::REJECTED->isTerminal())->toBeFalse();
});

test('DteStatus identifica estados que pueden reenviarse', function () {
    expect(DteStatus::PENDING->canBeResent())->toBeTrue();
    expect(DteStatus::ERROR->canBeResent())->toBeTrue();
    expect(DteStatus::REJECTED->canBeResent())->toBeTrue();
    expect(DteStatus::ACCEPTED->canBeResent())->toBeFalse();
});

test('DteStatus identifica estados que pueden anularse', function () {
    expect(DteStatus::ACCEPTED->canBeCancelled())->toBeTrue();
    expect(DteStatus::PENDING->canBeCancelled())->toBeFalse();
    expect(DteStatus::REJECTED->canBeCancelled())->toBeFalse();
});

// ============================================
// DteFolioRange Entity
// ============================================

test('se puede crear un rango de folios', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1000, // Aún no usado
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    expect($range->id)->not->toBeNull();
    expect($range->dte_type)->toBe(DteType::BOLETA_AFECTA);
});

test('totalFolios calcula correctamente', function () {
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

    expect($range->totalFolios())->toBe(1000); // 2000 - 1001 + 1 = 1000
});

test('availableFolios calcula folios restantes', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1050,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    expect($range->availableFolios())->toBe(950); // 2000 - 1050 = 950
});

test('usagePercentage calcula porcentaje de uso', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1,
        'folio_final' => 100,
        'folio_current' => 50,
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    expect($range->usagePercentage())->toBe(50.0);
});

test('consumeFolio retorna siguiente folio y actualiza contador', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 2000,
        'folio_current' => 1000, // No usado aún
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    // Primera vez: debe retornar 1001
    $folio1 = $range->consumeFolio();
    expect($folio1)->toBe(1001);

    // Segunda vez: debe retornar 1002
    $folio2 = $range->consumeFolio();
    expect($folio2)->toBe(1002);

    // Verificar estado actualizado
    $range->refresh();
    expect($range->folio_current)->toBe(1002);
    expect($range->availableFolios())->toBe(998);
});

test('consumeFolio lanza excepción cuando no hay folios', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1001,
        'folio_final' => 1002,
        'folio_current' => 1002, // Ya consumido completamente
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    expect(fn() => $range->consumeFolio())
        ->toThrow(NoFoliosAvailableException::class);
});

test('isRunningLow detecta rangos cerca de agotarse', function () {
    $range = DteFolioRange::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio_initial' => 1,
        'folio_final' => 100,
        'folio_current' => 95, // 95% usado
        'caf_xml' => '<CAF>...</CAF>',
        'authorization_date' => now()->toDateString(),
    ]);

    expect($range->isRunningLow())->toBeTrue();
    expect($range->isExhausted())->toBeFalse();
});

// ============================================
// DteDocument Entity
// ============================================

test('se puede crear un DTE con datos básicos', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1042,
        'net_amount' => 12000.00,
        'tax_amount' => 2280.00,
        'total_amount' => 14280.00,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    expect($dte->id)->not->toBeNull();
    expect($dte->dte_type)->toBe(DteType::BOLETA_AFECTA);
    expect($dte->sii_status)->toBe(DteStatus::PENDING);
});

test('identifier retorna formato correcto T{type}F{folio}', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1042,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    expect($dte->identifier())->toBe('T39F1042');
});

test('formattedFolio retorna folio con 7 dígitos', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 42,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::PENDING,
        'issue_date' => now()->toDateString(),
    ]);

    expect($dte->formattedFolio())->toBe('0000042');
});

test('markAsSent actualiza track ID y estado', function () {
    $dte = DteDocument::create([
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

    $dte->markAsSent(123456789, '<DTE>...</DTE>');

    $dte->refresh();
    expect($dte->track_id)->toBe(123456789);
    expect($dte->sii_status)->toBe(DteStatus::SENT);
    expect($dte->sent_at)->not->toBeNull();
});

test('markAsAccepted actualiza estado y timbre', function () {
    $dte = DteDocument::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'dte_type' => DteType::BOLETA_AFECTA,
        'folio' => 1,
        'net_amount' => 1000,
        'tax_amount' => 190,
        'total_amount' => 1190,
        'sii_status' => DteStatus::SENT,
        'issue_date' => now()->toDateString(),
    ]);

    $dte->markAsAccepted('<TED>...</TED>');

    $dte->refresh();
    expect($dte->sii_status)->toBe(DteStatus::ACCEPTED);
    expect($dte->timbre_xml)->toBe('<TED>...</TED>');
    expect($dte->accepted_at)->not->toBeNull();
});

// ============================================
// DteCertificate Entity
// ============================================

test('se puede crear un certificado digital', function () {
    $cert = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Certificado Casa Matriz',
        'serial_number' => 'ABC123456',
        'issuer' => 'E-Sign S.A.',
        'certificate_content' => 'PKCS12_CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Restaurant SpA',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
        'environment' => DteCertificate::ENV_CERTIFICATION,
    ]);

    expect($cert->id)->not->toBeNull();
    expect($cert->environment)->toBe('certification');
});

test('isValid verifica vigencia del certificado', function () {
    $validCert = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Válido',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addYear(),
    ]);

    $expiredCert = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Vencido',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subYear(),
        'valid_until' => now()->subDay(),
    ]);

    expect($validCert->isValid())->toBeTrue();
    expect($expiredCert->isValid())->toBeFalse();
});

test('isExpiringSoon detecta certificados próximos a vencer', function () {
    $expiringSoon = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Por vencer',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subYear(),
        'valid_until' => now()->addDays(15), // 15 días
    ]);

    $notExpiring = DteCertificate::create([
        'company_id' => $this->company->id,
        'name' => 'Vigente',
        'certificate_content' => 'CONTENT',
        'holder_rut' => '76.123.456-7',
        'holder_name' => 'Test',
        'valid_from' => now()->subYear(),
        'valid_until' => now()->addYear(),
    ]);

    expect($expiringSoon->isExpiringSoon())->toBeTrue();
    expect($notExpiring->isExpiringSoon())->toBeFalse();
});
