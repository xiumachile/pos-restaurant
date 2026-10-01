<?php

namespace Modules\Catalog\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\MenuActivation;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Identity\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Tests\TestCase;

class MenuResolvePreviewTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;
    private PriceList $priceList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->priceList = PriceList::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_resolve_preview_returns_null_when_no_menus(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/menus/resolve-preview?channel_type=dine_in');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    public function test_resolve_preview_returns_default_menu(): void
    {
        $menu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/menus/resolve-preview?channel_type=dine_in');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu' => [
                        'uuid' => $menu->uuid,
                    ],
                ],
            ]);
    }

    public function test_resolve_preview_with_activation_rule(): void
    {
        $defaultMenu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $deliveryMenu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => false,
            'is_active' => true,
        ]);

        MenuActivation::create([
            'menu_id' => $deliveryMenu->id,
            'channel_type' => 'delivery',
            'days_of_week' => null,
            'time_from' => '00:00:00',
            'time_to' => '23:59:59',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Preview para dine_in debe retornar default
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/menus/resolve-preview?channel_type=dine_in');

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu' => [
                        'uuid' => $defaultMenu->uuid,
                    ],
                ],
            ]);

        // Preview para delivery debe retornar deliveryMenu
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/v1/catalog/menus/resolve-preview?channel_type=delivery');

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu' => [
                        'uuid' => $deliveryMenu->uuid,
                    ],
                ],
            ]);
    }

    public function test_resolve_preview_with_datetime_parameter(): void
    {
        $menu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $futureDate = Carbon::now()->addDays(7)->toIso8601String();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/catalog/menus/resolve-preview?channel_type=dine_in&datetime={$futureDate}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'menu' => [
                        'uuid' => $menu->uuid,
                    ],
                ],
            ]);
    }
}
