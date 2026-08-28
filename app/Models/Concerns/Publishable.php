<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Builder;

trait Publishable
{
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
