<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * The test suite's `User::factory()->create(['is_admin' => true])`
     * call-sites all predate role-based access and expect full panel access
     * — grant that the same way production does, via the `super_admin`
     * role, rather than updating hundreds of call-sites. This is a test
     * convenience only: real Marketing/SEO users are created through the
     * admin panel's Users screen instead, which never does this.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->is_admin) {
                $user->assignRole(Role::findOrCreate('super_admin'));
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
