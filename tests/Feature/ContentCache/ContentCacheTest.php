<?php

use App\Enums\ContentCacheSection;
use App\Models\LoanProduct;
use App\Support\ContentCache\ContentCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
 * app()->forgetScopedInstances() starts a fresh request's memo, so these
 * prove what the shared cache store holds between requests, not only within
 * one.
 */

it('serves the cached copy to a later request instead of rebuilding it', function () {
    ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'first build');
    app()->forgetScopedInstances();

    expect(ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'second build'))->toBe('first build');
});

it('rebuilds only the values of an invalidated section', function () {
    ContentCache::remember('menu', [ContentCacheSection::LoanCatalog], fn () => 'old menu');
    ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'old rates');

    ContentCache::invalidate(ContentCacheSection::Rates);
    app()->forgetScopedInstances();

    expect([
        ContentCache::remember('menu', [ContentCacheSection::LoanCatalog], fn () => 'new menu'),
        ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'new rates'),
    ])->toBe(['old menu', 'new rates']);
});

it('rebuilds a value that depends on several sections when any one of them changes', function () {
    ContentCache::remember('loan-page', [ContentCacheSection::LoanCatalog, ContentCacheSection::Rates], fn () => 'old');

    ContentCache::invalidate(ContentCacheSection::LoanCatalog);
    app()->forgetScopedInstances();

    expect(ContentCache::remember('loan-page', [ContentCacheSection::LoanCatalog, ContentCacheSection::Rates], fn () => 'new'))->toBe('new');
});

it('moves a section to a new version only when the transaction commits', function () {
    $versionDuringTransaction = DB::transaction(function (): int {
        ContentCache::invalidate(ContentCacheSection::Rates);

        return ContentCache::versionReport()['rates']['version'];
    });

    expect([$versionDuringTransaction, ContentCache::versionReport()['rates']['version']])->toBe([0, 1]);
});

it('keeps the version of a section whose change was rolled back', function () {
    try {
        DB::transaction(function (): void {
            ContentCache::invalidate(ContentCacheSection::Rates);

            throw new RuntimeException('Save failed');
        });
    } catch (RuntimeException) {
    }

    expect(ContentCache::versionReport()['rates']['version'])->toBe(0);
});

it('never caches what a transaction read after changing content, so a rolled-back change cannot be served', function () {
    ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'committed rates');
    app()->forgetScopedInstances();
    $readInsideTransaction = null;

    try {
        DB::transaction(function () use (&$readInsideTransaction): void {
            ContentCache::invalidate(ContentCacheSection::Rates);
            $readInsideTransaction = ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'uncommitted rates');

            throw new RuntimeException('Save failed');
        });
    } catch (RuntimeException) {
    }
    app()->forgetScopedInstances();

    expect([$readInsideTransaction, ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'rebuilt')])
        ->toBe(['uncommitted rates', 'committed rates']);
});

it('does not trust a value that was being built while a change committed', function () {
    ContentCache::remember('rates', [ContentCacheSection::Rates], function (): string {
        // An editor's publish commits while this request is still reading.
        ContentCache::invalidate(ContentCacheSection::Rates);

        return 'rates read before the publish';
    });
    app()->forgetScopedInstances();

    expect(ContentCache::remember('rates', [ContentCacheSection::Rates], fn () => 'rates read after the publish'))->toBe('rates read after the publish');
});

it('expires a cached published set when a scheduled record goes live, without any edit', function () {
    $this->travelTo('2026-10-30 10:00:00');
    LoanProduct::factory()->published()->create(['published_at' => '2026-10-30 10:30:00']);
    $countLive = fn (): int => LoanProduct::query()->published()->count();
    ContentCache::remember('live-products', [ContentCacheSection::LoanCatalog], $countLive, scheduled: [LoanProduct::class]);

    $this->travelTo('2026-10-30 10:30:01');
    app()->forgetScopedInstances();

    expect(ContentCache::remember('live-products', [ContentCacheSection::LoanCatalog], $countLive, scheduled: [LoanProduct::class]))->toBe(1);
});

it('expires a cached published set when a record lapses, without any edit', function () {
    $this->travelTo('2026-10-30 10:00:00');
    LoanProduct::factory()->published()->create(['expires_at' => '2026-10-30 18:00:00']);
    $countLive = fn (): int => LoanProduct::query()->published()->count();
    ContentCache::remember('live-products', [ContentCacheSection::LoanCatalog], $countLive, scheduled: [LoanProduct::class]);

    $this->travelTo('2026-10-30 18:00:01');
    app()->forgetScopedInstances();

    expect(ContentCache::remember('live-products', [ContentCacheSection::LoanCatalog], $countLive, scheduled: [LoanProduct::class]))->toBe(0);
});

it('lets a change made behind the application\'s back show once the safety-net TTL passes', function () {
    config(['content_cache.ttl' => 600]);
    $this->travelTo('2026-10-30 10:00:00');
    ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'before');

    $this->travelTo('2026-10-30 10:10:01');
    app()->forgetScopedInstances();

    expect(ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'after'))->toBe('after');
});

it('serves content from the database when the cache store is down', function () {
    unreachableCacheStore();

    expect(ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'from the database'))->toBe('from the database');
});

it('skips a dead cache store for the rest of the request after the first failure, logging it once', function () {
    unreachableCacheStore();
    Log::spy();

    ContentCache::remember('first', [ContentCacheSection::Settings], fn () => 'a');
    ContentCache::remember('second', [ContentCacheSection::Rates], fn () => 'b');

    Log::shouldHaveReceived('warning')->once();
});

it('serves content uncached when the versions table does not exist yet', function () {
    Schema::drop(ContentCache::TABLE);

    expect(ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'from the database'))->toBe('from the database');
});

it('reads the database on every request while the content cache is switched off', function () {
    config(['content_cache.enabled' => false]);
    ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'first build');
    app()->forgetScopedInstances();

    expect(ContentCache::remember('greeting', [ContentCacheSection::Settings], fn () => 'second build'))->toBe('second build');
});

it('refuses to cache an object, which the production cache store could not read back', function () {
    ContentCache::remember('product', [ContentCacheSection::LoanCatalog], fn () => ['product' => new LoanProduct]);
})->throws(InvalidArgumentException::class, 'can only hold arrays and scalars');
