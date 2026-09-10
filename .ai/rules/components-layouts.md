---
paths:
  - 'app/Support/Seo/SeoDefaults.php,app/Support/Seo/Sitemap.php,app/Support/Seo/CrawlerPolicy.php,resources/views/components/layouts/app.blade.php'
---

# Components Layouts

## SEO fallback order: page's own value, then sitewide default, then derived
SeoDefaults resolves every meta/OG/Twitter tag in ONE order: the value THIS page set for itself → the sitewide Setting → a derivation from the page (OG title falls back to the page title, Twitter to OG). Getting it backwards is easy and was actually shipped-then-caught: passing the already-resolved title as "the page's value" let a sitewide default override every page that had its own title. Hence ogTitle(?string $pageOwn, string $fallback) takes both, and the layout passes the raw `$title`/`$description` props as $pageOwn, not the resolved ones.

Per-record OG/Twitter overrides live on seo_metas (og_title, og_description, twitter_title, twitter_description, twitter_image_path) and reach the layout as ONE `:social` prop from Seoable::seoSocial() — add it to any new SEO-aware view, or that record's social fields silently do nothing there. Tags render only when they have a value; never emit an empty og:image/twitter:image.

Sitemap::fromRecords() is the single place noindex exclusion and canonical preference are applied — route a new record source through it rather than mapping the collection directly, or that source will happily list pages that tell crawlers not to index them.

robots.txt is plain text: echo values with {!! !!}. Escaping turns the path pattern /*&signature= into /*&amp;signature= and it silently stops matching.
