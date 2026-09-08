---
paths:
  - 'app/Filament/RelationManagers/FaqsRelationManager.php,app/Models/Page.php,app/Http/Controllers/PageController.php'
---

# Controllers

## FaqsRelationManager is shared across faqable owners (LoanProduct + Page)
`FaqsRelationManager` lives in `app/Filament/RelationManagers/` (not under a single resource) because its owner is the polymorphic `faqable` relation, not one model — it's registered on both LoanProductResource and PageResource. Moved there from `Resources/LoanProducts/RelationManagers/` when Page gained FAQs. Add it to any new model that morphs FAQs onto itself rather than copying it.

`Page::faqs()` is a morphMany ordered by sort_order. `PageController::show()` passes `$page->faqs()->published()->get()` — always scope through `published()` at the call site, since the relation itself is unscoped. `pages/show.blade.php` renders BOTH `x-site.faq-accordion` and `x-site.faq-json-ld` from that same collection: Google's FAQPage guidelines require the content to be visible, so never emit the schema without the accordion.

FaqResource's `whereNull('faqable_id')` scope keeps page-attached FAQs out of the "General FAQs" list automatically.
