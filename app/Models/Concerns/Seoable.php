<?php

namespace App\Models\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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
}
