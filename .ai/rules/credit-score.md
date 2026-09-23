---
paths:
  - 'app/Support/Pages/CreditScorePageContent.php,app/Filament/Pages/CreditScorePageSettings.php,resources/views/credit-score/show.blade.php'
---

# Credit Score

## Credit Score page copy is one Setting PER bureau page
Hero badge/headline/intro, benefits heading+list, highlights strip and meta title/description on /credit-score/{bureau} live in one Setting per bureau, `credit_score_page.{cibil|experian|equifax|crif}` (CreditScorePageContent::settingKey()), so each page is independently editable at Admin → Website Settings → Credit Score Page (`?bureau=` picks the page; permission View:CreditScorePageSettings, granted to Marketing in RoleSeeder). A bureau never saved falls back to the legacy shared key `credit_score_page`, then to defaults — so "reset" stores `[]`, not null (null would resurrect the legacy copy). `{bureau}` in any field still becomes the bureau label via forBureau(); the admin form edits resolve($bureau) (placeholder intact). Blank text → default, saved empty list hides that block. Not editable here: the Livewire check form's steps and the BureauName list.

## Credit Score pages mirror the Calculator three-layer architecture
Each /credit-score/{bureau} page (BureauName enum = the page list; no free slugs, the Livewire check needs a real bureau) has three independent layers, like calculators: (1) heading/intro/benefits/meta in Setting `credit_score_page.{bureau}` (Website Settings → Credit Score Page); (2) "About" heading+rich body in CreditScorePage (table credit_score_pages, one row per `bureau`, Content → Credit Score Pages, rendered via x-site.calculator-explainer); (3) indexing in CreditScoreIndexing (Setting `credit_score_pages_indexing` = {indexed[]}). Unlike CalculatorIndexing it stores INDEXED pages (opt-in) so the default stays `noindex, nofollow` + robots.txt Disallow + no sitemap; an opted-in page gets `Allow: /credit-score/{bureau}` (CrawlerPolicy::allowedPaths) and a sitemap entry. The toggle lives on the settings form but is saved outside the wording Setting so Reset never changes it. FAQs pin via FaqPlacement::CreditScorePages (`credit-score.show[:bureau]`). Canonical/OG per URL come from Page SEO, as for calculators.
