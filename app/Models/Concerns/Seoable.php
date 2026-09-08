<?php

namespace App\Models\Concerns;

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

    public function seoOgImageUrl(): ?string
    {
        $path = $this->seoMeta?->og_image_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
