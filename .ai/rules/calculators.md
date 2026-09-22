---
paths:
  - 'app/Support/Calculators/CalculatorPagesContent.php,app/Filament/Pages/CalculatorPagesSettings.php,resources/views/calculators/**'
---

# Calculators

## Calculator page headings are one Setting; the "About" body stays elsewhere
Meta title/description, h1 and intro for /calculators and every calculator page (plus the "About" heading on eligibility/prepayment) live in ONE nested Setting `calculator_pages` (keys: index, emi, eligibility, prepayment, fixed_deposit, sip, daily_sip, gst), resolved by App\Support\Calculators\CalculatorPagesContent and edited at Website Settings → Calculators Page (View:CalculatorPagesSettings, granted to Marketing). Loan calculators share copy per type; `{loan}` becomes the LoanCategory label via forPage($page, $category). Blank field → default; reset stores null. Do NOT duplicate the "About this calculator" body here — it stays on CalculatorPage (FD/SIP/Daily SIP/GST) and LoanProduct::calculator_explanation (loan calculators). Breadcrumbs and calculator links (CalculatorCatalog) are intentionally not editable.

## Calculator indexing is a separate Setting that stores HIDDEN pages
Per-calculator search indexing lives in App\Support\Calculators\CalculatorIndexing (Setting `calculator_pages_indexing` = {enabled, noindex[]}), edited in the "Search engine indexing" section of Website Settings → Calculators Page. It is kept OUT of `calculator_pages` so "Reset to defaults" (wording) never re-indexes/de-indexes pages. It stores the noindexed page keys (path under /calculators: `index`, `gst`, `emi/home-loan`), so a missing row or a new CalculatorCatalog entry is indexable by default. Hidden pages render `noindex, follow` via the controller's `robots` view var and are filtered out of Sitemap::calculators(). Any new calculator view must pass `:robots="$robots"` to the layout. The sitewide SearchEngineIndexing switch still wins.
