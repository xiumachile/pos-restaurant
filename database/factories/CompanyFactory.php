<?php

namespace Database\Factories;

use Modules\Companies\Domain\Entities\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tax_id' => fake()->numerify('########-#'),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->company(),
            'default_locale' => 'es',
            'fallback_locale' => 'en',
            'is_active' => true,
        ];
    }
}
