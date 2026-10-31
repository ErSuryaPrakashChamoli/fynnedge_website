<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One path that answered 404, with how often and from where it was asked
 * for. Aggregated, so a crawler asking for the same old URL a thousand times
 * is one row with hits = 1000, not a thousand rows.
 *
 * Holds no visitor data (see the migration): the path is normalised and has
 * no query string, the referrer is kept as scheme://host/path, and a crawler
 * is only counted, never identified.
 *
 * Written with the query builder (record()), so a hit is one UPDATE in the
 * common case and fires no model events.
 */
class NotFoundUrl extends Model
{
    use HasFactory, MassPrunable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'internal_referrals' => 'integer',
            'crawler_hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Counts one 404 for a path that is already normalised. Once
     * max_tracked_paths rows exist a NEW path is dropped rather than stored,
     * which is what keeps a scanner walking random URLs from growing the
     * table without limit; paths already tracked keep counting.
     */
    public static function record(string $path, ?string $referrer, bool $isInternalReferral, bool $isCrawler): void
    {
        $now = now();

        $updated = self::query()->where('path', $path)->update([
            'hits' => DB::raw('hits + 1'),
            'internal_referrals' => DB::raw('internal_referrals + '.(int) $isInternalReferral),
            'crawler_hits' => DB::raw('crawler_hits + '.(int) $isCrawler),
            'last_referrer' => $referrer ?? DB::raw('last_referrer'),
            'last_seen_at' => $now,
        ]);

        if ($updated > 0 || self::query()->count() >= (int) config('seo.not_found.max_tracked_paths')) {
            return;
        }

        // insertOrIgnore: two requests for a new path at once must not fail on the unique index.
        self::query()->insertOrIgnore([
            'path' => $path,
            'hits' => 1,
            'internal_referrals' => (int) $isInternalReferral,
            'crawler_hits' => (int) $isCrawler,
            'last_referrer' => $referrer,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * A path not asked for within the retention period is history, not a
     * problem to act on.
     */
    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<', now()->subDays((int) config('seo.not_found.retention_days')));
    }
}
