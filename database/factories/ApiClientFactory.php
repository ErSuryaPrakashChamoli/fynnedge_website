<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A client with an unknown random key. Tests that call the API take the key
 * from $client->issueKey(), the same way the admin issues one.
 *
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = ApiClient::KEY_PREFIX.Str::random(48);

        return [
            'name' => fake()->company().' LMS',
            'user_id' => User::factory()->state(fn (): array => [
                'is_admin' => false,
                'email' => 'api-client-'.Str::uuid().'@'.ApiClient::SERVICE_ACCOUNT_DOMAIN,
            ]),
            'key_prefix' => substr($key, 0, 12),
            'key_hash' => ApiClient::hashKey($key),
            'key_generated_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    /**
     * @param  list<string>  $ips
     */
    public function allowingOnly(array $ips): static
    {
        return $this->state(['allowed_ips' => $ips]);
    }
}
