<?php

namespace Database\Factories;

use App\Models\SchemaTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchemaTemplate>
 */
class SchemaTemplateFactory extends Factory
{
    protected $model = SchemaTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)).' schema',
            'schema_type' => 'HowTo',
            'notes' => fake()->sentence(),
            'body' => [
                '@type' => 'HowTo',
                'name' => '{{ title }}',
                'description' => '{{ description }}',
                'url' => '{{ url }}',
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
