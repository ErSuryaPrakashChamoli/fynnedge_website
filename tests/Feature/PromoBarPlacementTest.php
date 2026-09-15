<?php

use App\Enums\FaqPlacement;
use App\Enums\PromoBarTrigger;
use App\Models\PromoBar;

it('renders the promo bar with no way to close it', function () {
    PromoBar::factory()->onEveryPage()->create(['headline' => 'Always-on offer']);

    $this->get('/resources')
        ->assertSee('Always-on offer')
        ->assertDontSee('Close offer')
        ->assertDontSee('dismiss()', false);
});

it('rises on the first scroll with no percentage or close-and-reshow setting sent to the page', function () {
    $config = PromoBar::factory()->make(['trigger' => PromoBarTrigger::Scroll, 'trigger_value' => 20])->clientConfig();

    expect($config['trigger'])->toBe('scroll')
        ->and($config['triggerValue'])->toBe(0)
        ->and($config)->not->toHaveKey('reshowAfterHours');
});

it('shows a promo bar only on the pages it is pinned to', function () {
    PromoBar::factory()->onPages([FaqPlacement::Contact->value])->create(['headline' => 'Contact page offer']);

    $this->get('/contact')->assertSee('Contact page offer');
    $this->get('/resources')->assertDontSee('Contact page offer');
});

it('shows a site-wide promo bar everywhere except the pages it is hidden on', function () {
    PromoBar::factory()->onEveryPage()->hiddenOn([FaqPlacement::Contact->value])->create(['headline' => 'Site-wide offer']);

    $this->get('/resources')->assertSee('Site-wide offer');
    $this->get('/contact')->assertDontSee('Site-wide offer');
});

it('shows one promo bar per page, preferring one pinned to that page over a site-wide one', function () {
    PromoBar::factory()->onEveryPage()->create(['headline' => 'General offer', 'sort_order' => 0]);
    PromoBar::factory()->onPages([FaqPlacement::Contact->value])->create(['headline' => 'Contact-only offer', 'sort_order' => 5]);

    $response = $this->get('/contact');

    $response->assertSee('Contact-only offer')->assertDontSee('General offer');
    expect(preg_match_all('/\sdata-promo-bar[\s>]/', $response->getContent()))->toBe(1);
});

it('does not show a draft or expired promo bar', function () {
    PromoBar::factory()->onEveryPage()->draft()->create(['headline' => 'Draft offer']);
    PromoBar::factory()->onEveryPage()->create(['headline' => 'Expired offer', 'expires_at' => now()->subDay()]);

    $this->get('/contact')
        ->assertDontSee('Draft offer')
        ->assertDontSee('Expired offer');
});

it('does not show a promo bar on the page its button links to', function () {
    PromoBar::factory()->onEveryPage()->create(['headline' => 'Talk to us today', 'cta_url' => '/contact']);

    $this->get('/contact')->assertDontSee('Talk to us today');
    $this->get('/resources')->assertSee('Talk to us today');
});

it('shows the countdown only when it is switched on', function () {
    PromoBar::factory()->onPages([FaqPlacement::Contact->value])->create(['show_countdown' => true, 'expires_at' => now()->addDay()]);
    PromoBar::factory()->onPages([FaqPlacement::ResourcesIndex->value])->create(['show_countdown' => false, 'expires_at' => now()->addDay()]);

    $this->get('/contact')->assertSee('data-promo-bar-countdown', false);
    $this->get('/resources')->assertDontSee('data-promo-bar-countdown', false);
});

it('escapes the headline while highlighting its starred words', function () {
    PromoBar::factory()->onEveryPage()->create(['headline' => 'Rates from *9.99%* <script>alert(1)</script>']);

    $this->get('/contact')
        ->assertSee('<strong class="promo-bar-highlight">9.99%</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('never renders a promo bar whose stored button link is unsafe', function (string $url) {
    PromoBar::factory()->onEveryPage()->create(['headline' => 'Unsafe link offer', 'cta_url' => $url]);

    $this->get('/contact')->assertDontSee('Unsafe link offer');
})->with(['javascript:alert(1)', '//evil.example/steal']);

it('drops a stored colour that is not a hex code', function () {
    PromoBar::factory()->onEveryPage()->create(['background_color' => 'red;position:fixed', 'cta_bg_color' => '#ff6600']);

    $this->get('/contact')
        ->assertSee('--promo-cta-bg:#ff6600;', false)
        ->assertDontSee('red;position', false);
});
