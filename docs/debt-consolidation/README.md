# Debt Consolidation — documentation

**Executive summary.** FynnEdge now has a complete debt consolidation service built into the existing website, admin and customer portal: seven CMS-managed public pages; a six-view calculator suite; a quick enquiry and a full multi-step assessment for individuals and businesses (OTP, save and resume, any number of loans, full or partial consolidation); a deterministic, versioned financial engine (foreclosure, savings, affordability, break-even, portfolio, suitability score) whose every result is stored as an immutable snapshot; rule-based lender matching on the existing lenders; consent-gated lender applications with independent status tracking; evidence-based closure and NOC tracking; eight branded PDF reports; a customer portal section; an admin module with a 360° case view; and a read-only API prepared for FynnAI. No OCR or AI is included. Everything is additive: no existing table, URL or feature was removed. One visible change is intended: at the owner's request (25 Nov 2026) the service is given preference across the website once it is live — header strip, main navigation straight after Loans (where it takes the place of the desktop "Eligibility" link, which duplicates the "Check Eligibility" button beside it), top of the mobile menu, footer and a homepage feature under the hero. The service ships **switched off** with its pages in **draft**; launch needs lender policies, legal sign-off on wording, and publishing (see `12-deployment.md`).

| Doc | Contents |
| --- | --- |
| `00-project-status.md` | Master status, decisions, dependencies, defects |
| `01-existing-system-audit.md` | Phase 0 audit and reuse map |
| `02-functional-requirements.md` | Requirements traceability |
| `03-architecture.md` | Architecture and decisions |
| `04-customer-journey.md` | Journey and notifications |
| `05-database-design.md` | Tables and columns |
| `06-financial-engine.md` | Formulas and rules |
| `07-api.md` | API / FynnAI integration point |
| `08-testing-report.md` | Test results |
| `09-security-compliance.md` | Controls and regulatory assumptions |
| `10-cms-integration.md` | Pages, editing, SEO |
| `11-admin-documentation.md` | Admin screens and roles |
| `12-deployment.md` | Deployment and rollback |
| `13-operational-user-guide.md` | Advisor procedures |
| `14-calculators-and-matching.md` | Calculator and matching specs |
| `15-final-completion-report.md` | Completion report |
| `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` | **Pre-production acceptance: result, defects fixed, business approval register, deployment readiness** |
| `validation/` | Financial validation workbook (`DC-financial-validation-workbook.xlsx`), cases, engine harness, independent reference, comparison |
