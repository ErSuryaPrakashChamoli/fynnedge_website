---
paths:
  - 'app/Modules/CreditBureau/**'
---

# Credit Bureau

## CRIF reports are paid calls: one entry point, no retries, DB-level duplicate guard
Every Surepass CRIF fetch costs money. Start requests ONLY through Actions/RequestCrifReport (consent → in-flight block → reuse window → rejected-details guard → pending row + FetchCrifReport job). Never add ->retry()/->throw() to SurepassCrifService or raise FetchCrifReport::$tries above 1; an uncertain outcome (read timeout, 5xx except 503, malformed 200, success without report) must go to reconciliation_required and keep the PAN locked. The real duplicate guard is the unique index on crif_reports.in_flight_key (= pan_hash while pending/processing/reconciliation_required, NULL otherwise) — any new transition must set/clear it consistently. pan_hash/request_fingerprint are HMACs keyed from APP_KEY (rotating it breaks reuse). See docs/crif-credit-report-integration.md.
