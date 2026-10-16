---
paths:
  - 'app/Modules/Equifax/**'
---

# Equifax

## Equifax is a separate module; paid pulls go only through RequestEquifaxCheck
Equifax (Surepass credit-report-v2) lives in app/Modules/Equifax with its own table equifax_credit_requests, enums, policy, admin group "Equifax" and API (routes/equifax.php) — never mix it into CRIF classes. Shared infra only: CreditBureau/Services/SurepassClient (transport, no retries), CreditConsentGate + CreditConsentInput (consent), PanNumber, audit_logs, Credit Officer role. Start pulls only via RequestEquifaxCheck (or EquifaxCreditService::request); its in_flight_key unique index is per PAN + product (EquifaxFingerprint::inFlight). A score request may be answered by a recent report; a score never answers a report. Surepass accepts gender male|female (lowercase) only. FynnEdge's plan (2026-10-16) allows fetch-report but NOT score / fetch-pdf-report — check `php artisan equifax:check-access` before relying on them.

## Equifax token: SUREPASS_EQUIFAX_TOKEN first, shared token as fallback
Equifax's Surepass client comes from EquifaxSettings::surepassClient() (contextually bound for SurepassEquifaxService in AppServiceProvider). It uses services.surepass.equifax_token / equifax_base_url (SUREPASS_EQUIFAX_TOKEN / SUREPASS_EQUIFAX_BASE_URL), and a blank value falls back to the shared SUREPASS_BEARER_TOKEN / SUREPASS_BASE_URL at runtime, not in the config file. CRIF never uses the Equifax token. phpunit.xml blanks both Equifax env keys so a real token in .env can't reach tests. Never resolve app(SurepassClient::class) for Equifax: that is the shared client.
