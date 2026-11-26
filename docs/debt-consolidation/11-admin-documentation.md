# 11 — Admin documentation

Sidebar: **Operations → Debt Consolidation** (purple accent). Every screen has the built-in "About this screen" help.

| Tab | URL | Purpose |
| --- | --- | --- |
| Dashboard | `/admin/debt-consolidation-dashboard` | 12 KPIs (enquiries → completed), follow-ups due, quotations expiring |
| Enquiries | `/admin/debt-consolidation-enquiries` | Short enquiries waiting for a call-back |
| Applications | `/admin/debt-applications` | All cases; tabs Open / Submitted / Drafts / Closed / All; "Open a case for a customer" |
| Customers | `/admin/debt-consolidation-customers` | Existing customer records that have a case |
| Case work ▾ Liabilities | `/admin/debt-liabilities` | Verify and correct liabilities across cases; record quotations |
| Case work ▾ Financial Assessments | `/admin/debt-assessments` | Every stored calculation (immutable) |
| Case work ▾ Foreclosure Analysis | `/admin/debt-foreclosure-quotations` | Quotations: verify, watch expiry |
| Case work ▾ Savings Comparison | `/admin/debt-scenarios` | Proposed consolidation options |
| Lenders & closure ▾ Lender Matching | `/admin/debt-lender-matches` | Match results with reasons; advisor review |
| Lenders & closure ▾ Lender Applications | `/admin/debt-lender-applications` | Lender statuses ("Record lender update") |
| Lenders & closure ▾ Debt Closure | `/admin/debt-closures` | Payments, NOCs, verification |
| Reports | `/admin/debt-reports` | Download, release to customer |
| Scoring Configuration | `/admin/debt-scoring-configs` | Versioned suitability weights/thresholds (draft → activate by a second person) |

Elsewhere: **Loan Products & Lenders → Eligibility ▾ Debt Consolidation Policies** (`/admin/debt-lender-policies`); **Website Settings → Debt Consolidation ▾ Debt Consolidation Settings / Debt Consolidation Pages**; the seven pages in **Website Pages → General Pages**.

## Case view (`/admin/debt-applications/{id}`)

Header: **Recalculate** (new snapshot), **Match lenders**, **Change status**, **More ▾** (Assign / follow-up, Log communication, Record lender-sharing consent, New lender application, Generate report).

Tabs: Overview · Customer profile · Existing liabilities · Documents · Financial assessment · Suitability score · Savings comparison · Foreclosure analysis · Lender matching · Lender applications · Debt closure · Communication · Activity timeline · Audit history · Collateral · Scenarios · Calculation history · Consents · Reports · Status history. Links: `?relation=<tab>`.

## Roles

| Role | Can |
| --- | --- |
| super_admin | everything |
| Debt Advisor (new) | work cases end to end; view lender policies and scoring; view customers |
| Marketing | Debt Consolidation Pages wording; the seven pages (as General Pages) |
| Ungranted by default | delete documents, create/edit/approve lender policies, create/activate scoring, Debt Consolidation Settings — grant in Administration → Roles (Approve and Activate are on the lender-policy and scoring-configuration rows; documents, notes, collateral and consents are on the **Custom permissions** tab) |

Each case-page action also needs the permission for what it creates: **Recalculate** needs Create on assessments, **Match lenders** Create on lender matches, **Log communication** Create on notes, **Record lender-sharing consent** Create on consents, **New lender application** Create on lender applications. Confirming a closure payment needs Update on Debt Closure. Notes appear in the activity timeline only for staff who may list them.
