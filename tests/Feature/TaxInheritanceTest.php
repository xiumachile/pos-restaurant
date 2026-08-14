<?php

use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Domain\ValueObjects\TaxType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Usar forceCreate para evitar problemas con $fillable
    $this->company = Company::forceCreate([
        'tax_id' => '76.123.456-7',
        'legal_name' => 'Tax Test SpA',
        'trade_name' => 'Tax Test',
    ]);

    $this->branch = Branch::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'TAX',
        'name' => 'Tax Test Branch',
    ]);

    // Crear IVA 19% como default
    $this->iva19 = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'IVA 19%',
        'code' => 'IVA',
        'type' => TaxType::PERCENT,
        'rate' => 19.00,
        'is_default' => true,
        'is_active' => true,
    ]);

    // Crear impuesto exento
    $this->exento = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Exento',
        'code' => 'EXENTO',
        'type' => TaxType::EXEMPT,
        'rate' => 0.00,
        'is_default' => false,
        'is_active' => true,
    ]);

    // Crear categoría sin impuesto específico
    $this->categoryDefault = Category::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Categoría Default'],
        'sort_order' => 1,
    ]);

    // Crear categoría con impuesto exento
    $this->categoryExento = Category::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name_translations' => ['es' => 'Categoría Exenta'],
        'sort_order' => 2,
        'tax_id' => $this->exento->id,
    ]);
});

test('product hereda IVA 19% cuando no tiene tax_id ni category.tax_id', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryDefault->id,
        'name_translations' => ['es' => 'Producto Default'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $product->load('tax', 'category.tax');
    $effectiveTax = $product->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($this->iva19->id)
        ->and($effectiveTax->name)->toBe('IVA 19%')
        ->and((float) $effectiveTax->rate)->toBe(19.00);
});

test('product usa su propio tax_id cuando está definido', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryDefault->id,
        'name_translations' => ['es' => 'Producto Exento'],
        'base_price' => 1000,
        'is_active' => true,
        'tax_id' => $this->exento->id,
    ]);

    $product->load('tax');
    $effectiveTax = $product->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($this->exento->id)
        ->and($effectiveTax->name)->toBe('Exento');
});

test('product hereda tax de category cuando product no tiene tax_id', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryExento->id,
        'name_translations' => ['es' => 'Producto en Categoría Exenta'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $product->load('category.tax');
    $effectiveTax = $product->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($this->exento->id)
        ->and($effectiveTax->name)->toBe('Exento');
});

test('product tax_id tiene prioridad sobre category tax_id', function () {
    $fijo = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Impuesto Fijo',
        'code' => 'FIJO',
        'type' => TaxType::FIXED,
        'rate' => 500.00,
        'is_default' => false,
        'is_active' => true,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryExento->id,
        'name_translations' => ['es' => 'Producto con Impuesto Fijo'],
        'base_price' => 1000,
        'is_active' => true,
        'tax_id' => $fijo->id,
    ]);

    $product->load('tax', 'category.tax');
    $effectiveTax = $product->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($fijo->id)
        ->and($effectiveTax->name)->toBe('Impuesto Fijo');
});

test('category hereda IVA 19% cuando no tiene tax_id', function () {
    $this->categoryDefault->load('tax');
    $effectiveTax = $this->categoryDefault->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($this->iva19->id)
        ->and($effectiveTax->name)->toBe('IVA 19%');
});

test('category usa su propio tax_id cuando está definido', function () {
    $this->categoryExento->load('tax');
    $effectiveTax = $this->categoryExento->getEffectiveTax();
    
    expect($effectiveTax)->not->toBeNull()
        ->and($effectiveTax->id)->toBe($this->exento->id)
        ->and($effectiveTax->name)->toBe('Exento');
});

test('product calculateTax calcula correctamente IVA 19%', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryDefault->id,
        'name_translations' => ['es' => 'Producto con IVA'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $product->load('tax', 'category.tax');
    $tax = $product->calculateTax(2);
    
    expect($tax)->toBe(380.00);
});

test('product calculateTax retorna 0 para productos exentos', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryExento->id,
        'name_translations' => ['es' => 'Producto Exento'],
        'base_price' => 1000,
        'is_active' => true,
    ]);

    $product->load('category.tax');
    $tax = $product->calculateTax(5);
    
    expect($tax)->toBe(0.00);
});

test('product calculateTax calcula correctamente impuesto fijo', function () {
    $fijo = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'Impuesto Fijo $500',
        'code' => 'FIJO500',
        'type' => TaxType::FIXED,
        'rate' => 500.00,
        'is_default' => false,
        'is_active' => true,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->categoryDefault->id,
        'name_translations' => ['es' => 'Producto con Impuesto Fijo'],
        'base_price' => 1000,
        'is_active' => true,
        'tax_id' => $fijo->id,
    ]);

    $product->load('tax');
    $tax = $product->calculateTax(3);
    
    expect($tax)->toBe(1500.00);
});
