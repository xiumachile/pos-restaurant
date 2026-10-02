<?php

namespace Database\Factories;

use Modules\Catalog\Domain\Entities\Menu;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'price_list_id' => PriceList::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
