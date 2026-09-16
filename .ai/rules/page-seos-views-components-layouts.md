---
paths:
  - 'app/Models/PageSeo.php,app/Support/Seo/PageSeoOverrides.php,app/Filament/Resources/PageSeos/**,resources/views/components/layouts/app.blade.php'
---

# Page Seos Views Components Layouts

## Per-URL SEO overrides are folded into the layout's props, and win over the view
Website Settings → Page SEO (PageSeo model, keyed by a normalised url_path) sets meta tags for any URL, including the pages with no record of their own (/, /contact, /calculators, /faqs). Values live on the shared seo_metas morph table via Seoable + SeoFormSection — never as columns on page_seos.

PageSeoOverrides::forCurrentRequest() is called ONCE at the top of x-layouts.app and reassigns the layout's own $title/$description/$canonical/$robots/$ogImage/$pageType/$structuredData/$schemaTemplate/$social props before the SeoDefaults chain runs. Keep it there: the row becomes "the value this page set for itself", so everything downstream works unchanged and there is no second fallback chain. Every field is applied with `?:`, so a blank field overrides nothing.

A row WINS over a record's own SEO section (Page/Article/LoanProduct). That is deliberate — an admin who adds a rule for an exact URL and sees nothing change has no way to tell which layer won. Wire any NEW field added to SeoFormSection into the layout block too, or it silently does nothing here.

PageSeo::normalizePath() and Redirect::normalizePath() both delegate to App\Support\UrlPath::normalize(); form uniqueness is validated against the NORMALISED value, never a plain unique() rule.
