---
paths:
  - 'app/Enums/FaqPlacement.php,app/Support/Faqs/**,app/Filament/Resources/PageFaqs/**,resources/views/components/site/page-faqs.blade.php'
---

# Page Faqs Views Components Site

## Page-pinned FAQs: three UIs over one faqs table, one FAQPage entity per URL
`faqs` now backs THREE disjoint admin UIs, kept apart by query scope — never let them overlap:
- FaqResource "General FAQs" — `whereNull('faqable_id')->whereNull('placements')`
- PageFaqResource "Page FAQs" — `whereNull('faqable_id')->whereNotNull('placements')`
- FaqsRelationManager — rows with a `faqable` owner (LoanProduct, Page)

`faqs.placements` is a json array of ROUTE NAMES (see `App\Enums\FaqPlacement`, one case per public page, `HasLabel` + `group()` for the grouped Select). One FAQ can be pinned to several pages. A parameterised placement covers every URL that route serves — `loans.show` shows on all loan products, on top of each product's own FAQs tab.

Rendering rule: ONE accordion and ONE FAQPage JSON-LD block per URL — two would be duplicate structured data. `x-layouts.app` renders `<x-site.page-faqs />` automatically UNLESS the view passes `handles-faqs`. The 7 views that render their own FAQ section pass `handles-faqs` and merge instead, via `PageFaqs::merge($ownFaqs)` in their controller (Home/Faq/LoanProduct/LoanLandingPage/PageController). A new page needs no view change at all — the layout picks it up.

TRAP: never call `PageFaqs::forCurrentRoute()` inside a Livewire component. Livewire re-renders arrive on the `livewire.update` route, so pinned FAQs would vanish the moment a visitor moves a slider. `⚡emi-calculator.blade.php` captures the real route into a public `$placementRoute` prop at mount and passes it to `PageFaqs::merge()`; copy that pattern for any other Livewire component that renders FAQs.
