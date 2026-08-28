<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['title', 'description', 'canonical_url', 'og_image_path', 'robots'])]
class SeoMeta extends Model
{
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
