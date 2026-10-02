<?php

namespace Database\Factories;

use Modules\Catalog\Domain\Entities\Category;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'name_translations' => json_encode([
                'es' => fake()->word(),
                'en' => fake()->word(),
            ]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
