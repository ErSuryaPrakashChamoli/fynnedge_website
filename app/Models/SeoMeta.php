<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['title', 'description', 'canonical_url', 'og_image_path', 'robots', 'page_type', 'schema_template_id', 'structured_data'])]
class SeoMeta extends Model
{
    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function schemaTemplate(): BelongsTo
    {
        return $this->belongsTo(SchemaTemplate::class);
    }
}
