<?php

namespace App\Models\Concerns;

use App\Support\ContentCache\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the cached content built from this model's table (see
 * ContentCacheSection::forTable()) whenever a row is created, updated,
 * deleted, restored (restore() saves) or force-deleted (which fires deleted).
 * The bump waits for the transaction to commit, so it is safe inside
 * RateWorkflow's or an importer's transaction.
 *
 * Writes that skip model events — query-builder updates, saveQuietly() —
 * must call ContentCache::invalidateTable() themselves.
 */
trait InvalidatesContentCache
{
    public static function bootInvalidatesContentCache(): void
    {
        $invalidate = fn (Model $model) => ContentCache::invalidateTable($model->getTable());

        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
