<?php

use App\Enums\ContentCacheSection;
use App\Enums\LandingPageGroup;
use App\Enums\LoanCategory;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\ContentCache\ContentCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('reports a healthy store and every section\'s version', function () {
    ContentCache::invalidate(ContentCacheSection::Rates);

    Artisan::call('content-cache:status');

    expect(Artisan::output())->toContain('OK')->toContain('rates')->toContain('loan-catalog')->toContain('never');
});

it('fails the status check while the cache store is down', function () {
    unreachableCacheStore();

    $this->artisan('content-cache:status')->assertFailed();
});

it('invalidates only the sections named, and logs who did it', function () {
    Log::spy();

    $this->artisan('content-cache:refresh', ['sections' => ['rates']])->assertSuccessful();

    expect(collect(ContentCache::versionReport())->map(fn (array $row): int => $row['version'])->all())
        ->toMatchArray(['rates' => 1, 'settings' => 0, 'loan-catalog' => 0]);
    Log::shouldHaveReceived('notice')->once()->withArgs(fn (string $message, array $context): bool => $context['sections'] === ['rates']);
});

it('rejects an unknown section without invalidating anything', function () {
    // Not 0: data migrations that ship redirect rows have already moved the Redirects version.
    $versionsBefore = collect(ContentCache::versionReport())->sum('version');

    $this->artisan('content-cache:refresh', ['sections' => ['everything']])->assertFailed();

    expect(collect(ContentCache::versionReport())->sum('version'))->toBe($versionsBefore);
});

it('refuses to run without a section or --all', function () {
    $versionsBefore = collect(ContentCache::versionReport())->sum('version');

    $this->artisan('content-cache:refresh')->assertFailed();

    expect(collect(ContentCache::versionReport())->sum('version'))->toBe($versionsBefore);
});

it('warms the mega menu so the next page view runs no landing page query', function () {
    $product = LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    LoanLandingPage::factory()->published()->for($product, 'loanProduct')->create(['group' => LandingPageGroup::ByAmount]);

    $this->artisan('content-cache:warm')->assertSuccessful();
    app()->forgetScopedInstances();
    DB::enableQueryLog();
    $this->get('/');

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'loan_landing_pages')))->toBeEmpty();
});
