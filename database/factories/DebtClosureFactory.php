<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtClosure>
 */
class DebtClosureFactory extends Factory
{
    protected $model = DebtClosure::class;

    public function definition(): array
    {
        return [
            'debt_liability_id' => DebtLiability::factory(),
            'debt_application_id' => fn (array $attributes): int => DebtLiability::query()->findOrFail($attributes['debt_liability_id'])->debt_application_id,
            'closure_type' => ConsolidationMode::Full,
            'status' => ClosureStatus::Planned,
        ];
    }
}
