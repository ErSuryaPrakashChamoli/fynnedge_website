# LP-A — Lender policy configuration checklist (Debt Consolidation)

**For:** Credit (maker) and a second Credit or Head of Advisory reviewer (checker), who enter each partner lender's debt consolidation policy in the admin (**Debt Consolidation → Lender policies**).
**Status on 26 Nov 2026:** **0 policies configured, 0 verified.** No lender policy document has been provided to engineering. Every lender is **UNVERIFIED**.
**Related:** `LP-B-LENDER-POLICY-VALIDATION-REPORT.md`, `LP-C-LENDER-POLICY-PENDING-INFORMATION-REGISTER.md`, approval **LP-01** in `../17-BUSINESS-APPROVAL-TRACKER.md`.

## 1. Rules

1. **Never invent a policy.** Enter a criterion only when it is in a document received from the lender, such as:
   - a DSA or LSP product note;
   - a policy circular;
   - a signed email from the lender's relationship manager.
2. **Leave unknown criteria blank.** A blank field means "not recorded". The matcher treats it as follows:
   - **blank rate, amount or tenure:** falls back to the lender offer's published rate card in *Loan Products & Lenders*;
   - **blank applicant criterion:** the rule is not checked;
   - **blank required list:** the form will not save.
   Where the lender's position is genuinely unknown, do not approve the policy: keep it in **Draft**.
3. **Draft policies are never used.** Matching uses only a policy that meets all of the following (`DebtLenderPolicy::scopeInForce`):
   - status **Active**;
   - inside its effective dates;
   - on an active offer of an active lender.
   A draft is not shown to advisors as a match, and never to customers.
4. **Maker-checker.** The person who entered or last changed a policy cannot approve it. The admin refuses this, and only the super admin is exempt. The approver's name and time are stored on the policy.
5. **Approved policies are locked.** To change one, create a new **version**. The old version stays as the record of what was in force.
6. **Every policy must carry its source:**
   - source name;
   - reference;
   - link (if any);
   - date received.
   Keep the document itself in the restricted Credit folder, not on the website.
7. **A "potential match" is not eligibility and not an approval.** The admin and reports say so. Only the lender decides eligibility, after its own checks.

## 2. Lender offers that need a policy

The lender directory below is from the **local development database**, which is rebuilt from seeders. Engineering has **no access to production** and has not seen its lender directory.

**Before LP-01 starts, Credit must replace this list with the production list of partner lenders and offers, then confirm that each one is a real partner for debt consolidation.**

| # | Lender | Offer (loan product) | Offer status | Rate card in *Loan Products & Lenders* | DC policy | Verification status |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | DEMO Lender Inc 342 | Personal Loan | active | 10.50–18.00% | none | **Not a real lender.** Seeded demonstration data (`EligibilitySeeder`); must not exist in production. |
| 2 | DEMO Lender Ltd 595 | Home Loan | active | 7.00–10.50% | none | **Not a real lender.** Seeded demonstration data; must not exist in production. |
| 3 | Bajaj Finance | Flexi Hybrid Term Loan | active | 10.00–30.50% (rate audit 13 Oct 2026) | none | UNVERIFIED for debt consolidation |
| 4 | Tata Capital | Flexi Hybrid Term Loan | active | from 12.99% | none | UNVERIFIED for debt consolidation |
| 5 | Aditya Birla Finance | Flexi Hybrid Term Loan | active | 12.50–28.00% | none | UNVERIFIED for debt consolidation |
| 6 | Piramal Finance | Flexi Hybrid Term Loan | active | from 12.99% | none | UNVERIFIED for debt consolidation |
| — | 29 other lenders in the directory (SBI, PNB, Bank of Baroda, Kotak, IDFC FIRST, HDB, Muthoot, L&T, IIFL, etc.) | no offer recorded | — | — | none | UNVERIFIED; no offer, so they cannot be matched |

**Note.** A published rate card shows what a lender charges for a product. It does **not** show that the lender:
- takes over other lenders' debts;
- takes over credit cards;
- or accepts any particular customer for consolidation.
That is what the DC policy records.

## 3. Per-offer checklist

Copy this block once for each offer. Every row needs a value **and** the document it came from, or "Not stated by lender".

**Where each criterion is recorded:**

| Code | Recorded in | How it is used |
| --- | --- | --- |
| **P** | A field of the DC lender policy | Checked automatically by *Match lenders* |
| **E** | The offer's **eligibility rule set** (*Loan Products & Lenders → Eligibility rules*) | The DC matcher applies it unchanged |
| **N** | *Notes* or *Foreclosure / takeover restrictions* | **Not checked automatically.** The advisor must read it before shortlisting the lender. |

