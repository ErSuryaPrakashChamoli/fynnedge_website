<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ClosurePaymentMode;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtClosurePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtClosurePayment>
 */
class DebtClosurePaymentFactory extends Factory
{
    protected $model = DebtClosurePayment::class;

    public function definition(): array
    {
        return [
            'debt_closure_id' => DebtClosure::factory(),
            'amount' => 515000,
            'paid_on' => now()->toDateString(),
            'payment_mode' => ClosurePaymentMode::LenderDirect,
            'paid_by' => DebtClosurePayment::PAID_BY_NEW_LENDER,
            'utr_reference' => 'UTR'.$this->faker->numerify('##########'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['confirmed_at' => now()]);
    }
}
