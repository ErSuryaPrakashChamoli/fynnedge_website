<?php

namespace App\Models\Concerns;

use App\Models\SchemaTemplate;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

trait Seoable
{
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function seoTitle(): string
    {
        return $this->seoMeta?->title ?: $this->name ?? $this->title;
    }

    public function seoDescription(): ?string
    {
        return $this->seoMeta?->description ?: ($this->summary ?? $this->excerpt ?? null);
    }

    public function seoCanonicalUrl(): ?string
    {
        return $this->seoMeta?->canonical_url ?: null;
    }

    public function seoRobots(): ?string
    {
        return $this->seoMeta?->robots ?: null;
    }

    /**
     * Admin-authored JSON-LD for this record, decoded from the shared
     * seo_metas row. Returns null (rather than an empty array) when nothing
     * is set, so call sites can skip rendering the <script> tag entirely.
     *
     * @return array<array-key, mixed>|null
     */
    public function seoStructuredData(): ?array
    {
        $structuredData = $this->seoMeta?->structured_data;

        return filled($structuredData) ? $structuredData : null;
    }

    /**
     * The schema.org `@type` an admin picked for this record's WebPage node.
     *
     * Falls back to the value the view hardcodes (about.blade.php's AboutPage,
     * for instance) and then to the sitewide default Setting, so choosing a
     * type in the panel overrides the view rather than the other way round —
     * an admin who sets one and still sees the old type would have no way to
     * tell which layer won.
     */
    public function seoPageType(?string $default = null): ?string
    {
        return $this->seoMeta?->page_type ?: $default;
    }

    /**
     * The reusable JSON-LD blueprint attached to this record, if it is still
     * active. Deactivating a template therefore stops it rendering everywhere
     * at once without anyone having to detach it record by record — which is
     * the point of templates being a shared entity rather than pasted JSON.
     */
    public function seoSchemaTemplate(): ?SchemaTemplate
    {
        $template = $this->seoMeta?->schemaTemplate;

        return $template?->is_active ? $template : null;
    }

    public function seoOgImageUrl(): ?string
    {
        $path = $this->seoMeta?->og_image_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
