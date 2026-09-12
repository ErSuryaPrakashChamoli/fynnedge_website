<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Achievement;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Lender;
use App\Models\LoanProduct;

/**
 * The hero is a hardcoded-height 40/60 banner whose whole point is fitting in
 * a desktop viewport with the lender marquee under it. These pin the pieces
 * that silently break that: the column ratio, the shared fixed heights, and
 * the vertical order of banner -> marquee -> stats.
 */
function heroPositions(string $html): array
{
    return [
        'banner' => strpos($html, 'lg:grid-cols-[2fr_3fr]'),
        'heading' => strpos($html, 'Simplifying loans'),
        'marquee' => strpos($html, 'Trusted by leading banks'),
        'stats' => strpos($html, 'Partner banks'),
    ];
}

it('splits the banner 40/60 once a banner is published', function () {
    Banner::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->subMinute(),
        'heading' => 'Instant Personal Loan',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('lg:grid-cols-[2fr_3fr]', false)
        ->assertSee('Instant Personal Loan');
});

it('lets the static column span full width when no banner is published, rather than leaving an empty box', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Simplifying loans')
        ->assertDontSee('lg:grid-cols-[2fr_3fr]', false);
});

it('keeps the carousel and its column on the same hardcoded heights', function () {
    Banner::factory()->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $this->get('/')
        ->assertOk()
        ->assertSee('h-[200px]', false)
        ->assertSee('md:h-[300px]', false)
        ->assertSee('lg:h-[330px]', false)
        ->assertSee('xl:h-[350px]', false);
});

it('orders the hero as banner, then lender marquee, then the stats strip', function () {
    Lender::factory()->create();
    Banner::factory()->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $p = heroPositions($this->get('/')->assertOk()->getContent());

    expect($p['banner'])->toBeLessThan($p['heading']);
    expect($p['heading'])->toBeLessThan($p['marquee']);
    expect($p['marquee'])->toBeLessThan($p['stats']);
});

it('keeps the Flexi Hybrid ticker above the banner', function () {
    Banner::factory()->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
    LoanProduct::factory()->published()->create(['category' => LoanCategory::FlexiHybridTermLoan]);

    $html = $this->get('/')->assertOk()->getContent();

    expect(strpos($html, 'Our Specialty'))->toBeLessThan(strpos($html, 'lg:grid-cols-[2fr_3fr]'));
});

it('fills the stats row with four figures derived from real records when no achievement is published', function () {
    Lender::factory()->count(3)->create();
    LoanProduct::factory()->published()->create();
    Article::factory()->published()->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Partner banks &amp; NBFCs', false)
        ->assertSee('Loan products compared')
        ->assertSee('Free loan calculators')
        ->assertSee('Guides &amp; resources', false);
});

it('replaces every derived figure with the admin achievements once any are published', function () {
    Lender::factory()->create();
    Achievement::factory()->published()->create(['label' => 'Cities served', 'value' => '550', 'suffix' => '+']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Cities served')
        ->assertSee('550+')
        ->assertDontSee('Free loan calculators')
        ->assertDontSee('Partner banks &amp; NBFCs', false);
});

it('renders exactly the stats an admin published, in their chosen order, whatever the count', function () {
    Achievement::factory()->published()->create(['label' => 'Cities served', 'value' => '550', 'suffix' => '+', 'sort_order' => 2]);
    Achievement::factory()->published()->create(['label' => 'Disbursed', 'value' => '20', 'prefix' => '₹', 'suffix' => 'Cr+', 'sort_order' => 1]);
    Achievement::factory()->published()->create(['label' => 'Happy customers', 'value' => '12000', 'suffix' => '+', 'sort_order' => 3]);
    Achievement::factory()->create(['label' => 'Still a draft', 'value' => '99']);

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'text-ink-faint">'))->toBeGreaterThanOrEqual(3)
        ->and($html)->not->toContain('Still a draft')
        ->and(strpos($html, 'Disbursed'))->toBeLessThan(strpos($html, 'Cities served'))
        ->and(strpos($html, 'Cities served'))->toBeLessThan(strpos($html, 'Happy customers'))
        ->and($html)->toContain('₹20Cr+');
});

it('drops a derived figure that would otherwise render as zero', function () {
    Lender::query()->delete();

    $this->get('/')->assertOk()->assertDontSee('Partner banks &amp; NBFCs', false);
});

