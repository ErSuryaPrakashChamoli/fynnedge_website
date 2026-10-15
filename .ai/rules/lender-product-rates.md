---
paths:
  - 'app/Filament/Imports/**,app/Filament/Resources/LenderProductRates/**'
---

# Lender Product Rates

## A synchronous Filament import signs the request's guard out — keep auth lookups null-safe after it
Filament's ImportCsv job ends with auth()->forgetGuards() even when it runs 'sync' inside the upload request (our importers do, up to 1000 rows). Anything the page renders afterwards that calls Auth::user()->… without a null check fails (in tests, where actingAs has no session, always; in the browser the session usually re-resolves the user). RateWorkflowActions::user() is ?User and visibility checks use (bool) self::user()?->can(…); actions that change data go through actor(), which refuses with AuthorizationException. Also: the import job runs a whole chunk in ONE transaction and does not roll back a failed row's partial writes, so LenderProductRateImporter checks every row (RatePublicationRules, permission) in beforeSave and builds new versions unsaved until then. "Upload verified rates" requires per-row evidence (https lender link, quote, checked_on) plus an upload-wide confirmation; there is still no way to mark rates verified without evidence.
