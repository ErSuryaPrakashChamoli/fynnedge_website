---
paths:
  - app/Providers/Filament/AdminPanelProvider.php
---

# App Providers Filament

## multiFactorAuthentication's isRequired closure cannot see the authenticated user
Do not attempt per-role/per-user MFA enforcement via `->multiFactorAuthentication($providers, isRequired: fn () => ...)`. Empirically verified: this closure is evaluated while panel routes/middleware are being registered, which happens before session-based auth resolves in the request lifecycle — `Filament::auth()->user()` is always null inside it, so any role-based condition silently never enforces for anyone (confirmed both roles reach `/admin` with 200, no redirect). A static `isRequired: true` DOES work (redirects every role to `/admin/multi-factor-authentication/set-up`) — the mechanism itself is fine, just not per-role. If per-role MFA enforcement is ever required, it needs either a Filament version where this is resolved differently, or an explicit decision to add custom middleware (a deliberate scope change, not a Phase 5 default). See tests/Feature/Filament/MfaEnforcementProbeTest.php.
