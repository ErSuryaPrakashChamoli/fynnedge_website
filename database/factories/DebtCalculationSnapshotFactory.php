<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Services\Finance\FormulaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Prefer AssessDebtApplication in tests that need real figures; this
 * factory only makes a row.
 *
 * @extends Factory<DebtCalculationSnapshot>
 */
class DebtCalculationSnapshotFactory extends Factory
{
    protected $model = DebtCalculationSnapshot::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'kind' => DebtCalculationSnapshot::KIND_ASSESSMENT,
            'formula_version' => FormulaVersion::CURRENT,
            'inputs' => [],
            'inputs_hash' => hash('sha256', '[]'),
            'results' => ['scenarios' => [], 'flags' => []],
            'created_by_type' => ActorType::System,
        ];
    }
}
