---
paths:
  - 'routes/external.php,app/Http/Controllers/Api/V1/ExperianController.php,app/Http/Requests/Api/V1/StoreExternalExperianCheckRequest.php,app/Http/Requests/Api/V1/StoreExperianCheckRequest.php'
---

# Api V1

## External API: the caller picks the bureau by URL; CIBIL and Experian stay mirrored
User decision (2026-10-17): other systems choose CIBIL or Experian by path — /api/v1/external/cibil/* and /api/v1/external/experian/* expose the same five endpoints (score, reports, reports/{id}, reports/{id}/pdf, requests/{id}/status), one API key serving both. A change to one bureau's external API must be mirrored in the other. Experian's external request takes name, mobile, PAN and recorded consent but NO gender (its API has none); the customer is matched by MatchCustomerForBureauRequest with gender null, so both bureaus' requests for one person share a customer. Each bureau has its own throttle (cibil-external-api, experian-external-api) and records channel 'external' via the controller's channel() — never hardcode 'api' in show/pdf.
