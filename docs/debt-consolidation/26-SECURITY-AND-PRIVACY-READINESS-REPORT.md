# 26 — Security and privacy readiness report

**For:** the Owner, the tech lead and the DPO.
**Date:** 26 Nov 2026.
**Scope:**
- the Debt Consolidation vertical;
- where it connects to the existing site: customer portal, OTP, API clients, admin roles, storage and queue.

This is an engineering review with test evidence. **It is not a security certification, a penetration test or a compliance attestation.**

## 1. Result

| Measure | Result |
| --- | --- |
| Method | Code review of every DC controller, Livewire component, Filament resource and relation manager, policy, action, notification and route, plus the shared portal, OTP and API-client code. Each suspected weakness was **probed with a test** on in-memory SQLite (never the local or production database). |
| Findings | 1 High, 3 Medium, 4 Low, 1 Informational. An independent read-only review of the post-acceptance changes added L3 and L4. |
| Fixed, with regression tests | **H1, M1, M2, L1, L2 (queued email), L3**. Each new test was confirmed to fail on the old code and pass on the fixed code. |
| Open | **M3** (personal-data erasure): awaits a Legal/DPO decision on what may be deleted. **L2 (sessions)** and the site-wide part of **M2**: production configuration, an Owner decision (`27-…`). |
| Customer isolation | **Holds.** One customer cannot reach another customer's case, liabilities, documents, reports, consents or matches (§3). |

## 2. Findings register

| ID | Severity | Finding | Evidence | Resolution | Status |
| --- | --- | --- | --- | --- | --- |
| H1 | **High** | Staff document upload trusted a file path sent by the browser. An advisor could import **any** file on the private disk into a case and then delete the original, for example another case's encrypted report, or the unencrypted KYC files of the loan application journey (`applications/…`). | Probe: an advisor imported `applications/77/aadhaar.pdf` and deleted it; another probe erased another case's report. Filament's `FileUpload` passes string paths through unless `preventFilePathTampering()` is set. | `DocumentsRelationManager`: the field uses `storeFiles(false)` + `preventFilePathTampering()`, so the action receives only the uploaded temporary file. Anything else is refused, and only that temporary file is deleted afterwards. | **FIXED.** Tests: *stores a document an advisor uploads, encrypted, on that case*; *refuses a file path sent in place of an upload and leaves that file untouched* (2 cases). Both fail on the old code. |
| M1 | Medium | The public calculator and the application auto-save accepted any number of loan rows (the 20-row limit existed only on the "add" button). One anonymous request with 2,000 rows used 7.2 s and 221 MB of a 256 MB limit; auto-save stored 300 rows on one case. | Probes 3 and 4 | `LiabilityRows::limit()` (first 20 rows) is used by `toInputs()` and `SaveDebtApplicationDraft`. Both components cut the row list back when it is updated. | **FIXED.** Tests: *works on at most the allowed number of loans, whatever a request sends* (calculator); *saves at most the allowed number of loans…* (application). Both fail on the old code. |
| M2 | Medium | Links in DC emails (staff "Open the case", customer "View my case") were built from the request's `Host` header. With nginx `server_name _` and no trusted-host setting, a forged header produces a genuine FynnEdge email that links to a look-alike site. | Probe 6 | DC emails now build links from `APP_URL` (`DebtCaseNotifier::siteUrl`). **Site-wide** (other modules' emails, password resets): restrict accepted hosts in nginx (`server_name fynnedge.com www.fynnedge.com` plus a default server that returns 444), or use Laravel `trustHosts`. This is a production configuration change, so it is an Owner decision. | DC part **FIXED** (test: *links every email to the site address…*, fails on the old code). Site-wide part **PENDING APPROVAL** (`27-…` §7, INF-04). |
| M3 | Medium (privacy) | The retention job withdraws and **soft-deletes** abandoned drafts, so the personal data and stored files remain. Nothing in the system can erase a customer's DC data. | `DebtConsolidationEnforceRetentionCommand`; no role holds ForceDelete | Proposed design for Legal/DPO (§5). **Not implemented**, because deciding what to erase (including consent records, which are evidence) is a legal decision. | **PENDING APPROVAL** (Legal/DPO; linked to OP-01 retention OD8 and LA-02) |
| L1 | Low | The case page showed the full analysis (assessment, suitability, savings, foreclosure) to anyone allowed to view the case, without the calculation-snapshot permission. The report download did not check the per-report `view` permission. | Code review | Analysis tabs need `ViewAny:DebtCalculationSnapshot`; download needs `view` on the report. The overview headline figures stay part of viewing a case (by design). | **FIXED.** Test: *shows the full analysis and report downloads only to staff allowed to see them* (fails without either gate). |
| L2 | Low | The customer's email address was stored in plain text in the queued job (`jobs` / `failed_jobs`, database queue). Calculator inputs are kept in database sessions with `SESSION_ENCRYPT=false`. | Code review; test | `DebtCaseUpdate` implements `ShouldBeEncrypted`. Sessions: set `SESSION_ENCRYPT=true` in production (site-wide setting; signs everyone out once). | Queue **FIXED** (test: *keeps the customer's email address encrypted while their case update waits in the queue*). Sessions: **applied** as the `docker-compose.yml` default (Owner, 26 Nov 2026); Operations must make sure the production `.env` does not set `SESSION_ENCRYPT=false` |
| L3 | Low | The analyser's comparison options were not cut back like the loan rows (M1), so a tampered request could make the page draw thousands of option cards (independent review) | `updatedScenarios()` keeps the first 3 options | **FIXED** (test: *keeps at most the allowed number of options…*) |
| L4 | Low | Without the snapshot permission, staff still see the case overview's headline figures (net benefit, suitability) and the case list's total debt and band; report PDFs need only the report permission (independent review) | By design: headline figures are part of viewing a case; reports have their own permission (`View:DebtReport`). Seeded roles hold both. Custom roles must be granted with this in mind. | **ACCEPTED** (documented) |
| I1 | Info | A case that staff opened by phone is attached to whoever next proves that mobile number by OTP. A mistyped or recycled number would expose the case to the wrong person. | `OpenDebtApplication` | By design (staff-opened cases need a way to reach the customer). Mitigation in SOP-02: read the number back to the caller. | **ACCEPTED**: operational control (`25-…` SOP-02) |

