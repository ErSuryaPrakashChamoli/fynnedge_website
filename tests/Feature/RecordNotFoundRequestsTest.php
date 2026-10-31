<?php

use App\Console\Commands\SeoAuditCommand;
use App\Models\NotFoundUrl;
use App\Models\Redirect;
use Illuminate\Support\Facades\Artisan;

it('counts repeat requests for one missing page as one row, without the query string', function () {
    $this->get('/Old-Page?utm_source=newsletter');
    $this->get('/old-page');

    expect(NotFoundUrl::query()->sole())
        ->path->toBe('/old-page')
        ->hits->toBe(2);
});

it('tells a link from this site apart from one elsewhere and keeps the referrer without its query string', function (string $referrer, int $internalReferrals, string $storedReferrer) {
    // A path stands for a page on this site, which the dataset cannot build before the app boots.
    $onThisSite = fn (string $url): string => str_starts_with($url, '/') ? config('app.url').$url : $url;

    $this->withHeader('Referer', $onThisSite($referrer))->get('/old-page');

    expect(NotFoundUrl::query()->sole())
        ->internal_referrals->toBe($internalReferrals)
        ->last_referrer->toBe($onThisSite($storedReferrer));
})->with([
    'a page on this site' => ['/loans?ref=menu', 1, '/loans'],
    'another site' => ['https://partner.example/blog?q=loan', 0, 'https://partner.example/blog'],
]);

it('counts requests from search crawlers', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->get('/old-page');

    expect(NotFoundUrl::query()->sole()->crawler_hits)->toBe(1);
});

it('records a missing record on a route that exists', function () {
    $this->get('/resources/no-such-article')->assertNotFound();

    expect(NotFoundUrl::query()->sole()->path)->toBe('/resources/no-such-article');
});

it('does not record scanner probes, asset files or URLs that carry a token', function (string $path) {
    $this->get($path)->assertNotFound();

    expect(NotFoundUrl::query()->count())->toBe(0);
})->with([
    'WordPress login probe' => '/wp-login.php',
    'old build asset' => '/build/assets/app-0ld.js',
    'newsletter link' => '/newsletter/expired-link',
]);

it('does not record a form post or seo:audit\'s own requests', function () {
    $this->post('/no-such-form', []);
    $this->withHeader('User-Agent', SeoAuditCommand::USER_AGENT)->get('/old-page')->assertNotFound();

    expect(NotFoundUrl::query()->count())->toBe(0);
});

it('does not record a redirect, a Gone rule or a page that exists', function () {
    Redirect::factory()->create(['source_path' => '/moved-page', 'destination' => '/loans']);
    Redirect::factory()->gone()->create(['source_path' => '/retired-article']);

    $this->get('/moved-page')->assertRedirect();
    $this->get('/retired-article')->assertGone();
    $this->get('/')->assertOk();

    expect(NotFoundUrl::query()->count())->toBe(0);
});

it('stops adding new paths once the limit is reached but keeps counting the tracked ones', function () {
    config(['seo.not_found.max_tracked_paths' => 1]);
    $tracked = NotFoundUrl::factory()->create(['path' => '/already-tracked', 'hits' => 3]);

    $this->get('/already-tracked');
    $this->get('/brand-new-path');

    expect(NotFoundUrl::query()->pluck('path')->all())->toBe(['/already-tracked'])
        ->and($tracked->refresh()->hits)->toBe(4);
});

it('prunes paths nobody asked for within the retention period', function () {
    $this->freezeTime();
    config(['seo.not_found.retention_days' => 90]);
    $stale = NotFoundUrl::factory()->create(['first_seen_at' => now()->subDays(120), 'last_seen_at' => now()->subDays(91)]);
    $recent = NotFoundUrl::factory()->create(['first_seen_at' => now()->subDays(120), 'last_seen_at' => now()->subDays(89)]);

    Artisan::call('model:prune', ['--model' => [NotFoundUrl::class]]);

    $this->assertModelMissing($stale);
    $this->assertModelExists($recent);
});
