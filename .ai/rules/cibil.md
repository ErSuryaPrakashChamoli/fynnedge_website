---
paths:
  - 'app/Modules/Cibil/**'
---

# Cibil

## CIBIL page uses a stand-in bureau (Experian), always labelled as such
There is no CIBIL API (user decision, 2026-10-16). The public CIBIL page gets real scores from a stand-in source chosen by config services.cibil.public_score_source (CIBIL_PUBLIC_SCORE_SOURCE, default "experian"), mapped in CibilSettings::SOURCES to a CreditScoreProvider. It is live only while CIBIL Settings → cibil_public_score_enabled is on and the source's token is set; that switch is separate from the Experian page's. ExperianCibilScoreProvider tags raw_response.source_bureau = "experian", and CreditScoreCheck::sourceBureau() / liveFailure() read it. The component's standInSource() must keep the page honest: never show an Experian score as a CIBIL score. To add a real CIBIL API later, register a new provider in SOURCES and change the env value; the component and SubmitCreditScoreCheck stay unchanged.

## CIBIL is a full bureau module with its own token and no fallback (supersedes the stand-in-only note)
Since 2026-10-16 CIBIL is integrated like Experian. It has its own table cibil_credit_requests (product score|report|pdf), RequestCibilCheck as the only paid entry point, an admin group "CIBIL" and routes/cibil.php (throttle cibil-api). The payload is {mobile, pan, name, gender (male|female lowercase), consent "Y"}. CibilSettings::surepassClient() passes useSharedCredentials: false, so without SUREPASS_CIBIL_TOKEN CIBIL is "not configured" and NEVER falls back to SUREPASS_BEARER_TOKEN or another bureau's token. credit_report is a LIST of TransUnion records, which CibilReportDocument reads across all records; only 300–900 counts as a score. A PDF may come as credit_report_link or credit_report_base64; the inline one is stored as an encrypted file and replaced by a marker in raw_response. The public page source (CIBIL_PUBLIC_SCORE_SOURCE) is "cibil" or the "experian" stand-in, and the default stays "experian". Use CibilSettings::isStandIn() / publicSourceConfigured() / publicScoreReuseDays() for the public page, and isConfigured() / scoreReuseDays() for the bureau. Plan on 2026-10-16: fetch-report allowed, score and fetch-report-pdf NOT.

## CIBIL page source is automatic: CIBIL once its token is set
User decision (2026-10-16). CIBIL_PUBLIC_SCORE_SOURCE is blank by default (config value null). In that case CibilSettings::source() returns "cibil" when SUREPASS_CIBIL_TOKEN is set (CibilSettings::isConfigured()), otherwise the labelled "experian" stand-in. A live page therefore switches to genuine CIBIL scores as soon as the token is added, and never drops to demo scores for want of one. An explicit "cibil" or "experian" value forces that source; an unknown value means demo scores. Earlier notes saying the default stays "experian" are superseded.
