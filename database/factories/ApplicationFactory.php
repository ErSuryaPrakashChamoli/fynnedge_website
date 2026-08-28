<?php

namespace Database\Factories;

use App\Models\LenderProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'journey_session_id' => JourneySession::factory(),
            'customer_id' => null,
            'lender_product_id' => LenderProduct::factory(),
            'eligibility_result_id' => null,
            'status' => ApplicationStatus::LenderSelected,
            'submitted_at' => null,
        ];
    }
}
