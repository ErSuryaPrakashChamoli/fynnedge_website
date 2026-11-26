# 07 — API (FynnAI-ready integration point)

FynnAI is **not** implemented. This read-only endpoint is the prepared integration point.

`GET /api/v1/external/debt-consolidation/cases/{reference}` — `reference` like `DC-7K3M9QX2`.

| Requirement | How |
| --- | --- |
| Service authentication | `Authorization: Bearer fyn_…` key issued in Administration → API Clients (hashed, IP allow-list, revocable); signs in as the client's service account |
| Permission | The service account's role must allow `View:DebtApplication` (e.g. a dedicated role with only that) |
| Customer consent | An active `external_analysis` consent on the case (wording in settings; no screen collects it yet — add it to the portal when the service exists) |
| Rate limit | 60 requests/minute per client |
| Audit | Each read writes `case_read_by_api_client` to the case's activity log |

Responses: `200` with `data` (below); `401` bad key; `403` no permission, or `{"error":{"code":"consent_required"}}`; `404` `{"error":{"code":"not_found"}}`.

`data` contains: reference, applicant type, status, city, submitted_at; liabilities (id, type, lender, secured, outstanding, rate, monthly payment, remaining months, standing, consolidation mode, verification status); the latest assessment (id, time, formula and scoring versions, input hash, full `results` as stored); lender applications (lender, product, status, sanctioned and disbursed amounts); closures (liability id, type, status, verified time); a disclaimer. It never contains name, mobile, email, PAN, loan/card numbers or documents.

**Boundaries for future AI use.** Figures and lender decisions remain authoritative in this system. An AI service may explain `results` but must not recompute, alter or present them as lender decisions. Any future write-back (e.g. explanations) should be a separate endpoint storing text against a snapshot id, with its own consent purpose.
