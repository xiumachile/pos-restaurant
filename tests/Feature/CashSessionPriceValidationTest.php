<?php

/**
 * P1-005: Validación de montos enteros en apertura/cierre de caja.
 * 
 * Contrato ADR-018: "Money 100% INTEGER"
 * 
 * Este test valida que los FormRequests de caja usan la regla 'integer' 
 * (no 'numeric') y que el servicio calcula diferencias como enteros,
 * evitando truncamientos silenciosos o pérdida de precisión.
 */

test('OpenCashSessionRequest usa regla integer para opening_amount', function () {
    $content = file_get_contents(app_path('Modules/Payments/Interfaces/Requests/OpenCashSessionRequest.php'));
    
    preg_match("/'opening_amount'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches)->not->toBeEmpty('opening_amount rule not found');
    
    $ruleContent = $matches[1];
    expect($ruleContent)->toContain("'integer'")
        ->and($ruleContent)->not->toContain("'numeric'")
        ->and($ruleContent)->toContain("'min:0'");
});

test('CloseCashSessionRequest usa regla integer para closing_amount', function () {
    $content = file_get_contents(app_path('Modules/Payments/Interfaces/Requests/CloseCashSessionRequest.php'));
    
    preg_match("/'closing_amount'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches)->not->toBeEmpty('closing_amount rule not found');
    
    $ruleContent = $matches[1];
    expect($ruleContent)->toContain("'integer'")
        ->and($ruleContent)->not->toContain("'numeric'")
        ->and($ruleContent)->toContain("'min:0'");
});

test('Validación integer rechaza opening_amount decimal (10000.50 → inválido)', function () {
    $validator = validator(
        ['opening_amount' => 10000.50],
        ['opening_amount' => 'required|integer|min:0']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('opening_amount'))->toBeTrue();
});

test('Validación integer acepta opening_amount entero (10000 → válido)', function () {
    $validator = validator(
        ['opening_amount' => 10000],
        ['opening_amount' => 'required|integer|min:0']
    );
    
    expect($validator->passes())->toBeTrue();
});

test('Validación integer rechaza closing_amount decimal (10000.50 → inválido)', function () {
    $validator = validator(
        ['closing_amount' => 10000.50],
        ['closing_amount' => 'required|integer|min:0']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('closing_amount'))->toBeTrue();
});

test('Validación integer acepta closing_amount entero (10000 → válido)', function () {
    $validator = validator(
        ['closing_amount' => 10000],
        ['closing_amount' => 'required|integer|min:0']
    );
    
    expect($validator->passes())->toBeTrue();
});

test('CashSessionService calcula difference como integer (sin round)', function () {
    $content = file_get_contents(app_path('Modules/Payments/Domain/Services/CashSessionService.php'));
    
    // Verificar que NO existe round(..., 2) en el cálculo de difference
    expect($content)->not->toContain('round($closingAmount - $expected, 2)')
        ->and($content)->toContain('$difference = $closingAmount - $expected;');
        
    // Verificar que los tipos son int
    expect($content)->toContain('int $openingAmount')
        ->and($content)->toContain('int $closingAmount');
});

test('Diferencia entre enteros siempre es entero (comportamiento PHP)', function () {
    $closing = 10500;
    $expected = 10000;
    $difference = $closing - $expected;
    
    expect($difference)->toBe(500)
        ->and(is_int($difference))->toBeTrue();
});

test('Diferencia negativa entre enteros siempre es entero (comportamiento PHP)', function () {
    $closing = 9500;
    $expected = 10000;
    $difference = $closing - $expected;
    
    expect($difference)->toBe(-500)
        ->and(is_int($difference))->toBeTrue();
});
