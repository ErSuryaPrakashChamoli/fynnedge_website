<?php

namespace Database\Factories;

use App\Modules\Applications\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug(2);

        return [
            'public_id' => Str::uuid(),
            'key' => $key,
            'label' => Str::headline($key),
            'description' => null,
            'order' => 0,
        ];
    }
}
