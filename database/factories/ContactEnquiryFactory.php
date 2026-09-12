<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
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
            'phone' => $this->faker->numerify('9#########'),
            'message' => $this->faker->paragraph(),
            'source_url' => null,
            'handled_at' => null,
            'enquiry_type' => EnquiryType::Contact,
            'source' => 'website',
            'status' => EnquiryStatus::New,
            'enquiry_count' => 1,
            'phone_verified_at' => null,
        ];
    }

    /**
     * A phone-number-only lead, as the public Quick Enquiry form creates it.
     */
    public function quickEnquiry(): static
    {
        return $this->state(fn (): array => [
            'enquiry_type' => EnquiryType::QuickEnquiry,
            'name' => null,
            'email' => null,
            'message' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => EnquiryStatus::Closed,
            'handled_at' => now()->subMonths(6),
        ]);
    }
}
