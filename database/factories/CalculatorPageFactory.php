<?php

namespace Database\Factories;

use App\Models\CalculatorPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CalculatorPage>
 */
class CalculatorPageFactory extends Factory
{
    protected $model = CalculatorPage::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'calculator_key' => 'calculator-'.$this->faker->unique()->slug(2),
            'title' => $this->faker->sentence(4),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
        ];
    }
}
