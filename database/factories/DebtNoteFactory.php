<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\NoteType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtNote>
 */
class DebtNoteFactory extends Factory
{
    protected $model = DebtNote::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'type' => NoteType::InternalRemark,
            'body' => $this->faker->sentence(),
        ];
    }
}
