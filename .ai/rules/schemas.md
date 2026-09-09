---
paths:
  - 'app/Support/Seo/**,app/Filament/Pages/StructuredData.php,app/Filament/Resources/SchemaTemplates/**,app/Filament/Schemas/SeoFormSection.php'
---

# Schemas

## The JSON-LD graph's shape is admin-controlled via schema_* Settings, but @id never is
The three standing @graph nodes are no longer hardcoded. Their `@type` and arbitrary extra properties come from `schema_*` Settings edited on the Structured Data page (app/Filament/Pages/StructuredData.php): `schema_organization_types` (one per line, "Organization" is force-kept), `schema_website_type`, `schema_default_page_type`, `schema_language`, plus `schema_{organization,website,webpage}_extra` JSON merged over the generated values.

`SchemaGraph::applyOverrides()` strips `@id` and `@context` from every override. Do not "fix" that — the WebSite/WebPage/Service nodes reference the Organization by `@id` value, so an overridable id silently disconnects the graph.

Per-record: `seo_metas.page_type` (curated App\Enums\SchemaPageType; FAQPage deliberately excluded — it's generated beside the visible accordion) and `seo_metas.schema_template_id` → SchemaTemplate, a reusable JSON-LD blueprint with `{{ title|description|url|image|site_name|organization_id|website_id }}` placeholders. Precedence is record → view-hardcoded → sitewide Setting.

The template is rendered IN THE LAYOUT, not the view, because that's the only place the final title/description/canonical/og-image are resolved. A new seoable view must pass `:page-type="$r->seoPageType()"` and `:schema-template="$r->seoSchemaTemplate()"` alongside `:structured-data`, or both features silently do nothing there.

`SchemaTemplateRenderer` is subtractive: a placeholder with nothing to fill it removes its key rather than emitting `{{ token }}` or an empty string. `schema_templates.body`, `seo_metas.structured_data` and the `*_extra` Settings are all stored DECODED — never switch any of them to a text column.

Gating: `View:StructuredData` (Shield page permission) gates the page AND the structured-data controls in SeoFormSection; `*:SchemaTemplate` gates the resource. RoleSeeder grants neither on purpose — see its docblock. The pre-existing SEO fields stay ungated so the SEO role keeps what it already had.
