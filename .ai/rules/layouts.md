---
paths:
  - 'app/Filament/Schemas/SeoFormSection.php,app/Models/SeoMeta.php,app/Models/Concerns/Seoable.php,resources/views/components/layouts/app.blade.php'
---

# Layouts

## Custom JSON-LD is an escape hatch on seo_metas, rendered via the layout's structuredData prop
`seo_metas.structured_data` is a `json` column cast to `array` on SeoMeta, edited through the shared `SeoFormSection`'s "Custom JSON-LD structured data" Textarea. It is stored DECODED, never as raw text: the Textarea `formatStateUsing` pretty-prints the array for editing and `dehydrateStateUsing` json_decodes it back, with a closure rule rejecting anything that isn't a JSON object/array. That means the app can only ever re-encode valid JSON — do not switch this to a plain text column.

Rendering lives in `x-layouts.app` behind `@if (filled($structuredData ?? null))`, encoded with `JSON_HEX_TAG` so a `</script>` inside an admin-supplied string value cannot break out of the tag. Every SEO-aware view passes `:structured-data="$record->seoStructuredData()"` alongside the other `seo*()` accessors — add it to any NEW seoable view too, or the field silently does nothing on that page.

This is only for schema.org types the app doesn't generate itself (Service, HowTo, Event…). FAQPage, BreadcrumbList, Article and the sitewide Organization/WebSite graph are generated from real records — never duplicate those here.
