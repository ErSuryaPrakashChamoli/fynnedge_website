# 08 — Testing report

## Baseline (before any Debt Consolidation change)

**Run:** 25 Nov 2026, `php artisan test --compact`, local (SQLite in-memory), 12 min 41 s.

| Tests | Passed | Failed | Errors |
| --- | --- | --- | --- |
| 3,011 | 2,996 | 14 | 1 |

All 15 failures are **pre-existing** and in one file, `tests/Feature/Experian/ExternalExperianApiTest.php`. Every request to `/api/v1/external/experian/...` answers 404 because `routes/external.php` only registers the CIBIL external routes (the `.ai/rules/api-v1.md` note describes an Experian mirror that is not in the routes file). This is unrelated to Debt Consolidation and was not changed by this project; it is listed as a known pre-existing defect in `00-project-status.md`.

## Debt Consolidation suites (final, after pre-production acceptance, 26 Nov 2026)

| Suite | Tests | Assertions | Result |
| --- | --- | --- | --- |
| `tests/Unit/DebtConsolidation` — engine: amortisation, projection, foreclosure, comparison (incl. the loan-sizing rule), affordability, portfolio, suitability, end-to-end assessment | 64 | 206 | all pass |
| `tests/Feature/DebtConsolidation` — pages, site preference, calculator, enquiry, application, workflow and 8 reports, admin (screens, 360° tabs, maker-checker, role editor, per-action permissions), portal isolation, API, Hindi, retention, rollback, immutability | 151 | 606 | all pass |

The acceptance round added 31 tests, one or more for each of the 24 defects it found and fixed (`16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` §5). It also corrected three of this project's own fixtures to match the intended rules, with no assertion removed: two calculator tests now publish the assessment page, which the hand-off requires; one closure test now uses a real NOC and a case in "Closure in progress".

How the expected figures were obtained: worked out independently, by hand or with a separate Python `Decimal` implementation, never with the engine's own code (`06-financial-engine.md`). Security tests cover every portal route answering 404 for another customer's case, document and report, the Livewire ownership checks, consent gates on lender applications, and API authentication, permission and consent.

## Full regression runs (`php artisan test --compact`, local)

| Run | When | Tests | Passed | Failed / errors | Notes |
| --- | --- | --- | --- | --- | --- |
| Baseline | before any change | 3,011 | 2,996 | 15 | all pre-existing Experian |
| 1 | after all modules | — | — | 6 extra | 4 caused by editing files mid-run (a race, re-run clean); 2 real: 25 seeded **published** FAQs broke `BannerAndFaqSchedulingTest` → seeded FAQs made drafts, "Publish selected" bulk action added |
| 2 | after the fixes | 3,203 | 3,188 | 15 | all pre-existing Experian |
| 3 | after the site-preference requirement | 3,212 | 3,197 | 15 | all pre-existing Experian |
| **4 (final)** | after the pre-production acceptance fixes (25–26 Nov) | **3,243** | **3,227** | **16** | **No regressions; all 16 pre-existing**: 15 Experian, plus `PublicRateDisplayTest` (see below); 15 min 44 s |

**Run 4's extra failure is pre-existing and time-of-day dependent.** `PublicRateDisplayTest` "labels a third-party rate with its source…" ran after midnight IST. It expects the India-time date (26 Nov), but the rates module prints the UTC date (25 Nov), so it fails every night between 00:00 and 05:30 IST. It fails identically on a clean checkout of the last commit (`084825b`) at the same time; runs 2 and 3 passed it because they ran before midnight. The rates module is untouched by this project.

## Baseline evidence (pre-production acceptance)

- The Experian test file was committed in `d446274`, before this project. At `084825b`, `routes/external.php` registers only CIBIL routes.
- On a clean worktree of `084825b` (its own `vendor`), the Experian file gives 16 tests: 1 pass, 14 fail, 1 error, the same 15 as today.
- `pest --list-tests`: all 3,011 tests at `084825b` still exist. The 5 names that differ are dataset rows with random IDs; per-test row counts are identical.
- Of the files that existed before this project, only `tests/Feature/MobileOtp/MobileOtpApiTest.php` changed: +1 dataset row, no removals.

## Other verification

- Migrations: `migrate`, `migrate:rollback --step=7` (back to the 85 baseline tables) and re-`migrate` on local MySQL; `--pretend` DDL checked for identifier lengths.
- Visual checks in headless Chromium: hub, calculator and assessment (desktop and phone); homepage, header strip and mobile menu at 390, 1024 and 1280 px in English and Hindi.
- Header width (owner requirement 19): no horizontal overflow at 360–1440 px in either language. The tightest case is English at 1280 px with a scrollbar on the system fallback font, which leaves 19 px (12 px on a Debt Consolidation page). The live service previously overflowed by 93 px at 1280 px; this is fixed.
- Engine performance: about 34 ms for 20 debts × 3 options.

## Financial validation

Ten cases (18 options) were run through the engine and an independent Decimal reference written from the specification. All 827 figures agree to the paisa, after one correction found by this validation (loan sizing, D-23). The workbook has 1,960 formulas and recalculates without errors. See `validation/DC-financial-validation-workbook.xlsx` and `16-…` §3.

## Not yet done (needs the business)

- Parallel run on 10–20 anonymised past cases against real lender foreclosure letters, sanction letters and schedules (BV-13 / HV-01 in `16-…` §6).
- User acceptance by advisors on the admin screens, with real lender policies entered.
