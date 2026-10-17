---
paths:
  - 'app/Modules/Cibil/Services/CibilSettings.php,app/Filament/Pages/CibilSettings.php,app/Modules/Cibil/Providers/**,resources/views/components/⚡credit-score-check.blade.php'
---

# Providers Views Components

## Get CIBIL Score page: two independent admin switches decide demo / CIBIL / Experian (supersedes CIBIL_PUBLIC_SCORE_SOURCE, the automatic source and the exclusive switches)
User decision (2026-10-17). CIBIL Settings has exactly two switches for the public page: "Real CIBIL score enabled" (cibil_public_score_enabled; off = labelled demo, no bureau call, whatever switch 2 says) and "Fetch Experian instead of CIBIL" (cibil_public_score_use_experian; off/absent = CIBIL via Surepass CIBIL, on = Experian via ExperianCibilScoreProvider). Read them only through CibilSettings::source()/sourceBureau()/liveProvider(). A failure is never retried on the other bureau. A chosen bureau without its token = labelled demo, and the settings page says which token. CIBIL_PUBLIC_SCORE_SOURCE and the "CIBIL once SUREPASS_CIBIL_TOKEN is set" automatic choice are gone. Migration 2026_10_17_105911 kept the old Experian choice only where real scores were already on. The earlier exclusivity of "CIBIL requests enabled" vs the page switch is removed: they are independent, so the LMS API keeps working while the page shows real scores. The component locks disclosedSource at the details step; if an admin flips the bureau before submit, the visitor re-submits (OTP not spent).
