---
paths:
  - 'app/Modules/Equifax/**'
---

# Equifax

## Equifax is a separate module; paid pulls go only through RequestEquifaxCheck
Equifax (Surepass credit-report-v2) lives in app/Modules/Equifax with its own table equifax_credit_requests, enums, policy, admin group "Equifax" and API (routes/equifax.php) — never mix it into CRIF classes. Shared infra only: CreditBureau/Services/SurepassClient (transport, no retries), CreditConsentGate + CreditConsentInput (consent), PanNumber, audit_logs, Credit Officer role. Start pulls only via RequestEquifaxCheck (or EquifaxCreditService::request); its in_flight_key unique index is per PAN + product (EquifaxFingerprint::inFlight). A score request may be answered by a recent report; a score never answers a report. Surepass accepts gender male|female (lowercase) only. FynnEdge's plan (2026-10-16) allows fetch-report but NOT score / fetch-pdf-report — check `php artisan equifax:check-access` before relying on them.
