<?php

namespace Database\Factories;

use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'category_id' => Category::factory(),
            'sku' => fake()->unique()->numerify('SKU####'),
            'name_translations' => json_encode([
                'es' => fake()->words(2, true),
                'en' => fake()->words(2, true),
            ]),
            'description_translations' => json_encode([
                'es' => fake()->sentence(),
                'en' => fake()->sentence(),
            ]),
            'base_price' => fake()->numberBetween(1000, 50000),
            'tax_rate' => 19,
            'is_combo' => false,
            'is_active' => true,
        ];
    }
}
