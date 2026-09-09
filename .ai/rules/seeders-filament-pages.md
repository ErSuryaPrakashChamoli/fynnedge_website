---
paths:
  - 'app/Support/Seo/OrganizationSchema.php,database/seeders/BusinessProfileSeeder.php,app/Filament/Pages/Settings.php'
---

# Seeders Filament Pages

## business_* Settings must be populated or the Organization schema silently ships with no address
`OrganizationSchema::build()` filters away every blank Setting, which is correct (it can't fabricate) but means an unconfigured environment emits an Organization/FinancialService node with only name/url/logo/telephone/email — no address, no areaServed, no description. That is invisible: every schema test still passes, because they set the Settings themselves first. This shipped live for a while; the address existed only as the free-text `contact_address` Setting the footer renders, never split into the PostalAddress parts the schema needs.

`BusinessProfileSeeder` now fills the structured address parts + description from the same approved registered-office address, and only where the Setting is blank, so re-running can never overwrite an admin's edit. It deliberately leaves `business_area_served` and `business_price_range` blank — both are public business claims (FynnEdge is a DSA/advisory, not the lender, so a loan price range isn't its to state). Don't "complete" the seeder by inventing those.

When adding a new business_* Setting, check whether a fresh environment leaves it blank — a passing test suite does not prove the live page carries the value.
