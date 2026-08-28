<?php

namespace Database\Factories;

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LenderProduct>
 */
class LenderProductFactory extends Factory
{
    protected $model = LenderProduct::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'lender_id' => Lender::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'min_amount' => 50000,
            'max_amount' => 1500000,
            'min_tenure_months' => 12,
            'max_tenure_months' => 60,
            'interest_rate_from' => 10.5,
            'interest_rate_to' => 18.0,
            'processing_fee_note' => 'Up to 2% of loan amount',
            'status' => LenderStatus::Active,
        ];
    }
}
