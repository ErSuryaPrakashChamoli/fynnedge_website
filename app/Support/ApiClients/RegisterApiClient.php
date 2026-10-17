<?php

namespace App\Support\ApiClients;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens an API client together with its own service account: a user that
 * cannot sign in to the admin (is_admin off, an unknown random password, an
 * address on a reserved domain), holding the role chosen for the client. The
 * account is what the key signs in as, so permissions, policies and "requested
 * by" work exactly as for an employee.
 */
class RegisterApiClient
{
    /**
     * @param  array{name: string, allowed_ips?: ?list<string>, notes?: ?string}  $attributes
     * @return array{client: ApiClient, key: string}
     */
    public function handle(array $attributes, string $role, ?User $createdBy): array
    {
        return DB::transaction(function () use ($attributes, $role, $createdBy): array {
            $account = User::query()->create([
                'name' => self::accountName($attributes['name']),
                'email' => 'api-client-'.Str::uuid().'@'.ApiClient::SERVICE_ACCOUNT_DOMAIN,
                'password' => Str::random(64),
                'is_admin' => false,
            ]);
            $account->syncRoles([$role]);

            $client = new ApiClient($attributes);
            $client->forceFill(['user_id' => $account->id, 'created_by' => $createdBy?->id]);

            return ['client' => $client, 'key' => $client->issueKey()];
        });
    }

    /** How the client appears as "requested by" across the admin. */
    public static function accountName(string $clientName): string
    {
        return trim($clientName).' (API client)';
    }
}
