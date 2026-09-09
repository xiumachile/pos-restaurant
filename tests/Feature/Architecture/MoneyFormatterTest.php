<?php

use App\Shared\Domain\Services\MoneyFormatter;
use Modules\Companies\Domain\Entities\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->formatter = new MoneyFormatter();
});

// ============================================
// FORMAT TESTS - CLP (Chile, 0 decimales)
// ============================================

test('format formatea CLP sin decimales', function () {
    $company = Company::create([
        'tax_id' => 'CLP-' . uniqid(),
        'legal_name' => 'CLP Test',
        'trade_name' => 'CLP Test',
        'settings' => [
            'currency' => [
                'code' => 'CLP',
                'symbol' => '$',
                'decimals' => 0,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
            ],
        ],
    ]);

    expect($this->formatter->format(12990, $company))->toBe('12.990');
    expect($this->formatter->format(1234567, $company))->toBe('1.234.567');
    expect($this->formatter->format(0, $company))->toBe('0');
});

test('format maneja montos pequeños en CLP', function () {
    $company = Company::create([
        'tax_id' => 'CLP-' . uniqid(),
        'legal_name' => 'CLP Small',
        'trade_name' => 'CLP Small',
    ]);

    expect($this->formatter->format(500, $company))->toBe('500');
    expect($this->formatter->format(1000, $company))->toBe('1.000');
});

// ============================================
// FORMAT TESTS - USD (USA, 2 decimales)
// ============================================

test('format formatea USD con 2 decimales', function () {
    $company = Company::create([
        'tax_id' => 'USD-' . uniqid(),
        'legal_name' => 'USD Test',
        'trade_name' => 'USD Test',
        'settings' => [
            'currency' => [
                'code' => 'USD',
                'symbol' => 'US$',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
            ],
        ],
    ]);

    expect($this->formatter->format(12.99, $company))->toBe('12.99');
    expect($this->formatter->format(1234.56, $company))->toBe('1,234.56');
    expect($this->formatter->format(0, $company))->toBe('0.00');
});

// ============================================
// FORMAT TESTS - EUR (Europa, 2 decimales con coma)
// ============================================

test('format formatea EUR con coma decimal', function () {
    $company = Company::create([
        'tax_id' => 'EUR-' . uniqid(),
        'legal_name' => 'EUR Test',
        'trade_name' => 'EUR Test',
        'settings' => [
            'currency' => [
                'code' => 'EUR',
                'symbol' => '€',
                'decimals' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
            ],
        ],
    ]);

    expect($this->formatter->format(12.99, $company))->toBe('12,99');
    expect($this->formatter->format(1234.56, $company))->toBe('1.234,56');
});

// ============================================
// FORMAT WITH SYMBOL TESTS
// ============================================

test('formatWithSymbol agrega símbolo de moneda', function () {
    $company = Company::create([
        'tax_id' => 'SYM-' . uniqid(),
        'legal_name' => 'Symbol Test',
        'trade_name' => 'Symbol Test',
    ]);

    expect($this->formatter->formatWithSymbol(12990, $company))->toBe('$ 12.990');
});

test('formatWithSymbol con símbolo largo', function () {
    $company = Company::create([
        'tax_id' => 'SYM-' . uniqid(),
        'legal_name' => 'Long Symbol',
        'trade_name' => 'Long Symbol',
        'settings' => [
            'currency' => [
                'code' => 'USD',
                'symbol' => 'US$',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
            ],
        ],
    ]);

    expect($this->formatter->formatWithSymbol(12.99, $company))->toBe('US$ 12.99');
});

// ============================================
// PARSE TESTS (inverso de format)
// ============================================

test('parse convierte string formateado a número', function () {
    $company = Company::create([
        'tax_id' => 'PARSE-' . uniqid(),
        'legal_name' => 'Parse Test',
        'trade_name' => 'Parse Test',
    ]);

    expect($this->formatter->parse('12.990', $company))->toBe(12990.0);
    expect($this->formatter->parse('1.234.567', $company))->toBe(1234567.0);
});

test('parse maneja string con símbolo', function () {
    $company = Company::create([
        'tax_id' => 'PARSE-' . uniqid(),
        'legal_name' => 'Parse Symbol',
        'trade_name' => 'Parse Symbol',
    ]);

    expect($this->formatter->parse('$ 12.990', $company))->toBe(12990.0);
    expect($this->formatter->parse('$12.990', $company))->toBe(12990.0);
});

test('parse maneja diferentes configuraciones', function () {
    $company = Company::create([
        'tax_id' => 'PARSE-' . uniqid(),
        'legal_name' => 'Parse USD',
        'trade_name' => 'Parse USD',
        'settings' => [
            'currency' => [
                'code' => 'USD',
                'symbol' => 'US$',
                'decimals' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
            ],
        ],
    ]);

    expect($this->formatter->parse('1,234.56', $company))->toBe(1234.56);
    expect($this->formatter->parse('US$ 12.99', $company))->toBe(12.99);
});

// ============================================
// DEFAULTS TESTS
// ============================================

test('defaults retorna configuración de Chile', function () {
    $defaults = MoneyFormatter::defaults();

    expect($defaults)->toBeArray()
        ->toHaveKeys(['code', 'symbol', 'decimals', 'thousands_separator', 'decimal_separator']);

    expect($defaults['code'])->toBe('CLP');
    expect($defaults['decimals'])->toBe(0);
});

test('format usa defaults si settings está vacío', function () {
    $company = Company::create([
        'tax_id' => 'DEF-' . uniqid(),
        'legal_name' => 'Defaults Test',
        'trade_name' => 'Defaults Test',
        'settings' => [],
    ]);

    expect($this->formatter->format(12990, $company))->toBe('12.990');
});

// ============================================
// EDGE CASES
// ============================================

test('format maneja montos negativos', function () {
    $company = Company::create([
        'tax_id' => 'NEG-' . uniqid(),
        'legal_name' => 'Negative Test',
        'trade_name' => 'Negative Test',
    ]);

    expect($this->formatter->format(-12990, $company))->toBe('-12.990');
});

test('format maneja montos muy grandes', function () {
    $company = Company::create([
        'tax_id' => 'BIG-' . uniqid(),
        'legal_name' => 'Big Amount',
        'trade_name' => 'Big Amount',
    ]);

    expect($this->formatter->format(999999999, $company))->toBe('999.999.999');
});

test('format acepta int y float', function () {
    $company = Company::create([
        'tax_id' => 'TYPE-' . uniqid(),
        'legal_name' => 'Type Test',
        'trade_name' => 'Type Test',
    ]);

    expect($this->formatter->format(12990, $company))->toBe('12.990');
    expect($this->formatter->format(12990.0, $company))->toBe('12.990');
});
