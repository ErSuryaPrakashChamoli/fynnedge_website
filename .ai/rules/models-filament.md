---
paths:
  - 'app/Models/**,app/Filament/**'
---

# Models Filament

## SEO fields live on a shared seo_metas morph table via Seoable + SeoFormSection
Don't add title/description/canonical/robots columns to individual content tables (pages, loan_products, future blog_posts). Use the Seoable trait (app/Models/Concerns/Seoable.php) for the morphOne relation, and App\Filament\Schemas\SeoFormSection::make() to embed the editable SEO section in a resource form — it saves to the relationship automatically via Filament's Section::relationship('seoMeta').
