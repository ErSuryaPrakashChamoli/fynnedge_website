---
paths:
  - 'app/Support/Seo/**,app/Filament/Pages/Settings.php,resources/views/loans/**'
---

# Loans

## Organization schema is Settings-driven; loan pages carry FinancialProduct microdata
`App\Support\Seo\OrganizationSchema::build()` assembles the sitewide Organization node for the layout's `@graph` from `business_*` + `contact_*` Settings (address parts, areaServed one-per-line, priceRange, description). Every value is `filled()`-filtered away when blank — never hardcode a phone number, address or service area in a controller or view, and never let a blank Setting fabricate a default. `business_area_served` is newline-delimited text split by the class, not JSON.

The node is multi-typed `['Organization', 'FinancialService']` so LocalBusiness-level `areaServed`/`priceRange` are legal on it while keeping the `#organization` `@id` the rest of the graph references. Because of that, never assert it with `assertSee('"@type":"Organization"')` — decode the graph and assert on `@id`/types instead.

Loan product views (`show`, `show-flexi-hybrid`, `landing-page`) use `<article itemscope itemtype="https://schema.org/FinancialProduct">` as the outer wrapper (was `<section>`; nothing in app.css targets either element, and show-flexi-hybrid keeps its `id="flexi-hybrid-details"` jump-link anchor). Sections carry `data-ai-context="..."` labels. The FAQ accordion deliberately has NO microdata — the FAQPage JSON-LD already declares those Question/Answer entities and marking them up twice would double-declare them.

Trap when testing loan product pages: `LoanProductFactory` randomises `category`, and a hybrid category renders `loans.show-flexi-hybrid` instead of `loans.show`. Pin `'category' => LoanCategory::PersonalLoan` or the test is flaky.
