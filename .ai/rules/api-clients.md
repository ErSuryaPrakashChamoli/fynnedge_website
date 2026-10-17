---
paths:
  - 'routes/external.php,config/auth.php,app/Support/ApiClients/**,app/Models/ApiClient.php,app/Filament/Resources/ApiClients/**'
---

# Api Clients

## External API: API-key guard signs in as a service account; the guard has no provider
User decision (2026-10-17): other systems (the LMS) call FynnEdge via routes/external.php (/api/v1/external/*, `api` group, stateless) with `Authorization: Bearer fyn_…` from Admin → Access Control → API Clients. Guard `api-client` (Auth::viaRequest 'api-key' → AuthenticateApiClient) returns the client's service account User (is_admin false, role chosen per client, never super_admin), so existing policies, permissions and requested_by work unchanged. Only sha256(key) is stored; the key is shown once. Clients are revoked, never deleted. TRAP: the `api-client` guard in config/auth.php must NOT have a 'provider'. auth middleware's shouldUse() switches the default guard, and spatie/laravel-permission then checks roles on every guard whose provider is `users`. The roles live on `web`, so every permission check returned 403. RequestGuard caches its user across test requests: do failing calls first, or call app('auth')->forgetGuards().
