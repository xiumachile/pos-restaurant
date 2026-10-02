<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Companies\Domain\Entities\Company;
use Tests\TestCase;

class EnsureDefaultMenuCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_defaults_for_branch_without_them(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::withoutEvents(fn() => Branch::factory()->create(['company_id' => $company->id]));

        $this->artisan("catalog:ensure-default-menu {$branch->id}")
            ->assertSuccessful();

        $this->assertDatabaseHas('price_lists', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('menus', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'is_default' => true,
        ]);
    }

    public function test_command_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::withoutEvents(fn() => Branch::factory()->create(['company_id' => $company->id]));

        $this->artisan("catalog:ensure-default-menu {$branch->id}")->assertSuccessful();

        $priceListId = PriceList::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->first()->id;
        $menuId = Menu::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->first()->id;

        $this->artisan("catalog:ensure-default-menu {$branch->id}")->assertSuccessful();

        $this->assertEquals($priceListId, PriceList::withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_default', true)->first()->id);
        $this->assertEquals($menuId, Menu::withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_default', true)->first()->id);
        $this->assertEquals(1, PriceList::withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_default', true)->count());
        $this->assertEquals(1, Menu::withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_default', true)->count());
    }

    public function test_command_processes_all_branches_when_no_argument(): void
    {
        $company = Company::factory()->create();
        $branch1 = Branch::withoutEvents(fn() => Branch::factory()->create(['company_id' => $company->id]));
        $branch2 = Branch::withoutEvents(fn() => Branch::factory()->create(['company_id' => $company->id]));

        $this->artisan('catalog:ensure-default-menu')->assertSuccessful();

        $this->assertDatabaseHas('price_lists', ['branch_id' => $branch1->id, 'is_default' => true]);
        $this->assertDatabaseHas('price_lists', ['branch_id' => $branch2->id, 'is_default' => true]);
        $this->assertDatabaseHas('menus', ['branch_id' => $branch1->id, 'is_default' => true]);
        $this->assertDatabaseHas('menus', ['branch_id' => $branch2->id, 'is_default' => true]);
    }
}
