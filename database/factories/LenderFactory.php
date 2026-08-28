<?php

namespace Database\Factories;

use App\Enums\LenderStatus;
use App\Models\Lender;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lender>
 */
class LenderFactory extends Factory
{
    protected $model = Lender::class;

    public function definition(): array
    {
        $name = 'DEMO Lender '.$this->faker->unique()->companySuffix().' '.$this->faker->randomNumber(3);

        return [
            'public_id' => Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_path' => null,
            'description' => 'Placeholder lender used for local development and testing only — not a real FynnEdge partner.',
            'status' => LenderStatus::Active,
            'serviceable_locations' => ['Delhi', 'Mumbai', 'Bengaluru'],
        ];
    }
}
