<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
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
            'enquiry_source' => 'Contact Page',
            'loan_product_id' => null,
            'loan_amount' => null,
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
            'enquiry_source' => 'Homepage Quick Enquiry',
            'name' => null,
            'email' => null,
            'message' => null,
        ]);
    }

    /**
     * A lead from a loan page's enquiry form, carrying the product it was made
     * about — the state most reporting assertions need.
     */
    public function forLoanProduct(LoanProduct $loanProduct): static
    {
        return $this->state(fn (): array => [
            'enquiry_type' => EnquiryType::LoanEnquiry,
            'loan_product_id' => $loanProduct->id,
            'enquiry_source' => "{$loanProduct->name} Page",
            'source_url' => '/loans/'.$loanProduct->slug,
            'loan_amount' => 500000,
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