/**
 * The carousel has to work both ways: advance on its own, and be driveable by
 * hand. The manual half was effectively missing on touch devices because the
 * arrows were revealed only on :hover.
 */
it('renders always-visible manual controls rather than hover-gated ones', function () {
    Banner::factory()->count(2)->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Previous banner"');
    expect($html)->toContain('aria-label="Next banner"');
    expect($html)->toContain('aria-label="Go to banner 1"');
    expect($html)->toContain('aria-label="Go to banner 2"');

    // the arrows must not be hidden behind a hover state
    $arrow = substr($html, strpos($html, 'aria-label="Next banner"') - 300, 400);
    expect($arrow)->not->toContain('group-hover:opacity-100');
    expect($arrow)->not->toContain('opacity-0');
});

it('auto-advances and routes every manual action through the timer reset', function () {
    Banner::factory()->count(2)->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('this.next()');
    expect($html)->toContain('6000');
    expect($html)->toContain('step(1)');
    expect($html)->toContain('step(-1)');

    // A single always-running interval that no-ops while paused: a stuck
    // hover/focus flag must never be able to kill the slideshow for good.
    expect($html)->toContain('get paused()');
});

it('offers a pause control and does not latch auto-play off after a touch tap', function () {
    Banner::factory()->count(2)->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Pause banner slideshow');
    expect($html)->toContain('toggle()');
    expect($html)->toContain("pointerType !== 'touch'");
});

it('shows no carousel controls for a single banner, which has nothing to navigate', function () {
    Banner::factory()->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('aria-label="Next banner"', false)
        ->assertDontSee('aria-label="Go to banner 1"', false);
});

/**
 * Motion runs left-to-right: row-reverse puts slide one against the right edge
 * and stacks the rest off-screen to its left, so a POSITIVE translate walks
 * rightwards while still showing them in the admin's sort_order. Flipping the
 * sign on a normal row would reverse the running order instead of the motion.
 */
it('moves slides left to right', function () {
    Banner::factory()->count(3)->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('flex-row-reverse');
    expect($html)->toContain('translateX(${current * 100}%)');
    expect($html)->not->toContain('translateX(-${current * 100}%)');
});

/**
 * A trailing copy of the first slide means passing the end animates in the
 * same direction as every other step, instead of sweeping the whole track
 * backwards through every slide.
 */
it('appends one clone of the first slide so the loop wraps seamlessly', function () {
    Banner::factory()->count(3)->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    // 3 banners + 1 trailing clone
    expect(substr_count($html, 'relative h-full w-full shrink-0 basis-full'))->toBe(4);
    // ...but only 3 dots, the clone is not a real destination
    expect(substr_count($html, 'aria-label="Go to banner'))->toBe(3);
    // the clone is a visual duplicate only
    expect($html)->toContain('aria-hidden="true"');
    // and the snap off it is transition-suppressed
    expect($html)->toContain('transition: none');
    expect($html)->toContain('settle($event)');
});

it('adds no clone for a single banner, which never moves', function () {
    Banner::factory()->create(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'relative h-full w-full shrink-0 basis-full'))->toBe(1);
});

/*
 * The banner image was served to visitors as
 * http://localhost:8000/storage/banners/... because the public disk built its
 * URL from APP_URL, so whatever value that env var happened to hold on the
 * server was baked into every <img src> — and into the cached config, so it
 * survived .env being corrected. The URL must follow the request instead.
 *
 * Deliberately NOT Storage::fake() here: a faked disk is built with only a
 * `root`, dropping the `url` config entirely, so Laravel falls back to a
 * relative /storage path. That is why every existing test that touched an
 * uploaded image passed while production served an absolute localhost URL —
 * faking the disk hides precisely the setting under test.
 */
it('serves the banner image from a host-relative url so it works on any domain', function () {
    Banner::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->subMinute(),
        'image_path' => 'banners/promo.png',
        'heading' => 'Marketing',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('src="/storage/banners/promo.png"');

    // No scheme and no host: the src starts at the root, so the same row
    // renders correctly on localhost, staging and the live domain alike.
    expect(Banner::query()->sole()->imageUrl())->toBe('/storage/banners/promo.png');
});
