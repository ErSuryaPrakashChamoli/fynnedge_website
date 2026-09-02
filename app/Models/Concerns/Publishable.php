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
            })
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * True once status=Published and published_at/expires_at say this record
     * is live right now — the same live-evaluated rule scopePublished() uses,
     * available on a single already-loaded model without re-querying.
     */
    public function isCurrentlyPublished(): bool
    {
        if ($this->status !== PublishStatus::Published) {
            return false;
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
