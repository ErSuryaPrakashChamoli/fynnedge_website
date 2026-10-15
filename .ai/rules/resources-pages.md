---
paths:
  - 'app/Filament/Resources/**/Pages/List*.php'
---

# Resources Pages

## Table filters holding PII must not be mirrored into the URL
ListRecords declares `#[Url(as: 'filters')] public ?array $tableFilters`, so every filter value lands in the address bar, browser history and nginx access logs. A list with a filter that takes a full PAN/mobile/etc. must redeclare `public ?array $tableFilters = null;` WITHOUT the attribute (see ListCrifReports) and match on a hash, never the plaintext column. Pinned by CrifReportAdminTest "does not read the PAN filter from the address bar".
