---
paths:
  - 'app/Models/MarketingSection.php,app/Models/NavigationLink.php,resources/views/home.blade.php,resources/views/components/site/footer.blade.php,resources/views/components/site/flexi-hybrid-ticker.blade.php'
---

# Site Views Components Site

## Flexi Hybrid ticker (marquee below header) content is admin-editable via MarketingSection placement 'home_flexi_hybrid_ticker'
Badge label (heading), scrolling marquee text (description), and button label/link (cta_label/cta_url) on the homepage strip immediately below the header come from `MarketingSection::forPlacement('home_flexi_hybrid_ticker')`, same additive/fallback-to-hardcoded convention as home_finance_cta etc. — see components-site.md. The strip's VISIBILITY stays gated on `$flexiHybridProduct` (a published FlexiHybridTermLoan LoanProduct) in HomeController, never on the MarketingSection row, so publishing ticker copy alone can't resurrect a dead-end "Apply Now" once the product is unpublished. When admin sets a description, it replaces the whole styled hardcoded phrase (loses the inline accent-colored dash/slash spans) — that's an accepted trade-off, not a bug.
