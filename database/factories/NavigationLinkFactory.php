<?php

namespace Database\Factories;

use App\Models\NavigationLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NavigationLink>
 */
class NavigationLinkFactory extends Factory
{
    protected $model = NavigationLink::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'label' => $this->faker->words(2, true),
            'url' => null,
            'route_name' => 'contact',
            'is_external' => false,
            'location' => 'footer',
            'parent_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
