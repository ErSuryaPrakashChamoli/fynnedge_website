---
paths:
  - 'app/Http/Controllers/FaqController.php,app/Support/Seo/Sitemap.php,resources/views/faqs/**,tests/**'
---

# Faqs

## /faqs is a CMS page whose row comes from a migration, and its route isn't named after its slug
/faqs reads its title, excerpt, body and SEO from the `pages` row with slug `faqs`. Like /about, it 404s unless that row is published (a signed Preview URL is the exception). Migration 2026_10_23_124234_create_faqs_cms_page creates the row because production only runs `migrate`. That means the row also exists in EVERY test DB. So never `Page::factory()->create(['slug' => 'faqs'])`, because it violates the unique slug. Never assume `pages` starts empty either (`Page::first()` returns it). To change it in a test, use `Page::where('slug','faqs')->sole()`. To publish every public slug, use firstOrCreate (see publishEveryPublicPage() in NavigationArchitectureTest).
The route is `faqs.index`, not `faqs`, because `faqs.placements` stores that name (FaqPlacement). Anything that maps a page slug to its route must go through `Sitemap::pageRouteName($slug)`, never `route($slug)`. Today that covers the sitemap's cmsPages() and EditPage's Preview action.
