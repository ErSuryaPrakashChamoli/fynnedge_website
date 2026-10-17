---
paths:
  - 'app/Modules/Customers/**,app/Http/Controllers/Portal/**,resources/views/portal/**,resources/views/components/⚡customer-login.blade.php'
---

# Portal Views Components

## Customer portal identity: the verified mobile is the account
FynnOn customer portal (docs/customer-portal.md, 2026-10-16). The `customers` row IS the portal account (guard `customer`, OTP-only login, no password/remember token). `verified_mobile` (unique) is the login and is set ONLY by ResolveCustomerAccount after an OTP in the same request: existing verified_mobile → else exactly one unclaimed customer with that `phone` is activated → else a new account (credit-score check only; forLogin() never creates). Never select an account by typed PAN, name or email. Portal ownership of journeys/applications/documents is JourneySession::scopeVerifiedFor (session phone OTP-verified = account's verified_mobile), never customer_id alone (journey links customer_id by a typed email). Portal queries start from the signed-in customer and answer 404 for anyone else's record.
