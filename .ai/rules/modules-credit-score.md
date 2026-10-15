---
paths:
  - 'app/Modules/CreditScore/**'
---

# Modules Credit Score

## Public CRIF score reads from fetch-report: the Surepass plan lacks the score API
FynnEdge's Surepass token (checked 2026-10-15) may call credit-report-crif/fetch-report but gets 401 invalid_token "not valid for this API/Scope" on credit-report-crif/score and fetch-report-pdf. So SurepassCrifScoreProvider defaults to services.surepass.crif_public_score_source = "report" (score taken from the full report, rest discarded). Don't switch the default back to "score" unless `php artisan crif:check-access` shows it Allowed. That 401 is classified CrifErrorCode::ApiNotEnabled (message contains "scope"), not AuthenticationFailed. Probing with an empty body is free: Surepass answers 400 validation (allowed) or 401 (blocked) before any bureau lookup.
