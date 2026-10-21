---
paths:
  - 'app/Filament/Resources/Customers/**,app/Modules/Customers/Services/CustomersCsv.php,app/Modules/Customers/Services/CustomerLocation.php,app/Filament/Schemas/IndiaDateRangeFilter.php'
---

# Services Filament Schemas

## Customers CSV: masked PAN, bureau columns and filter gated by viewAny, IST dates
User decision (2026-10-21): Customers has "Download CSV" (filtered table) and "Download selected (CSV)" (bulk), via CustomersCsv. It exports pan_masked only; Customer::pan() is "never for display". The bureau columns and the Bureau filter's options are limited to bureaus the user can viewAny, as the table columns are. The filter also ignores client-sent keys outside that list, so it can't reveal that a hidden bureau has a request. Location = CustomerLocation (profile city/state/PIN, else the newest CIBIL/Experian/Equifax full report the user can viewReport; CRIF has no address parser). Date filters on admin lists use App\Filament\Schemas\IndiaDateRangeFilter, with the column shown ->timezone(IndiaDateRangeFilter::TIMEZONE). Pinned by CustomersTableTest and CustomersCsvTest.
