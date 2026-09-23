<?php

/**
 * P1-004: Validación de precios enteros en FormRequests de productos.
 * 
 * Contrato ADR-018: "Money 100% INTEGER"
 * 
 * Este test valida mediante análisis estático que los FormRequests
 * de productos usan la regla 'integer' (no 'numeric') para base_price,
 * garantizando que valores decimales como 10000.50 sean rechazados
 * en lugar de truncarse silenciosamente a 10000.
 */

test('CreateProductRequest usa regla integer para base_price (no numeric)', function () {
    $content = file_get_contents(app_path('Modules/Catalog/Interfaces/Requests/CreateProductRequest.php'));
    
    // Extraer la línea de base_price
    preg_match("/'base_price'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches)->not->toBeEmpty('base_price rule not found in CreateProductRequest');
    
    $ruleContent = $matches[1];
    
    expect($ruleContent)->toContain("'integer'")
        ->and($ruleContent)->not->toContain("'numeric'")
        ->and($ruleContent)->toContain("'min:0'");
});

test('UpdateProductRequest usa regla integer para base_price (no numeric)', function () {
    $content = file_get_contents(app_path('Modules/Catalog/Interfaces/Requests/UpdateProductRequest.php'));
    
    preg_match("/'base_price'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches)->not->toBeEmpty('base_price rule not found in UpdateProductRequest');
    
    $ruleContent = $matches[1];
    
    expect($ruleContent)->toContain("'integer'")
        ->and($ruleContent)->not->toContain("'numeric'")
        ->and($ruleContent)->toContain("'min:0'");
});

test('CreateProductRequest requiere base_price (required)', function () {
    $content = file_get_contents(app_path('Modules/Catalog/Interfaces/Requests/CreateProductRequest.php'));
    
    preg_match("/'base_price'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches[1])->toContain("'required'");
});

test('UpdateProductRequest permite base_price opcional (sometimes)', function () {
    $content = file_get_contents(app_path('Modules/Catalog/Interfaces/Requests/UpdateProductRequest.php'));
    
    preg_match("/'base_price'\s*=>\s*\[([^\]]+)\]/", $content, $matches);
    expect($matches[1])->toContain("'sometimes'");
});

test('Validación integer rechaza 10000.50 (comportamiento esperado)', function () {
    // Simular la validación que Laravel hace con la regla 'integer'
    $validator = validator(
        ['base_price' => 10000.50],
        ['base_price' => 'required|integer|min:0']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('base_price'))->toBeTrue();
});

test('Validación integer rechaza "10000.50" string (comportamiento esperado)', function () {
    $validator = validator(
        ['base_price' => '10000.50'],
        ['base_price' => 'required|integer|min:0']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('base_price'))->toBeTrue();
});

test('Validación integer acepta 10000 (comportamiento esperado)', function () {
    $validator = validator(
        ['base_price' => 10000],
        ['base_price' => 'required|integer|min:0']
    );
    
    expect($validator->passes())->toBeTrue();
});

test('Validación integer acepta 0 (comportamiento esperado)', function () {
    $validator = validator(
        ['base_price' => 0],
        ['base_price' => 'required|integer|min:0']
    );
    
    expect($validator->passes())->toBeTrue();
});

test('Validación integer rechaza -100 (comportamiento esperado)', function () {
    $validator = validator(
        ['base_price' => -100],
        ['base_price' => 'required|integer|min:0']
    );
    
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('base_price'))->toBeTrue();
});
