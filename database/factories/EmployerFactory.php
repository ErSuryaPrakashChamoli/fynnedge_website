<?php

namespace Database\Factories;

use App\Modules\Eligibility\Models\Employer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employer>
 */
class EmployerFactory extends Factory
{
    protected $model = Employer::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'name' => $this->faker->unique()->company(),
            'notes' => null,
        ];
    }
}
