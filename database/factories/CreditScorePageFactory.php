<?php

namespace Database\Factories;

use App\Models\CreditScorePage;
use App\Modules\CreditScore\Enums\BureauName;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditScorePage>
 */
class CreditScorePageFactory extends Factory
{
    protected $model = CreditScorePage::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'bureau' => $this->faker->unique()->randomElement(BureauName::cases()),
            'title' => $this->faker->sentence(4),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
        ];
    }
}
