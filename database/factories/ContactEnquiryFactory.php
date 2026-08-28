<?php

namespace Database\Factories;

use App\Models\ContactEnquiry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactEnquiry>
 */
class ContactEnquiryFactory extends Factory
{
    protected $model = ContactEnquiry::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->numerify('##########'),
            'message' => $this->faker->paragraph(),
            'source_url' => null,
            'handled_at' => null,
        ];
    }
}
