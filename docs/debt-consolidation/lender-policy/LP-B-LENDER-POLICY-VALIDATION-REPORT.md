# LP-B — Lender policy validation report (Debt Consolidation)

**For:** Credit, the Head of Advisory and Compliance.
**Date:** 26 Nov 2026.
**Prepared by:** engineering (Claude), from the code, the tests and the local database. **This is not a lender policy approval.**

## 1. Result

| Measure | Result |
| --- | --- |
| Lender DC policies configured | **0** |
| Lender DC policies verified against a lender document | **0** |
| Lender documents received by engineering | **0** |
| Policies invented or pre-filled by engineering | **0** (none created; see §3) |
| Can an unverified policy be presented as eligibility? | **No.** Verified in code and tests (§2). |
| LP-01 status | **PENDING:** needs lender documents, BV-16 and LA-01 first |

**Consequence today:**
- *Match lenders* returns no lenders for any case, because no policy is in force.
- Customers see no lender suggestions. The website and calculators make no lender-specific eligibility claims.
- The service can take enquiries and run assessments without lender policies, but no lender can be matched or shortlisted until LP-01 is complete.

## 2. Controls verified

| # | Control | Where | Evidence |
| --- | --- | --- | --- |
| 1 | Only an **Active** policy, inside its effective dates, on an active offer of an active lender, is used for matching. Drafts, expired policies and inactive lenders are ignored. | `DebtLenderPolicy::scopeInForce`, `MatchLenders::handle` | Test *ignores draft, expired and inactive-lender policies* (`tests/Feature/DebtConsolidation/DebtCaseWorkflowTest.php`) |
| 2 | Only a second person can make a policy Active (maker-checker; the super admin is exempt) | `DebtLenderPolicyResource` approve action, `MakerChecker::isMaker` | Test *creates a policy in the admin form, needs a second person to approve it, then matches with it* (`DebtConsolidationAdminTest.php`) |
| 3 | A Debt Advisor cannot approve policies | Shield permission `approve_debt_lender_policy` | Test *lets a Debt Advisor work cases but not approve lender policies or change scoring* |
| 4 | Approved criteria cannot change, even from an edit page opened while the policy was a draft | `DebtLenderPolicy::booted`, Edit page `beforeSave()` | Test *never changes an approved policy…* |
| 5 | Every policy must name its source and the date it was received | Form: `source_name`, `source_received_on` required | Admin form |
| 6 | A match has three outcomes: *Potential match*, *More information needed* or *Does not match*. Wording on screens: "fits the lender's recorded criteria; it is not an approval or an offer". | `LenderMatchStatus`, `LenderMatchesRelationManager` | Code review |
| 7 | Customers see a lender only after an advisor has **shortlisted** it, worded as "Your advisor suggests [lender]", with a separate per-lender consent. No match status, score or eligibility wording is shown to customers. | `Portal\DebtConsolidationController`, `portal/debt-consolidation/show.blade.php` | Test *records consent for a lender only when the advisor shortlisted it* (`DebtConsolidationPortalTest.php`) |
| 8 | The lender's own decision is a separate record (Lender applications: submitted → approved / rejected / sanctioned / disbursed) and is recorded only with evidence | `UpdateLenderApplicationStatus` | Test *records the lender decision with evidence…* |
| 9 | Reports keep suitability, affordability and eligibility separate: "Financial suitability, repayment affordability and lender eligibility are separate: only a lender decides eligibility." | `debt-consolidation/reports/pdf.blade.php` | Code review |

## 3. What engineering deliberately did **not** do

- **No lender policies were created, seeded or imported**, not even from public rate cards. A public rate card does not state:
  - takeover of other lenders' loans or cards;
  - DPD limits;
  - FOIR caps for consolidation;
  - own-loan rules.
  Filling those from assumptions would be inventing policy.
- **The seed data was not changed.** The two "DEMO Lender" entries are local demonstration data from `EligibilitySeeder`. They must not be in production; checking this is item P-02 in `LP-C`.

## 4. Points for Credit to note

1. **Fallback to the rate card.** If an Active policy leaves *Rate from / up to*, *Min / max loan amount* or *Max tenure* blank, the matcher sizes the indicative loan from the offer's published rate card in *Loan Products & Lenders*. That card is a lender figure, kept current by the rate audit process. The match reason states the figure used.
   - **Decision for Credit (P-05 in LP-C):** keep this fallback, or require these fields on every DC policy.
2. **Unchecked criteria.** If a policy leaves an applicant criterion blank (for example minimum credit score), that rule is simply not checked. Credit should leave a field blank only when the lender does not use that criterion, and say so in *Notes*.
3. **Declared, not bureau, credit score.** Matching uses the credit score the customer declared, not a bureau pull. The reason line says "not bureau-verified".
4. **Eligibility rule sets.** If an offer also has an active eligibility rule set in *Loan Products & Lenders*, the matcher applies it unchanged. Today only the two DEMO offers have one.

## 5. Recommendation

Do not activate the service for live customer applications that expect lender matching until:
- at least one real partner policy has been entered from a lender document;
- that policy has been approved by a second person;
- it has been tested on a non-production copy (`LP-A` §4).
