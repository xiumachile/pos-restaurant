<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Tests\TestCase;

class ProductResolvePriceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch1;
    private Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        
        // Desactivar BranchObserver para evitar que cree PriceList/Menu defaults automáticamente
        $this->branch1 = Branch::withoutEvents(function () {
            return Branch::factory()->create(['company_id' => $this->company->id]);
        });
        $this->branch2 = Branch::withoutEvents(function () {
            return Branch::factory()->create(['company_id' => $this->company->id]);
        });
    }

    public function test_resolves_price_from_explicit_price_list(): void
    {
        $priceList = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
        ]);
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'base_price' => 1000,
        ]);

        $product->prices()->create([
            'price_list_id' => $priceList->id,
            'price' => 1500,
            'currency' => 'CLP',
        ]);

        $this->assertEquals(1500, $product->resolvePrice($priceList));
    }

    public function test_falls_back_to_branch_default_not_other_branch(): void
    {
        $defaultList1 = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'is_default' => true,
        ]);

        $defaultList2 = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch2->id,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'base_price' => 1000,
        ]);

        $product->prices()->create([
            'price_list_id' => $defaultList1->id,
            'price' => 2000,
            'currency' => 'CLP',
        ]);
        $product->prices()->create([
            'price_list_id' => $defaultList2->id,
            'price' => 5000,
            'currency' => 'CLP',
        ]);

        // Debe usar 2000 (default de SU sucursal), nunca 5000 (otra sucursal)
        $this->assertEquals(2000, $product->resolvePrice());
    }

    public function test_falls_back_to_company_wide_default_when_no_branch_default(): void
    {
        // Company-wide default (legacy: branch_id null)
        $companyDefault = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => null,
            'is_default' => true,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'base_price' => 1000,
        ]);

        $product->prices()->create([
            'price_list_id' => $companyDefault->id,
            'price' => 3000,
            'currency' => 'CLP',
        ]);

        $this->assertEquals(3000, $product->resolvePrice());
    }

    public function test_falls_back_to_base_price_when_no_prices(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'base_price' => 7777,
        ]);

        $this->assertEquals(7777, $product->resolvePrice());
    }
}
