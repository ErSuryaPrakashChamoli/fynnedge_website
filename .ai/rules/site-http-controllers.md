---
paths:
  - 'resources/views/components/site/language-switch.blade.php,routes/web.php,app/Http/Controllers/LocaleController.php'
---

# Site Http Controllers

## Language switch is a POST form, never an <a href>
Since 2026-10-30 x-site.language-switch renders one POST <form> per locale (hidden `return` field, CSRF-exempt via preventRequestForgery(except: ['language/*'])). The old <a href="/language/{locale}?return=<uri>"> put two crawlable action URLs on every page; Google crawled ~70 of them into "Page with redirect" despite rel="nofollow". The route still answers GET (Route::match) for already-known URLs. Never turn the switch back into a link, and never add /language/ URLs to a sitemap. SiteLanguageTest pins "no <a href> to /language/".
