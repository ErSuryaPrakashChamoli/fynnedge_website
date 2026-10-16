---
paths:
  - 'app/Modules/Cibil/**'
---

# Cibil

## CIBIL page uses a stand-in bureau (Experian), always labelled as such
There is no CIBIL API (user decision, 2026-10-16). The public CIBIL page gets real scores from a stand-in source chosen by config services.cibil.public_score_source (CIBIL_PUBLIC_SCORE_SOURCE, default "experian"), mapped in CibilSettings::SOURCES to a CreditScoreProvider. It is live only while CIBIL Settings → cibil_public_score_enabled is on and the source's token is set; that switch is separate from the Experian page's. ExperianCibilScoreProvider tags raw_response.source_bureau = "experian", and CreditScoreCheck::sourceBureau() / liveFailure() read it. The component's standInSource() must keep the page honest: never show an Experian score as a CIBIL score. To add a real CIBIL API later, register a new provider in SOURCES and change the env value; the component and SubmitCreditScoreCheck stay unchanged.
