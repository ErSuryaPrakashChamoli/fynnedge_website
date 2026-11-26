# LP-C — Lender policy pending-information register

**For:** Credit (owner of LP-01) and the Owner. **Opened:** 26 Nov 2026. Update a row when information arrives; never delete a row.

## 1. Information needed from the business

| ID | Information needed | From | Needed for | Status | Evidence when received |
| --- | --- | --- | --- | --- | --- |
| P-01 | The list of partner lenders and offers that FynnEdge will use for debt consolidation, taken from **production** | Credit / Owner | LP-01; LP-A §2 | **PENDING** | |
| P-02 | Confirmation that the seeded "DEMO Lender Inc 342" and "DEMO Lender Ltd 595" entries (and any other DEMO lenders) are **not** in the production lender directory; or a decision to deactivate them before go-live | Operations | Prevents demo lenders from being matched | **PENDING** | |
| P-03 | For each partner: a signed DSA, LSP or referral agreement that covers debt consolidation or balance transfer | Owner / Legal | LA-01; LP-A §3 | **PENDING** | |
| P-04 | For each partner offer: its debt consolidation or balance-transfer policy document, including: debts taken over; own-loan and excluded-lender rules; DPD limit; amounts; rate; fee; tenure; income, FOIR, DSCR, score, vintage and turnover minimums; collateral and LTV; documents; foreclosure or lock-in rules; effective date | Each lender via Credit | LP-01 | **PENDING (0 received)** | |
| P-05 | Decision: keep the rate-card fallback for blank rate, amount or tenure fields on a DC policy, or make those fields mandatory (`LP-B` §4.1) | Credit | LP-01 | **PENDING** | |
| P-06 | Route matrix approval: which debt types each financing route can take over in principle (`config/debt_consolidation.php`) | Credit | BV-16, before LP-01 | **PENDING** | |
| P-07 | Names of the maker(s) and checker(s) for lender policies, with admin roles assigned (maker: Debt Consolidation Manager or Credit; checker: a different person) | Head of Advisory | LP-01 | **PENDING** | |
| P-08 | Review frequency for lender policies, and who watches lender circulars | Credit | Operations SOP-07 | **PENDING** | |
| P-09 | Per lender: criteria that the DC policy cannot hold and the matcher does not check (maximum income, part-prepayment rules, foreclosure/lock-in rules, fixed fees). Decide whether an advisor check by hand is enough, or a new field is needed (engineering change). | Credit | LP-01; `LP-A` §3 "N" rows | **PENDING** | |

## 2. Per-lender status

| Lender — offer | Agreement (P-03) | Policy document (P-04) | Entered (maker) | Approved (checker) | Version | Effective | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Bajaj Finance — Flexi Hybrid Term Loan | ☐ | ☐ | ☐ | ☐ | — | — | **UNVERIFIED** |
| Tata Capital — Flexi Hybrid Term Loan | ☐ | ☐ | ☐ | ☐ | — | — | **UNVERIFIED** |
| Aditya Birla Finance — Flexi Hybrid Term Loan | ☐ | ☐ | ☐ | ☐ | — | — | **UNVERIFIED** |
| Piramal Finance — Flexi Hybrid Term Loan | ☐ | ☐ | ☐ | ☐ | — | — | **UNVERIFIED** |
| *(add each production partner offer from P-01)* | | | | | | | |

**UNVERIFIED** means no lender document has been received and no policy is active. While a lender is UNVERIFIED, the system cannot match it, so no advisor or customer sees it as a match.

## 3. Policies in force

| Policy (label) | Version | Approved by / date | Effective from–until | Source | Next review |
| --- | --- | --- | --- | --- | --- |
| *(none)* | | | | | |
