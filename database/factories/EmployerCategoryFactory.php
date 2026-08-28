<?php

namespace Database\Factories;

use App\Models\Lender;
use App\Modules\Eligibility\Models\EmployerCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployerCategory>
 */
class EmployerCategoryFactory extends Factory
{
    protected $model = EmployerCategory::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'lender_id' => Lender::factory(),
            'key' => 'category-'.$this->faker->unique()->randomLetter(),
            'label' => 'Category '.$this->faker->randomLetter(),
            'description' => null,
            'order' => 0,
        ];
    }
}
