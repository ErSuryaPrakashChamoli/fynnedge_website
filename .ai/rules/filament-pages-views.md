---
paths:
  - 'app/Support/Pages/AboutPageContent.php,app/Filament/Pages/AboutPageSettings.php,resources/views/about.blade.php'
---

# Filament Pages Views

## About page section copy is one Setting, edited on its own admin page
Every hardcoded section on /about (founder headline/points/quote/role, mission & vision cards, "Life at FynnEdge" eyebrow/heading/intro/values, "Work with us" heading/description/button) lives in ONE Setting key `about_page`, resolved by App\Support\Pages\AboutPageContent::resolve() and edited at Admin → Website Settings → About Page (AboutPageSettings, permission View:AboutPageSettings, granted to Marketing in RoleSeeder). Same fallback contract as QuickEnquiryPageContent: never-saved → defaults(); blank text → its default; a SAVED EMPTY list hides that block. "Reset to defaults" stores null.
Still edited elsewhere by design: title/excerpt/body on the `about` Page record (Content → Pages), founder name/photo (Settings → Founder message), the photo strip (CompanyPhoto), and the shared x-site.why-fynnedge cards (also on loan pages). Add a new field to defaults() and the page form together, and read it in about.blade.php via $content['key'].
