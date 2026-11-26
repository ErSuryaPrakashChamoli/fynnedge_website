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
| `17-BUSINESS-APPROVAL-TRACKER.md` | **Every approval and BV item, decision, approver, evidence, status, dependencies** |
| `18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md` | How to run the BV-13 parallel run on past cases |
| `19-BUSINESS-SIGN-OFF-CHECKLIST.md` | Per-function approval checklists |
| `20-REGRESSION-CLOSURE-REPORT.md` | Full regression on the release candidate vs the clean baseline (PR-01) |
| `21-HV-00-DECISION-PACK.md` | Legal/DPO decision on using redacted past-case documents |
| `22-HISTORICAL-VALIDATION-EXECUTION-REPORT.md` | Historical validation: infrastructure ready, blocked on HV-00 |
| `23-FINANCIAL-AND-CREDIT-VALIDATION-REPORT.md` | BV-01 to BV-18: implementation, evidence, approver, missing evidence |
| `24-LEGAL-AND-REGULATORY-READINESS-REPORT.md` | Legal decision pack A–L with DRAFT wording; website claims scan |
| `25-OPERATIONS-READINESS-REPORT.md` | Operations capability check and 13 SOPs |
| `26-SECURITY-AND-PRIVACY-READINESS-REPORT.md` | Security and privacy audit, findings register |
| `27-PRODUCTION-INFRASTRUCTURE-READINESS.md` | Docker, migrations risk and rehearsal, PR-01 to PR-05, infrastructure findings |
| `28-WEBSITE-AND-CUSTOMER-EXPERIENCE-REPORT.md` | Pages, links, SEO, responsive and accessibility checks; off-state proof |
| `29-FINAL-PRODUCTION-READINESS-REPORT.md` | **Final assessment of the 11 readiness areas; code review; release candidate** |
| `30-RELEASE-GATE-MASTER-REGISTER.md` | **Single matrix of gates A–G** |
| `31-DEPLOYMENT-RUNBOOK.md` | Step-by-step deployment with the service off |
| `32-ROLLBACK-AND-RECOVERY-PLAN.md` | Rollback levels 1–5 and recovery scenarios |
| `33-PRODUCTION-SMOKE-TEST-CHECKLIST.md` | Staging, post-deployment (off) and go-live smoke tests |
| `34-GO-LIVE-AND-ACTIVATION-CHECKLIST.md` | Pre-conditions and steps for switching the service on (gate G) |
| `35-KNOWN-LIMITATIONS-AND-RISKS.md` | Limitations, release risks, pre-existing defects |
| `evidence/` | Durable test evidence: full-suite outputs for the release candidate (run 8, plus the one-file follow-up) and the clean baseline `084825b` |
| `lender-policy/` | Lender policy configuration checklist, validation report, pending-information register |
| `release/` | Proposed (not applied) patches: explicit migration entrypoint; Docker PHP runtime; sticky enquiry panels (W-03) |
| `validation/` | Financial validation workbook (`DC-financial-validation-workbook.xlsx`), cases, engine harness, independent reference, comparison |
| `validation/historical/` | Historical validation workbook (empty), case-pack template, harness (refuses to run without HV-00) |
