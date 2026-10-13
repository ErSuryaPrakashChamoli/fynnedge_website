---
paths:
  - 'app/Models/PromoBar.php,app/Filament/Resources/PromoBars/**,app/Filament/Resources/Banners/**,app/Filament/Resources/LenderProducts/**,resources/views/loans/**,resources/views/components/site/faq-*.blade.php,database/seeders/LoanLandingPageSeeder.php'
---

# Site Seeders

## Rates in CMS copy are [rate:slug] tokens; typed rates are rejected or hidden
Editors write [rate:personal-loan] ("from 9.99% p.a.") or [rate-range:personal-loan] ("at lenders' published rates of …"); RateText::render() resolves them from RateBook, falling back to "at rates set by each lender" when no verified rate exists. Rendered in promo bar messages, banner heading/subtitle, loan product + landing page bodies, and FAQ answers — accordion AND FAQPage JSON-LD together so schema never contradicts the page. RateText::rule() rejects typed rate claims in promo bar, banner, LoanProduct interest_rate_note and lender offer processing_fee_note (wrap it: ->rule(fn () => RateText::rule()) — Filament treats a bare closure as config). LenderProduct::publicFeeNote() strips any ';' segment of a fee note that states a rate or cites an aggregator (the 2026-09 CSV import packed both into fee notes). LoanLandingPageSeeder emits the token; migration 2026_10_13_122725 converted existing bodies (chunkById — offset chunking skipped rows when updating the filtered column).
