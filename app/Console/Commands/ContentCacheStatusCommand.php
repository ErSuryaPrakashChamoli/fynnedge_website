<?php

namespace App\Console\Commands;

use App\Support\ContentCache\ContentCache;
use Illuminate\Cache\FailoverStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * What an operator runs on a server to answer "is the cache healthy, and
 * did my edit invalidate it?": whether the store answers, every section's
 * version and when it last changed, and — on Redis — memory, eviction and
 * hit-rate figures from INFO. Read-only apart from one short-lived probe key;
 * it never prints a cached value or a credential.
 */
class ContentCacheStatusCommand extends Command
{
    protected $signature = 'content-cache:status';

    protected $description = 'Show the public content cache\'s store health, section versions and Redis statistics';

    public function handle(): int
    {
        $storeName = config('content_cache.store') ?? config('cache.default');
        $store = Cache::store($storeName);

        $this->components->twoColumnDetail('Content cache', ContentCache::enabled() ? '<fg=green>enabled</>' : '<fg=yellow>disabled (CONTENT_CACHE_ENABLED=false)</>');
        $this->components->twoColumnDetail('Store', $storeName.' ('.config("cache.stores.{$storeName}.driver").')');
        $this->components->twoColumnDetail('Safety-net TTL', config('content_cache.ttl').'s');

        $healthy = $this->probe($store);
        $this->components->twoColumnDetail('Store read/write', $healthy ? '<fg=green>OK</>' : '<fg=red>FAILING — content is being served from the database</>');

        $this->newLine();
        $this->table(
            ['Section', 'Version', 'Last invalidated'],
            collect(ContentCache::versionReport())->map(fn (array $row, string $section): array => [
                $section,
                $row['version'],
                $row['changed_at']?->toDateTimeString() ?? 'never',
            ])->values()->all(),
        );

        if ($redis = $this->redisStore($store->getStore())) {
            $this->redisStatistics($redis);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function probe(Repository $store): bool
    {
        $key = 'content:health-probe:'.Str::random(12);

        try {
            $store->put($key, 'ok', 10);
            $read = $store->get($key);
            $store->forget($key);

            return $read === 'ok';
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return false;
        }
    }

    private function redisStore(Store $store): ?RedisStore
    {
        if ($store instanceof RedisStore) {
            return $store;
        }

        if ($store instanceof FailoverStore) {
            foreach ((array) config('cache.stores.failover.stores') as $name) {
                if (($inner = Cache::store($name)->getStore()) instanceof RedisStore) {
                    return $inner;
                }
            }
        }

        return null;
    }

    private function redisStatistics(RedisStore $store): void
    {
        try {
            $info = $store->connection()->info();
        } catch (Throwable $exception) {
            $this->components->error('Redis INFO failed: '.$exception->getMessage());

            return;
        }

        // phpredis returns one flat array; Predis groups it by section.
        $info = collect($info)->flatMap(fn (mixed $value, string $key): array => is_array($value) ? $value : [$key => $value]);
        $hits = (int) $info->get('keyspace_hits', 0);
        $misses = (int) $info->get('keyspace_misses', 0);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Redis</>', 'v'.$info->get('redis_version', '?'));
        $this->components->twoColumnDetail('Memory used / limit', $info->get('used_memory_human', '?').' / '.((int) $info->get('maxmemory', 0) === 0 ? '<fg=yellow>no limit set</>' : $info->get('maxmemory_human')));
        $this->components->twoColumnDetail('Eviction policy', (string) $info->get('maxmemory_policy', '?'));
        $this->components->twoColumnDetail('Hit rate (since restart)', $hits + $misses === 0 ? 'no reads yet' : round(100 * $hits / ($hits + $misses), 1)."% ({$hits} hits, {$misses} misses)");
        $this->components->twoColumnDetail('Evicted / expired keys', $info->get('evicted_keys', 0).' / '.$info->get('expired_keys', 0));
        $this->components->twoColumnDetail('Connected / rejected clients', $info->get('connected_clients', 0).' / '.$info->get('rejected_connections', 0));
        $this->components->twoColumnDetail('Uptime', (int) $info->get('uptime_in_seconds', 0).'s');
    }
}
