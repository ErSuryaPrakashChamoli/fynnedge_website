# 03 — Architecture and design decisions

Decisions are based on the Phase 0 audit (`01-existing-system-audit.md`). Each decision says what is reused, what is extended and why anything new exists.

## 1. Module boundaries

```
app/Modules/DebtConsolidation/
  Enums/                ApplicantType, LiabilityType, RepaymentStructure, ConsolidationMode,
                        DebtApplicationStatus, LenderApplicationStatus, ClosureStatus, …
  Services/Finance/     The financial engine (pure PHP, no database, no clock):
                        Amortisation, LiabilityProjector, ForeclosureEstimator,
                        ConsolidationComparator, AffordabilityCalculator, PortfolioAnalyser,
                        SuitabilityScorer, FormulaVersion
  DataTransferObjects/  Engine inputs/outputs (readonly) + ScoringConfig
  Models/               Eloquent models for the debt_* tables
  Actions/              Use cases (StartDebtApplication, SaveLiabilities, SubmitDebtApplication,
                        AssessDebtApplication, MatchLenders, RecordConsent, UploadDebtDocument,
                        UpdateClosureStatus, GenerateDebtReport, …)
  Services/             Orchestration (CaseInputBuilder, LenderMatcher, ReportRenderer, …)
  Policies/             Staff (Shield) policies, one per model
  Notifications/        Customer + staff notifications
```

| Responsibility | Owner | Editable by |
| --- | --- | --- |
| Page wording, FAQs, SEO, publish/preview | `pages` rows (Website Pages → General Pages) + Debt Consolidation Pages settings | Marketing / SEO roles |
| Service switches, availability, disclaimers, calculator visibility | Website Settings → Debt Consolidation Settings | Admin |
| Calculations | `Services/Finance` (code, versioned by `FormulaVersion`) | Developers only |
| Suitability weights/thresholds | `debt_scoring_configs` (versioned, draft → active) | Debt Consolidation managers |
| Lender rules | `debt_lender_policies` (per existing `lender_products` row, versioned, effective-dated, sourced) + existing eligibility rule sets | Loan Products & Lenders |
| Operations (cases, matching, lender applications, closures, reports) | Debt Consolidation admin module | Debt Advisor role |

Content editors never see formulas or scoring thresholds.

## 2. Key decisions

| # | Decision | Alternatives considered | Why |
| --- | --- | --- | --- |
| D1 | URLs without trailing slash (`/debt-consolidation`, `/debt-consolidation/how-it-works`, …); slashed form 301s | Serve slashed URLs | Sitewide canonical convention (`HandleRedirects`); changing it would move every canonical on the site. |
| D2 | Each DC URL is a `pages` row served by an explicit named route; slugs locked in the editor; the flat slug (e.g. `debt-consolidation-how-it-works`) never answers at `/{slug}` | New CMS table; page builder | Mandate: reuse the General Page editor (draft, schedule, preview, SEO, FAQs, Hindi, history). |
| D3 | Section wording (hero badge, CTAs, trust points, steps, embeds on/off) in a settings screen, like the About page | JSON blocks column on `pages` | Same pattern as `AboutPageContent`; no page builder. |
| D4 | Simple enquiries are written through `RecordEnquiry` with a new `EnquiryType::DebtConsolidation` and also open a `debt_applications` row at "Enquiry received" | Separate DC enquiry table only | Every DC lead reaches Lead Inbox, Lead Analytics and FynnOn LMS with no new lead source. DC enquiries dedupe only against earlier DC enquiries for the number. |
| D5 | Dedicated multi-step Livewire journey | Journey module | Journey cannot collect a variable list of liabilities and requires a loan product. |
| D6 | Mobile OTP purpose `debt_consolidation`; after verification the visitor is signed in to the existing customer portal (`ResolveCustomerAccount::forVerifiedMobile` + `LogInCustomer`) | Separate DC login | Same as the credit-score check; resume = portal. |
| D7 | DC lender rules attach to existing `lender_products` (`debt_lender_policies`) and reuse the lender product's active eligibility rule set | DC lender directory | No duplicate lenders; existing rules keep working. |
| D8 | DC documents in `debt_documents`, typed by the existing `document_types`, encrypted at rest on the private disk, versioned (never overwritten) | `application_documents` | That table is keyed to lender-product applications and overwrites files in place. |
| D9 | Calculations are snapshots (`debt_calculation_snapshots`): inputs, results, formula version, scoring config version, input hash. Reports render only from a snapshot | Recompute on view | Reproducible reports; old reports never change when formulas or data change. |
| D10 | Money in IEEE doubles, rounded to the paisa at every amortisation period, totals summed from rounded periods | bcmath (not installed) | Deterministic for a given input; matches how lenders round each instalment. Adding a PHP extension needs approval. |
| D11 | Two independent status tracks: FynnEdge case status and one status per lender application; closure status per liability | One status | Mandate §15. |
| D12 | A liability is marked closed only by `ClosureVerified`, which requires confirmed payment and closure evidence (NOC/closure letter for full closure) — never by disbursement | Auto-close on disbursement | Mandate §15. |
| D13 | PDFs rendered by the existing dompdf, English only, stored encrypted on the private disk with SHA-256 | On the fly | Reproducible, auditable reports; matches bureau PDF storage. |
| D14 | Staff permissions through Shield policies; new `Debt Advisor` role granted by migration (deploys run migrations, not seeders) | — | Existing `*Roles::grant()` pattern. |
| D15 | Read-only versioned API `GET /api/v1/external/debt-consolidation/applications/{reference}` on the existing `api-client` guard, gated by permission **and** a recorded customer consent for external analysis | No API | Prepares FynnAI without implementing AI. |