## 3. Controls verified

| Area | What was checked | Evidence |
| --- | --- | --- |
| Authentication | Portal routes behind `auth:customer` and `customer.portal`, throttled at 60/min, `no-store`, `noindex`; session ID regenerated at sign-in | `routes/web.php`; `LogInCustomer`; portal tests |
| Customer data isolation | Every lookup goes through `ownedBy` (linked account plus OTP-verified mobile). Documents, reports, consents and matches are queried through the case. Another customer's case, document or report returns 404. | `DebtConsolidationPortalTest` (12 tests) |
| Livewire tampering | Case ID, OTP challenge ID and the challenged number are `#[Locked]`. Every action re-checks ownership. | Probe 4 (`CannotUpdateLockedPropertyException`); *never lets another signed-in customer act on a case* |
| Authorisation and roles | Shield permissions per model. Debt Advisor cannot approve policies, activate scoring, delete, or open settings. Marketing is kept out of cases. Role edits keep every DC permission (D-01). | Role tests; admin tests |
| Restricted financial documents | Document download needs `view` on the document. Report download needs `view` on the report (L1). Analysis tabs need the snapshot permission (L1). Every download is logged. | Admin tests |
| Private storage | Documents and reports are encrypted at rest (`Crypt`) under random paths on the private `local` disk. The disk's serve route needs a signature and nothing generates one, so signed URLs are not used and not needed. | `UploadDebtDocument`, `GenerateDebtReport` |
| File upload validation | Customer uploads are checked for size (10 MB), extension, MIME sniffed from the bytes (PDF/JPG/PNG), document-type allow-list and liability ownership. Staff uploads get the same checks plus H1. | Upload tests |
| CSRF | Only `language/*` is exempt | `bootstrap/app.php` |
| XSS | The only unescaped output is the sanitised page body (`HtmlBodyEditor`) and translation strings whose parameters go through `e()`. The PDF is Blade-escaped, with dompdf remote access off. | Code review |
| SQL injection | Only one static `selectRaw`; everything else uses the query builder | Code review |
| Mass assignment | Every model has `#[Fillable]`; `forceFill` only receives server-built arrays | Code review |
| Rate limiting | OTP sends limited to 5 per number and 20 per IP per hour; wrong codes to 10 per IP per 15 min. Calculator snapshot saves limited. API limited to 60/min per client. Input size capped (M1). | Enquiry tests; calculator tests; API tests |
| OTP | CSPRNG codes, stored hashed, 10-minute expiry, 5 attempts under a row lock, single use, bound to the number and purpose. The public OTP API refuses the DC purpose. | Enquiry tests (10) |
| API | Key required (`auth:api-client`). Permission checked **before** the reference is looked up. Consent to external analysis required. No contact details returned. Every read logged. | `DebtConsolidationApiTest` (6) |
| Audit logging | `Auditable` on case data (identity fields excluded via `auditExcept`); status changes insert-only; case events carry public IDs only; audit view needs `View:AuditLog` | Admin tests |
| Sensitive data exposure | Staff emails carry no personal data. Customer emails carry the title and message only. The module writes no `Log::` calls. Customer email is encrypted in the queue (L2). | Code review; L2 test |
| Queue security | Database queue; the worker runs as `www-data`; customer notification encrypted | `supervisord.conf`; L2 |
| Error logging | Exceptions are reported through Laravel's handler, with no personal data added by the module. Production `APP_DEBUG` must be `false` (`33-…` smoke test S-03). | Code review |
| Secrets | No secrets in tracked files or commit `434ec8e`. `.env.example` and `phpunit.xml` have blank tokens. The seeder generates a random admin password. The entrypoint refuses to start without `APP_KEY`. | `git grep` review (`29-…` §6) |
| Backup security | **Every stored document and report depends on `APP_KEY`.** Back up the key separately from the database and files; losing it makes every DC document unreadable. `APP_PREVIOUS_KEYS` allows rotation. | `09-security-compliance.md` §2; PR-05 |
| Retention and deletion | Drafts are withdrawn after 180 days; anonymous calculator snapshots deleted after 90 days. **Erasure: see M3.** | Retention tests |
| OCR / AI | None implemented. No OCR, extraction, classification or AI scoring code, workers, tables or APIs. The DC API is read-only structured data only, with no dependency on FynnAI. | Code review |

