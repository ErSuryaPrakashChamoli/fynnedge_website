<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use Carbon\CarbonImmutable;
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
     * The next moment scopePublished() will return a different set without
     * anyone editing a row: the earliest future published_at or expires_at of
     * a Published record. Null when nothing is scheduled. ContentCache expires
     * a cached published set then, so scheduled content goes live on time.
     */
    public static function nextScheduledChange(): ?CarbonImmutable
    {
        $now = now();

        $next = static::query()
            ->where('status', PublishStatus::Published)
            ->toBase()
            ->selectRaw('min(case when published_at > ? then published_at end) as next_publish', [$now])
            ->selectRaw('min(case when expires_at > ? then expires_at end) as next_expiry', [$now])
            ->first();

        $moments = array_filter([$next?->next_publish, $next?->next_expiry]);

        return $moments === [] ? null : CarbonImmutable::parse(min($moments));
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
