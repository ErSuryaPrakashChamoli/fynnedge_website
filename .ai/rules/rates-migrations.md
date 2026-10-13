---
paths:
  - 'database/seeders/data/**,app/Support/Rates/RateAuditImport.php,database/migrations/2026_10_13_123020_import_october_2026_lender_rate_audit.php'
---

# Rates Migrations

## The Oct 2026 rate audit imports as "Awaiting approval" — nothing auto-publishes
database/seeders/data/rate-audit-2026-10-13.json holds 579 records for all 352 lender×product pairs, researched only from lenders' own sites/schedules (aggregators never count), with verbatim evidence. RateAuditImport (run by migration 2026_10_13_123020) puts each verified PRIMARY rate in "Awaiting approval" (primary = general product, so borrower_segment is null; research segment text goes to source_notes, not customer fields), other verified variants as verified drafts, snippet-only figures and pre-audit figures as unverified drafts, and lists NOT_OFFERED pairs. It skips any offer that already has rate rows (idempotent). Consequence at deploy: the site shows "Check current rates" until a Marketing Publisher approves in Catalog → Interest Rates ("Awaiting approval" tab → select → "Approve & publish selected"). Verification dates expire after 90 days (REVERIFY_AFTER_DAYS), i.e. ~11 Jan 2027 for this audit. Rate dates use IST: LenderProductRate::today() returns India's date at app-timezone midnight so 'date' casts compare by calendar day.