| Criterion (mandate §6) | Admin field | Where | Value | Source document, page/clause | Maker ☐ | Checker ☐ |
| --- | --- | --- | --- | --- | --- | --- |
| Lender offer | Lender offer | P | | | ☐ | ☐ |
| Product restrictions (which route the takeover loan is) | Financing route | P | | | ☐ | ☐ |
| Policy version | Version | P | | | ☐ | ☐ |
| Applicant type | Applicant types | P | | | ☐ | ☐ |
| Existing liability treatment / loan takeover policy | Debts it takes over (each liability type) | P | | | ☐ | ☐ |
| Secured / unsecured acceptance | Debts it takes over (secured types such as LAP, vehicle, gold) + Needs collateral | P | | | ☐ | ☐ |
| Eligibility exclusions: lenders it will not take over from | Will not take over loans from | P | | | ☐ | ☐ |
| Eligibility exclusions: its own loans | Takes over its own existing loans | P | | | ☐ | ☐ |
| Eligibility exclusions: arrears | Maximum days past due on any loan | P | | | ☐ | ☐ |
| Minimum / maximum loan amount (₹) | Min / max loan amount | P | | | ☐ | ☐ |
| Pricing: rate (% a year, reducing balance) | Rate from / up to | P | | | ☐ | ☐ |
| Pricing: fees | Processing fee % (any fixed fee, insurance or stamp duty → Notes) | P / N | | | ☐ | ☐ |
| Tenure (months) | Min / max tenure | P | | | ☐ | ☐ |
| Minimum income (₹ a month) | Minimum net monthly income | P | | | ☐ | ☐ |
| Maximum income (if the lender caps it) | — | N | | | ☐ | ☐ |
| Employment requirements (salaried / self-employed, employer category, job vintage) | Eligibility rule set: employment type, employer | E | | | ☐ | ☐ |
| Age limits | Eligibility rule set: age | E | | | ☐ | ☐ |
| Geographic restrictions (serviceable cities or pincodes) | Eligibility rule set: city | E | | | ☐ | ☐ |
| Credit score | Minimum credit score | P | | | ☐ | ☐ |
| FOIR | Maximum FOIR % | P | | | ☐ | ☐ |
| DSCR | Minimum DSCR | P | | | ☐ | ☐ |
| Business vintage | Minimum business vintage (months) | P | | | ☐ | ☐ |
| Turnover | Minimum annual turnover | P | | | ☐ | ☐ |
| Collateral | Needs collateral; accepted collateral types; maximum LTV % | P | | | ☐ | ☐ |
| Part-prepayment policy (will it fund a partial pay-down; minimum paid down) | Foreclosure / takeover restrictions | N | | | ☐ | ☐ |
| Foreclosure requirements (quotation validity, lock-in, who pays the old lender) | Foreclosure / takeover restrictions | N | | | ☐ | ☐ |
| Documentation requirements | Documents the lender needs | P (listed, not scored) | | | ☐ | ☐ |
| Source document | Source name, reference, link | P | | | ☐ | ☐ |
| Received on | Received on | P | | | ☐ | ☐ |
| Policy effective date | Effective from / until | P | | | ☐ | ☐ |
| Entered by | Stored automatically (`created_by`, audit log) | P | | | ☐ | ☐ |
| Second-level approval | *Approve* action by a different person (`approved_by`, `approved_at`) | P | | | ☐ | ☐ |
| Anything else the lender said | Notes | N | | | ☐ | ☐ |

**N rows.** These criteria are not checked automatically. If a lender has an N-type criterion that matters, record it in the register (`LP-C` P-09) so the advisor checks it by hand before shortlisting. Adding a dedicated field later would be an engineering change.

**Lender relationship.** Before go-live, confirm that FynnEdge has a signed DSA/LSP or referral agreement with this lender covering debt consolidation (depends on **LA-01**).
- Agreement reference:
- Date:
- Confirmed: ☐

| Role | Name | Date | Decision |
| --- | --- | --- | --- |
| Maker (entered in admin) | | | — |
| Checker (approved in admin) | | | Approve / Return to maker |

## 4. After approval

- [ ] The policy shows **Active**, with *Approved by* filled in.
- [ ] Run **Match lenders** on a **test case on a non-production copy**, not on a customer case, and check that each reason line matches the document.
- [ ] Record the policy in the register (`LP-C`, §3) with its version and effective dates.
- [ ] Set a review date. Re-check whenever the lender issues a new circular; when a policy expires (*effective until*), it stops matching automatically.
