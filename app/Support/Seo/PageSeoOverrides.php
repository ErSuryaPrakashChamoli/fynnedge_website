<?php

namespace App\Support\Seo;

use App\Models\PageSeo;

/**
 * Resolves the admin-managed SEO row (if any) for the URL being rendered.
 *
 * Called once from resources/views/components/layouts/app.blade.php, where the
 * row's values are folded into the layout's own $title/$description/... props
 * BEFORE the existing SeoDefaults chain runs. That ordering is the whole
 * design: the row becomes "the value this page set for itself", so everything
 * downstream — the sitewide defaults, the OG/Twitter derivations, the schema
 * graph — keeps working exactly as it already does, with no second fallback
 * chain to keep in step.
 *
 * A blank field is never an override: it leaves whatever the view passed in
 * place, so a row that only fills in a title cannot blank out a description
 * the page was already emitting.
 */
class PageSeoOverrides
{
    public static function forCurrentRequest(): ?PageSeo
    {
        return self::forPath(request()->path());
    }

    public static function forPath(string $path): ?PageSeo
    {
        $id = PageSeo::activeMap()[PageSeo::normalizePath($path)] ?? null;

        return $id
            ? PageSeo::query()->with('seoMeta.schemaTemplate')->find($id)
            : null;
    }
}