## 3. Data model

All tables are new and additive (`debt_` prefix). Every foreign key into an existing table is nullable or restrict-on-delete as noted, so no existing row can be blocked or changed by this module.

| Table | Purpose | Key relations |
| --- | --- | --- |
| `debt_applications` | The case: applicant type, contact, verified mobile, FynnEdge status, assignment, follow-up, attribution, reference | `customer_id` → customers (null on delete), `contact_enquiry_id` → contact_enquiries (null on delete), `assigned_to` → users |
| `debt_financial_profiles` | 1:1 income/employment (individual) or cash-flow/business details | application (cascade) |
| `debt_liabilities` | Each existing debt with consolidation mode (full/partial/exclude) | application (cascade), `lender_id` → lenders (null on delete) |
| `debt_collaterals` | Security offered for secured routes | application (cascade) |
| `debt_foreclosure_quotations` | Lender foreclosure letters per liability with validity and verification | liability (cascade), document |
| `debt_scenarios` | Proposed financing scenarios (inputs) incl. per-scenario inclusion overrides | application, `lender_product_id` → lender_products (null on delete) |
| `debt_calculation_snapshots` | Immutable calculation records (inputs, results, versions, hash) | application (nullable) , scoring config |
| `debt_scoring_configs` | Versioned suitability weights/thresholds per applicant type | — |
| `debt_lender_policies` | DC criteria per lender product, versioned, effective-dated, with source | `lender_product_id` → lender_products (cascade) |
| `debt_lender_matches` | Matching result per case × lender product, reasons, advisor review | application, policy, lender product |
| `debt_lender_applications` | Each application facilitated to a lender, independent status | application, lender product, consent |
| `debt_closures` | Closure tracking per liability (full/partial) | application, liability (unique), lender application, quotation, NOC document |
| `debt_closure_payments` | Payments towards a closure with evidence | closure, document |
| `debt_documents` | Uploaded documents (versioned, encrypted) | application, liability, closure, `document_type_id` → document_types |
| `debt_reports` | Generated PDF reports (type, version, snapshot) | application, snapshot |
| `debt_consents` | Purpose-specific consents with exact wording hash | application, customer, lender product |
| `debt_notes` | Communication log, internal remarks, follow-ups | application, user |
| `debt_status_changes` | Status history for cases, lender applications and closures (morph) | user |

Customer notifications use the existing `notifications` table (Customer becomes `Notifiable`). Activity history uses the existing `audit_logs`.

Full column lists: `05-database-design.md`.

## 4. Dependency map and build order

```
Enums ─► Financial engine (pure) ─► Snapshots/Assessment action ─► Reports
   │                                     ▲
   └─► Migrations/Models ─► Journey (OTP, customer, enquiry) ─┘
                        ├─► Documents ─► Closure tracking
                        ├─► Lender policies ─► Matching ─► Lender applications ─► Closures
                        └─► Admin module (dashboard, 360° view) ─► Portal ─► API
CMS pages + settings ─► Public pages ─► Calculators (engine) + embeds
```

1. Engine + exhaustive unit tests. 2. Schema, models, factories, policies, roles. 3. Assessment/snapshot actions. 4. CMS pages, settings, routes, SEO, navigation. 5. Calculator suite. 6. Enquiry + application journey. 7. Documents. 8. Lender policies + matching + lender applications. 9. Closures. 10. Reports. 11. Admin module. 12. Portal. 13. Notifications. 14. API. 15. Regression, security, docs, readiness.
