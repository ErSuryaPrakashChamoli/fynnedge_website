<?php

namespace App\Support\ApiClients;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The `api-client` guard (config/auth.php, registered with Auth::viaRequest in
 * AppServiceProvider): `Authorization: Bearer fyn_…` signs in as the API
 * client's service account. Anything else — no key, an unknown or revoked key,
 * a call from an address the client does not allow — is no user at all, which
 * the `auth:api-client` middleware answers with 401.
 */
class AuthenticateApiClient
{
    public function __invoke(Request $request): ?User
    {
        $key = $request->bearerToken();

        if (! is_string($key) || ! str_starts_with($key, ApiClient::KEY_PREFIX)) {
            return null;
        }

        $client = ApiClient::query()->with('user')->where('key_hash', ApiClient::hashKey($key))->first();

        if ($client === null || ! $client->isActive() || $client->user === null) {
            return null;
        }

        if (! $client->allowsIp($request->ip())) {
            Log::warning('API client key used from an address it does not allow', ['client' => $client->public_id, 'ip' => $request->ip()]);

            return null;
        }

        $client->recordUse($request->ip());

        return $client->user;
    }
}
