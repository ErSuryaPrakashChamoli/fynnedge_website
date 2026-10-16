---
paths:
  - routes/api.php
  - routes/equifax.php
---

# Routes

## Public API: versioned, throttled, envelope responses, no Sanctum
routes/api.php (registered in bootstrap/app.php, /api prefix) is public and stateless — no Sanctum installed, no session/CSRF/auth. Every route needs its own named throttle (AppServiceProvider, Limit::none() under tests) and lives under the v1 prefix with an api.v1.* name. Responses use { success, message, data | error_code }; Form Requests under App\Http\Requests\Api\V1 override failedValidation to the same envelope. SecurityHeaders is appended to the `api` group too. Per-IP limits rely on $request->ip(): if a CDN/LB is added in front of nginx, configure trustProxies or every visitor shares one budget.

## Authenticated JSON API: session auth, JSON forced before auth
The app has no token auth (no Sanctum) and routes/api.php is public, so staff-only JSON endpoints live in their own route file loaded in bootstrap/app.php `then:` with the `web` group (session + CSRF) and middleware ['equifax.json', 'auth', throttle]. ForceJsonResponse must run BEFORE auth — it is registered with prependToPriorityList(AuthenticatesRequests::class, …) because Laravel otherwise sorts auth first and a signed-out caller hits route('login'), which does not exist (500). Inside the app, call EquifaxCreditService instead of HTTP to yourself.