## 4. Residual risks accepted for now (Owner to note)

1. **`APP_KEY` is a single point of failure** for every encrypted document. Mitigation: a separate backup (PR-05, `32-…`).
2. **Declared credit score.** The customer types it in; it is not bureau-verified. Every screen and reason line says so.
3. **Staff can download decrypted documents.** This is necessary for their work. Each download is logged with who and when.

## 5. Proposed erasure design for M3 (for the Legal/DPO decision)

| Question for Legal/DPO | Options |
| --- | --- |
| What happens to an abandoned draft after the retention period? | (a) Keep the soft-delete (today). (b) **Anonymise**: blank name, email, mobile and financial profile; delete liabilities and stored files; keep a skeleton row with dates. (c) Hard-delete everything. |
| Consent records on drafts | Keep them as evidence for N years, or delete them with the draft |
| Customer erasure request (DPDP) | An admin action "Erase customer's DC data", run by a second person, applying the same choice as above. Submitted cases subject to a lender or legal hold are exempt. |
| Submitted cases | Keep under the retention period set for OD8; nothing deleted automatically until decided |

Once Legal/DPO decide, engineering implements the chosen option with tests. Estimated effort: one working day.

## 6. Status for the final report

**Security and privacy readiness: PENDING APPROVAL.**
- The code-level findings are fixed and tested.
- Still open:
  - M3: needs a Legal/DPO decision;
  - site-wide host restriction and session encryption: need an Owner decision on production configuration.
- No High or Critical finding is open.
