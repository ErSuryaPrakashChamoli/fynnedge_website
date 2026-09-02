---
paths:
  - 'app/Models/MarketingSection.php,app/Models/NavigationLink.php,resources/views/home.blade.php,resources/views/components/site/footer.blade.php'
---

# Components Site

## MarketingSection placements and NavigationLink are additive, never replace hardcoded content
`MarketingSection::forPlacement($key)` returns null when nothing is published for that key — every call site in home.blade.php falls back to the original hardcoded heading/copy/button, so an empty table renders identically to before this feature existed. Current placements: home_finance_cta, home_emi_cta, home_final_cta (see MarketingSectionForm's Select options — add new placement keys there, then branch on them at the call site). `NavigationLink` (location='footer' only, others not yet wired) renders as an ADDITIONAL "Quick Links" column in footer.blade.php, never replacing the existing hardcoded Company/Legal columns or header mega-menus (those stay hardcoded — too risky/route-coupled for a generic link editor). Any URL field accepting admin input (NavigationLink.url, MarketingSection.cta_url) validates against `^(https?://|/|mailto:|tel:)` to block `javascript:`-style injection — never swap this for Filament's built-in `->url()` rule, which rejects legitimate relative paths like `/eligibility`.
