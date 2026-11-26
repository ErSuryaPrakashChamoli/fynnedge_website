<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCollateral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtCollateral>
 */
class DebtCollateralFactory extends Factory
{
    protected $model = DebtCollateral::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'collateral_type' => CollateralType::ResidentialProperty,
            'description' => '2 BHK flat',
            'ownership' => 'self',
            'estimated_value' => 6000000,
            'existing_charge_amount' => 0,
            'city' => 'Pune',
        ];
    }
}
