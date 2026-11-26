---
paths:
  - 'app/Filament/Resources/**/Pages/List*.php'
  - 'app/Filament/Resources/**/Pages/View*.php'
---

# Resources Pages

## Table filters holding PII must not be mirrored into the URL
ListRecords declares `#[Url(as: 'filters')] public ?array $tableFilters`, so every filter value lands in the address bar, browser history and nginx access logs. A list with a filter that takes a full PAN/mobile/etc. must redeclare `public ?array $tableFilters = null;` WITHOUT the attribute (see ListCrifReports) and match on a hash, never the plaintext column. Pinned by CrifReportAdminTest "does not read the PAN filter from the address bar".

## Filament 5.7.6 has no getTabComponents(); mix content and relation-manager tabs in getRelationManagersContentComponent()
The 5.x docs describe getTabComponents()/getRelationManagerTabComponent(), but the installed 5.7.6 silently ignores an override of getTabComponents() (default tabs render). Override getRelationManagersContentComponent() and return Tabs::make()->key('relationManagerTabs')->livewireProperty('activeRelationManager')->contained(false)->tabs([...]) built from getCachedRelationManagers() + normalizeRelationManagerClass() — see ViewDebtApplication. Keep hasCombinedRelationManagerTabsWithContent() true. Pin with a test asserting tab-specific text, not just a 200.
