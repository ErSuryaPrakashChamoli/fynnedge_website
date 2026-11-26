# 34 — Go-live and activation checklist (gate G)

**For:** the Owner, who alone authorises switching the service on, and the people listed per row.

The service is switched on **only** when every row below is **Done** with its evidence. The pre-conditions are tracked in `30-RELEASE-GATE-MASTER-REGISTER.md`.

Switching on needs no deployment. It is the *Debt Consolidation is live* setting, plus publishing the approved pages.

## 1. Pre-conditions

| # | Pre-condition | Gate | Owner | Evidence | Done |
| --- | --- | --- | --- | --- | --- |
| G-01 | Code deployed and part B of the smoke test passed | F | Operations | `33-…` part B signed | ☐ |
| G-02 | LA-01 regulatory position decided (and any change it requires, such as the portal lender display, built and deployed) | A | Legal / Compliance | Signed decision; `24-…` A | ☐ |
| G-03 | LA-03 fee decision; "free" wording changed if a fee is charged | A | Legal + Owner | Signed decision | ☐ |
| G-04 | LA-02 consents, notices, disclaimers and warnings (BV-18) approved in English and Hindi, **entered in Settings**, and the consent version raised | A, B | Legal / DPO / Compliance | Approved texts; Settings screenshot | ☐ |
| G-05 | Privacy policy, terms and disclaimer updated for DC (`24-…` J) and published | A | Legal / DPO | Live page links | ☐ |
| G-06 | FA-01, FA-02, SC-01 and SC-02 approved (with BV-01 to BV-17), on HV-01 evidence or a documented alternative | A, B | Finance, Credit, Head of Advisory, Compliance | `17-…` rows signed | ☐ |
| G-07 | Production CMS content re-scanned for claims of guaranteed approval, savings or direct lending (`24-…` L) | A | Legal | Scan record | ☐ |
| G-08 | HV-00 decided, then HV-01 / BV-13 accepted (or Finance and Credit record that go-live may proceed without it, with their reasons) | C, B | Legal/DPO; Head of Advisory + Credit | `21-…` §10; `22-…` | ☐ |
| G-09 | LP-01: at least one real partner policy entered from a lender document, approved by a second person, and tested on a copy. Demo lenders confirmed absent or inactive (P-02). | A | Credit | `lender-policy/LP-C` | ☐ |
| G-10 | OP-01: team alert email set, SOPs approved (`25-…`, including SOP-05 = BV-08), advisors trained, retention (OD8) decided, SMS decision made | A, B | Operations + Owner | Training record; settings | ☐ |
| G-11 | Security: M3 erasure (anonymisation built 26 Nov 2026); INF-04 and INF-05 applied 26 Nov 2026. Remaining: the production `.env` must not set `SESSION_ENCRYPT=false`, and staging `nginx -t` must pass | A | Operations | `26-…`; `27-…` | ☐ |
| G-12 | Pages reviewed in preview by Marketing and Legal: hub, personal, business, how it works, analyser, assessment, FAQs, Savings Calculator (EN + HI) | A | Marketing + Legal | Review note | ☐ |

## 2. Activation (Owner, in a controlled window)

| # | Step | Done |
| --- | --- | --- |
| 2.1 | The Owner gives written go-live authorisation. Reference: __________ | ☐ |
| 2.2 | Publish the approved pages, each with its FAQs: in *Website Pages → General Pages* (leave *Published at* empty; the box is read in UTC), or all seven at once with `php artisan db:seed --class=PublishDebtConsolidationPagesSeeder --force` | ☐ |
| 2.3 | Choose the applicant types to accept (individual / business) | ☐ |
| 2.4 | Tick **Debt Consolidation is live** | ☐ |
| 2.5 | Run part C of `33-…` | ☐ |
| 2.6 | Watch for the first 7 days: new enquiries handled within the SOP-01 time limit; `failed_jobs` empty; no error spikes; the retention job ran | ☐ |

**To stop at any time:** untick *Debt Consolidation is live* (`32-…` §1).

## 3. Authorisation

| Role | Name | Decision | Date | Reference |
| --- | --- | --- | --- | --- |
| Owner: go-live (gate G) | | Approve / Defer | | |
