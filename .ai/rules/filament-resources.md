---
paths:
  - 'app/Models/Concerns/Publishable.php,app/Filament/Resources/**'
---

# Filament Resources

## Publishable now supports scheduled expiry — always query through the scope
`Publishable::scopePublished()` checks status=Published AND published_at<=now() AND (expires_at is null OR expires_at>now()). A model instance can check `$model->isCurrentlyPublished()` for the same live rule without a query. Any public controller using route-model-binding on a Publishable model (instead of querying through `published()`) MUST check `$model->isCurrentlyPublished()` manually — route-bound models skip the scope entirely, which is how scheduled-publish/expiry silently didn't work on LoanProduct/Article/LoanLandingPage's direct show routes before this was fixed. Models with published_at+expires_at: LoanProduct, Article, Page, Testimonial, LoanLandingPage, MarketingSection. Faq/Banner/CompanyPhoto/JobOpening intentionally only have a draft/published toggle (no scheduling) — a deliberate smaller pattern, not an oversight.
