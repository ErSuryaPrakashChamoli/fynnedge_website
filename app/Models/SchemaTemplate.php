<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Database\Factories\SchemaTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable JSON-LD blueprint an admin authors in the panel and attaches to
 * any SEO-aware record, so a schema.org type this app does not generate in PHP
 * can still be emitted consistently across many pages.
 *
 * `body` is stored DECODED (a json column cast to array), the same rule the
 * per-record `seo_metas.structured_data` escape hatch follows — the app can
 * therefore only ever re-encode valid JSON. Placeholders inside string values
 * (`{{ title }}`, `{{ url }}`, …) are substituted at render time by
 * App\Support\Seo\SchemaTemplateRenderer, never here.
 *
 * `is_active` rather than the Publishable status/published_at/expires_at set
 * used by content models: a template is admin configuration, not a public
 * record with a publication window, so scheduling it would be machinery
 * nothing reads.
 */
#[Fillable(['name', 'schema_type', 'notes', 'body', 'is_active'])]
class SchemaTemplate extends Model
{
    /** @use HasFactory<SchemaTemplateFactory> */
    use Auditable, HasFactory, HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function seoMetas(): HasMany
    {
        return $this->hasMany(SeoMeta::class);
    }
}
