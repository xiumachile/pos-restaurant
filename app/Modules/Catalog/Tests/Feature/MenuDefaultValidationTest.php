<?php

namespace Modules\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Identity\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\Branch;
use Modules\Identity\Domain\Entities\User;
use Tests\TestCase;

class MenuDefaultValidationTest extends TestCase
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

    public function test_cannot_delete_only_default_menu(): void
    {
        $menu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/catalog/menus/{$menu->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'branch_requires_default_menu',
            ]);

        $this->assertDatabaseHas('menus', ['uuid' => $menu->uuid]);
    }

    public function test_can_delete_default_menu_if_another_default_exists(): void
    {
        $menu1 = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $menu2 = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/catalog/menus/{$menu1->uuid}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('menus', ['uuid' => $menu1->uuid]);
        $this->assertDatabaseHas('menus', ['uuid' => $menu2->uuid]);
    }

    public function test_cannot_unset_default_flag_from_only_default_menu(): void
    {
        $menu = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/catalog/menus/{$menu->uuid}", [
                'name' => 'Updated Name',
                'price_list_id' => $this->priceList->uuid,
                'is_default' => false,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'branch_requires_default_menu',
            ]);

        $menu->refresh();
        $this->assertTrue($menu->is_default);
    }

    public function test_can_unset_default_flag_if_another_default_exists(): void
    {
        $menu1 = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $menu2 = Menu::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'price_list_id' => $this->priceList->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/catalog/menus/{$menu1->uuid}", [
                'name' => 'Updated Name',
                'price_list_id' => $this->priceList->uuid,
                'is_default' => false,
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $menu1->refresh();
        $this->assertFalse($menu1->is_default);
    }
}
