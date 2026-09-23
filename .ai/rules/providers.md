---
paths:
  - 'app/Filament/Resources/Pages/Schemas/PageForm.php,resources/views/components/site/footer.blade.php,app/Providers/AppServiceProvider.php'
---

# Providers

## Page slugs a route depends on are locked; footer hides links to non-live pages
Sitemap::publicPageSlugs() (ROUTED_PAGE_SLUGS + CONTROLLER_PAGE_SLUGS: legal pages, about, careers) is the list of `pages` slugs a public URL depends on. PageForm only derives the slug from the title on create ($operation === 'create'), and disables the slug field for those slugs. The footer's Company/Legal links stay hardcoded (see components-site.md) but render only when their page is published: AppServiceProvider composes `$livePageSlugs` for the footer in ONE query. Contact has no pages row and always shows.
