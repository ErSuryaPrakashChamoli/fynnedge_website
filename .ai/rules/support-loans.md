---
paths:
  - 'resources/views/components/site/partials/loan-mega-menu-panel.blade.php,resources/views/components/site/header.blade.php,app/Support/Loans/LoanMegaMenu.php'
---

# Support Loans

## The desktop mega menu panel is cached HTML — keep it free of per-visitor state
Since 2026-10-30 the header's desktop Loans panel (partials/loan-mega-menu-panel.blade.php, ~450 links, 222KB) is rendered once and cached as HTML by LoanMegaMenu::panelHtml(), keyed by locale + sha1(url('/')) and invalidated with ContentCacheSection::LoanCatalog (plus the products'/landing pages' publish schedule). Anything inside it is served to every visitor of that language: never put request()->routeIs()/active state, @auth/@guest, csrf_token(), old() or per-visitor text in the partial — keep those in header.blade.php around it. Data it shows must come from LoanMegaMenu::categories() or tables in the LoanCatalog section, or edits won't refresh it.
