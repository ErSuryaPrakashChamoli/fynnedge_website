<?php

use App\Models\NotFoundUrl;
use App\Models\Redirect;
use Illuminate\Support\Facades\Artisan;

it('fails and names a missing page that a page on this site links to', function () {
    NotFoundUrl::factory()->linkedInternally()->create(['path' => '/old-guide', 'last_seen_at' => now()]);

    $exitCode = Artisan::call('seo:not-found');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('/old-guide')->toContain('1 missing page(s) are linked from this site.');
});

it('passes, leaving out paths a rule now covers and paths not requested recently', function () {
    $this->freezeTime();
    NotFoundUrl::factory()->create(['path' => '/recent-miss', 'first_seen_at' => now()->subDays(2), 'last_seen_at' => now()->subDay()]);
    NotFoundUrl::factory()->linkedInternally()->create(['path' => '/old-miss', 'first_seen_at' => now()->subDays(40), 'last_seen_at' => now()->subDays(30)]);
    NotFoundUrl::factory()->linkedInternally()->create(['path' => '/now-redirected', 'first_seen_at' => now()->subDays(2), 'last_seen_at' => now()->subDay()]);
    Redirect::factory()->create(['source_path' => '/now-redirected', 'destination' => '/loans']);

    $exitCode = Artisan::call('seo:not-found', ['--days' => 7]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('/recent-miss')->not->toContain('/old-miss')->not->toContain('/now-redirected');
});
