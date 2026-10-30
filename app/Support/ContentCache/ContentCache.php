<?php

namespace App\Support\ContentCache;

use App\Enums\ContentCacheSection;
use App\Models\Concerns\Publishable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Cached public content with explicit, commit-safe invalidation.
 *
 * Every ContentCacheSection has a version number in the content_cache_versions
 * table, and a cached value's key holds the versions of the sections it was
 * built from. A content change never deletes cache entries: invalidate() bumps
 * the version once the surrounding transaction has committed, and the next
 * reader simply looks under a new key. Superseded entries expire through
 * their TTL, which is a safety net, not the refresh mechanism.
 *
 * The versions live in the database, not in the cache store, so an eviction,
 * restart, flush or outage of Redis can never lose an invalidation — the worst
 * it can do is leave the cache cold. Reading them is one query per request.
 *
 * The version is read BEFORE the value is built: a change that commits while
 * a value is being built moves the version on, so that value is never trusted
 * by the next reader (the race RateBook's first design lost).
 *
 * Reads never fail because of the cache store. If it is unreachable the value
 * is built from the database, and the store is skipped for the rest of the
 * request so a dead Redis costs one timeout, not one per lookup.
 *
 * Only arrays and scalars can be cached: cache.serializable_classes is false,
 * so a cached object would come back as __PHP_Incomplete_Class. remember()
 * refuses objects in every environment, including the array store tests use.
 *
 * Registered as a scoped singleton (AppServiceProvider).
 */
final class ContentCache
{
    public const TABLE = 'content_cache_versions';

    private const KEY_PREFIX = 'content:';

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 2;

    /**
     * @var array<string, int>|null
     */
    private ?array $versions = null;

    /**
     * Sections invalidated in this request whose bump is waiting for a commit.
     *
     * @var array<string, true>
     */
    private array $pending = [];

    /**
     * Values already read in this request, by key.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    private bool $storeUnavailable = false;

    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * The cached value, or $build()'s result cached under the current
     * versions of $sections.
     *
     * $scheduled lists Publishable models whose rows $build() filters by
     * published_at / expires_at: the entry then also expires when the next of
     * those dates passes, so scheduled content appears (and lapses) on time
     * without an edit to invalidate it.
     *
     * @template TValue
     *
     * @param  list<ContentCacheSection>  $sections
     * @param  Closure(): TValue  $build
     * @param  list<class-string<Model>>  $scheduled
     * @return TValue
     */
    public static function remember(string $name, array $sections, Closure $build, array $scheduled = []): mixed
    {
        return self::instance()->rememberValue($name, $sections, $build, $scheduled);
    }

    /**
     * Marks the sections changed. Their versions are bumped after the current
     * transaction commits (immediately when none is open); a rolled-back
     * transaction bumps nothing.
     */
    public static function invalidate(ContentCacheSection ...$sections): void
    {
        $cache = self::instance();

        foreach ($sections as $section) {
            $cache->pending[$section->value] = true;
        }

        DB::afterCommit(fn () => $cache->bumpPending());
    }

    /**
     * Invalidates whatever is built from $table (see ContentCacheSection::forTable()),
     * for writes that bypass model events: DB::table() updates, saveQuietly().
     */
    public static function invalidateTable(string $table): void
    {
        $sections = ContentCacheSection::forTable($table);

        if ($sections !== []) {
            self::invalidate(...$sections);
        }
    }

    /**
     * Every section's version and when it last changed, for diagnostics.
     *
     * @return array<string, array{version: int, changed_at: Carbon|null}>
     */
    public static function versionReport(): array
    {
        $rows = DB::table(self::TABLE)->get()->keyBy('section');

        return collect(ContentCacheSection::cases())
            ->mapWithKeys(fn (ContentCacheSection $section): array => [$section->value => [
                'version' => (int) ($rows[$section->value]->version ?? 0),
                'changed_at' => isset($rows[$section->value]->updated_at) ? Carbon::parse($rows[$section->value]->updated_at) : null,
            ]])
            ->all();
    }

    public static function enabled(): bool
    {
        return (bool) config('content_cache.enabled', true);
    }

    /**
     * @param  list<ContentCacheSection>  $sections
     * @param  list<class-string<Model>>  $scheduled
     */
    private function rememberValue(string $name, array $sections, Closure $build, array $scheduled): mixed
    {
        // Inside a transaction that changed content, the database already shows
        // the change but the version has not moved yet: anything cached now
        // would land under the old key and outlive a rollback. Read through.
        if (! self::enabled() || $this->pending !== []) {
            return $build();
        }

        $key = $this->key($name, $sections);

        if ($key === null) {
            return $build();
        }

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $cached = $this->read($key);

        if ($cached !== null) {
            return $this->memo[$key] = $cached['value'];
        }

        return $this->memo[$key] = $this->buildLocked($name, $key, $build, $scheduled);
    }

    /**
     * Builds a missing value under a short lock, so a burst of requests that
     * all missed (right after an edit) runs the queries once rather than once
     * each. A lock that cannot be had in time, or at all, never blocks the
     * page: the value is built anyway.
     *
     * @param  list<class-string<Model>>  $scheduled
     */
    private function buildLocked(string $name, string $key, Closure $build, array $scheduled): mixed
    {
        $lock = $this->acquireLock($name);

        try {
            // Another request may have stored it while this one waited.
            $cached = $this->read($key);

            if ($cached !== null) {
                return $cached['value'];
            }

            // Taken before building, for the same reason as the version.
            $expiresAt = $this->expiresAt($scheduled);
            $value = $build();

            self::assertCacheable($value, $name);
            $this->write($key, ['value' => $value], $expiresAt);

            return $value;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Only the lock's own failures are caught here — never the build's, which
     * must surface as they would without a cache.
     */
    private function acquireLock(string $name): ?Lock
    {
        if ($this->storeUnavailable) {
            return null;
        }

        try {
            $lock = $this->store()->lock('content-lock:'.$name, self::LOCK_SECONDS);
            $lock->betweenBlockedAttemptsSleepFor(20)->block(self::LOCK_WAIT_SECONDS);

            return $lock;
        } catch (LockTimeoutException) {
            return null;
        } catch (Throwable $exception) {
            $this->storeFailed($exception);

            return null;
        }
    }

    private function releaseLock(?Lock $lock): void
    {
        try {
            $lock?->release();
        } catch (Throwable $exception) {
            // It expires by itself after LOCK_SECONDS.
            $this->storeFailed($exception);
        }
    }

    /**
     * @param  list<ContentCacheSection>  $sections
     */
    private function key(string $name, array $sections): ?string
    {
        $versions = $this->versions();

        if ($versions === null) {
            return null;
        }

        $parts = collect($sections)
            ->map(fn (ContentCacheSection $section): string => $section->value.'.'.($versions[$section->value] ?? 0))
            ->sort()
            ->implode(':');

        return self::KEY_PREFIX.$name.':'.$parts;
    }

    /**
     * Null when the versions cannot be read (e.g. code deployed before its
     * migration ran): content is then served uncached rather than failing.
     *
     * @return array<string, int>|null
     */
    private function versions(): ?array
    {
        if ($this->versions !== null) {
            return $this->versions;
        }

        try {
            return $this->versions = DB::table(self::TABLE)
                ->pluck('version', 'section')
                ->map(fn (mixed $version): int => (int) $version)
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Content cache versions could not be read; serving uncached content.', ['exception' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param  list<class-string<Model>>  $scheduled
     */
    private function expiresAt(array $scheduled): CarbonInterface
    {
        $expiresAt = now()->addSeconds(max(1, (int) config('content_cache.ttl', 3600)));

        foreach ($scheduled as $model) {
            if (! in_array(Publishable::class, class_uses_recursive($model), true)) {
                throw new InvalidArgumentException("{$model} is not Publishable, so it has no schedule to expire on.");
            }

            $next = $model::nextScheduledChange();

            if ($next !== null && $next->lt($expiresAt)) {
                $expiresAt = $next;
            }
        }

        return $expiresAt;
    }

    /**
     * @return array{value: mixed}|null
     */
    private function read(string $key): ?array
    {
        if ($this->storeUnavailable) {
            return null;
        }

        try {
            $cached = $this->store()->get($key);
        } catch (Throwable $exception) {
            $this->storeFailed($exception);

            return null;
        }

        return is_array($cached) && array_key_exists('value', $cached) ? $cached : null;
    }

    /**
     * @param  array{value: mixed}  $payload
     */
    private function write(string $key, array $payload, DateTimeInterface $expiresAt): void
    {
        // A boundary already passed (or passing now): caching would only serve
        // a set that is out of date the moment it is stored.
        if ($this->storeUnavailable || $expiresAt <= now()) {
            return;
        }

        try {
            $this->store()->put($key, $payload, $expiresAt);
        } catch (Throwable $exception) {
            $this->storeFailed($exception);
        }
    }

    private function bumpPending(): void
    {
        $sections = array_keys($this->pending);

        if ($sections === []) {
            return;
        }

        $this->pending = [];
        $now = now();

        try {
            $bumped = DB::table(self::TABLE)->whereIn('section', $sections)->increment('version', 1, ['updated_at' => $now]);

            if ($bumped < count($sections)) {
                DB::table(self::TABLE)->insertOrIgnore(array_map(
                    fn (string $section): array => ['section' => $section, 'version' => 1, 'updated_at' => $now],
                    $sections,
                ));
            }
        } catch (Throwable $exception) {
            // The content change itself is already committed: never fail the
            // editor's save over it. The TTL bounds how long the old copy lives.
            report($exception);
        }

        $this->versions = null;
        $this->memo = [];
    }

    private function storeFailed(Throwable $exception): void
    {
        if (! $this->storeUnavailable) {
            Log::warning('Content cache store unavailable; serving content from the database for this request.', ['exception' => $exception->getMessage()]);
        }

        $this->storeUnavailable = true;
    }

    private function store(): Repository
    {
        return Cache::store(config('content_cache.store'));
    }

    private static function assertCacheable(mixed $value, string $name): void
    {
        if (is_object($value)) {
            throw new InvalidArgumentException("Content cache [{$name}] can only hold arrays and scalars; got ".$value::class.'.');
        }

        if (is_array($value)) {
            array_walk_recursive($value, function (mixed $item) use ($name): void {
                if (is_object($item)) {
                    throw new InvalidArgumentException("Content cache [{$name}] can only hold arrays and scalars; got ".$item::class.'.');
                }
            });
        }
    }
}
