<?php

use Modules\Companies\Domain\Entities\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Company tiene configuración de moneda por defecto (CLP)', function () {
    $company = Company::create([
        'tax_id' => 'CURR-' . uniqid(),
        'legal_name' => 'Currency Test Company',
        'trade_name' => 'Currency Test',
    ]);

    $config = $company->getCurrencyConfig();

    expect($config)->toBeArray()
        ->toHaveKeys(['code', 'symbol', 'decimals', 'thousands_separator', 'decimal_separator']);

    // Defaults de Chile
    expect($config['code'])->toBe('CLP');
    expect($config['symbol'])->toBe('$');
    expect($config['decimals'])->toBe(0);
    expect($config['thousands_separator'])->toBe('.');
    expect($config['decimal_separator'])->toBe(',');
});

test('Company puede actualizar configuración de moneda', function () {
    $company = Company::create([
        'tax_id' => 'CURR-' . uniqid(),
        'legal_name' => 'Currency Update Test',
        'trade_name' => 'Currency Update',
    ]);

    // Cambiar a configuración de USD
    $company->setCurrencyConfig([
        'code' => 'USD',
        'symbol' => 'US$',
        'decimals' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
    ]);

    $company->refresh();
    $config = $company->getCurrencyConfig();

    expect($config['code'])->toBe('USD');
    expect($config['symbol'])->toBe('US$');
    expect($config['decimals'])->toBe(2);
    expect($config['thousands_separator'])->toBe(',');
    expect($config['decimal_separator'])->toBe('.');
});

test('getCurrencyConfig retorna defaults si settings está vacío', function () {
    $company = Company::create([
        'tax_id' => 'CURR-' . uniqid(),
        'legal_name' => 'Empty Settings Test',
        'trade_name' => 'Empty Settings',
        'settings' => [],
    ]);

    $config = $company->getCurrencyConfig();

    expect($config['code'])->toBe('CLP');
    expect($config['decimals'])->toBe(0);
});

test('setCurrencyConfig hace merge con valores existentes', function () {
    $company = Company::create([
        'tax_id' => 'CURR-' . uniqid(),
        'legal_name' => 'Merge Test',
        'trade_name' => 'Merge Test',
    ]);

    // Cambiar solo el código y decimales
    $company->setCurrencyConfig([
        'code' => 'EUR',
        'decimals' => 2,
    ]);

    $company->refresh();
    $config = $company->getCurrencyConfig();

    // Valores actualizados
    expect($config['code'])->toBe('EUR');
    expect($config['decimals'])->toBe(2);

    // Valores preservados (defaults de CLP)
    expect($config['symbol'])->toBe('$');
    expect($config['thousands_separator'])->toBe('.');
    expect($config['decimal_separator'])->toBe(',');
});
