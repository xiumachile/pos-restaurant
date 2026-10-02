<?php

namespace Modules\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Tests\TestCase;

class CatalogHealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_reports_healthy_when_all_defaults_exist(): void
    {
        $priceList = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'is_default' => true,
        ]);

        $menu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $priceList->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $product->prices()->create([
            'price_list_id' => $priceList->id,
            'price' => 1000,
            'currency' => 'CLP',
        ]);
        $menu->menuProducts()->create(['product_id' => $product->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.healthy', true)
            ->assertJsonPath('data.checks.has_default_price_list.ok', true)
            ->assertJsonPath('data.checks.has_default_menu.ok', true)
            ->assertJsonPath('data.checks.products_without_menu.ok', true)
            ->assertJsonPath('data.checks.products_without_default_price.ok', true);
    }

    public function test_reports_unhealthy_when_menu_default_missing(): void
    {
        PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/health');

        $response->assertOk()
            ->assertJsonPath('data.healthy', false)
            ->assertJsonPath('data.checks.has_default_menu.ok', false);
    }

    public function test_reports_unhealthy_when_products_without_menu(): void
    {
        $priceList = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'is_default' => true,
        ]);
        Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $priceList->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/health');

        $response->assertOk()
            ->assertJsonPath('data.healthy', false)
            ->assertJsonPath('data.checks.products_without_menu.ok', false)
            ->assertJsonPath('data.checks.products_without_menu.count', 1);
    }
}
